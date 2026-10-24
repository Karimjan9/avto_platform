# Avto Marketplace API v1

Lokal manzil: `http://127.0.0.1:8001/api/v1`. Production manzili: `https://platform.buxoroavto.uz/api/v1` (domenning DNS va HTTPS sozlamalari tayyor bo'lishi kerak).

Backend va Blade marketplace interfeysi mavjud. Telefon orqali web kirish, SMS cheklovlari va sotuvchi kabineti: [phone-auth.md](phone-auth.md). Flutter uchun tokenli API alohida ishlaydi; mobil ilova, to'lov va chat bu relizga kirmaydi.

## Biznes tuzilmasi

| Bo'lim | `section` | Xususiyatlar | Biznes katalogi |
| --- | --- | --- | --- |
| Mashinalar | `cars` | `condition=new/used`, `offer_type=sale/rent` | `car_dealership` — avtosalon |
| Zapchastlar | `parts` | Yangi/bu ehtiyot qismlar, sotuv | `parts_store` — do'kon |
| Moto | `moto` | `motorcycle`, `scooter`, `moped`; sotuv | `moto_dealership` — motosalon |

Salon/do'kon sotuvchining biznesidir. Yangi/bu esa mahsulot holati; arenda taklif turidir. Moto faqat ikki g'ildirakli motorli transportni qamrab oladi; velosiped va elektr velosiped turlari mavjud emas. Benzinli va elektr moto qo'llanadi.

Kelishuv shartlari alohida `deal_terms` ro'yxatida saqlanadi: `cash`, `installment`, `exchange`; bir nechtasi birga tanlanadi. Berilmasa `['cash']`. `seller_type` javobda biznesdan aniqlanadi: `private`, `car_dealership`, `parts_store`, `moto_dealership`; uni yozish mumkin emas. Salondagi b/u mashina arendaga ham berilishi mumkin.

Yo'nalishlar va tanlov qiymatlarini `GET /catalog` dan oling. Katalogdagi bo'limlar migratsiyada yaratiladi; production'ga test foydalanuvchi yoki soxta e'lon qo'shilmaydi.

## Ulanish va autentifikatsiya

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>
```

`Authorization` ochiq endpointlarda kerak emas. Rasmlar uchun `Content-Type: multipart/form-data` ishlating; boundary'ni HTTP kutubxonangiz yaratsin. Faqat HTTPS orqali production'ga ulaning.

Ro'yxatdan o'tish: `POST /auth/register`

```json
{
  "name": "Sotuvchi",
  "email": "seller@example.com",
  "password": "ExamplePass123",
  "password_confirmation": "ExamplePass123",
  "device_name": "Flutter Android"
}
```

Email kichik harflarga o'tkaziladi. Parol 8–72 belgidan iborat bo'lib, harf va raqam qatnashishi kerak. Parolli ro'yxatdan o'tishda `phone` yuborish taqiqlangan: raqam faqat SMS bilan tasdiqlangandan keyin hisobga ulanadi. Bu boshqalarning raqamini tasdiqsiz band qilib qo'yishning oldini oladi. API tokeni email/parol orqali olinadi; SMS web oqimi sessiya bilan ishlaydi.

Javob: `201`, `{"user": {...}, "token": "...", "token_type": "Bearer"}`. Tokenni Flutter'da qurilmaning himoyalangan xotirasida saqlang. Bazada tokenning o'zi emas, hashi saqlanadi.

- `POST /auth/login` — `email`, `password`, `device_name`; javob shakli ro'yxatdan o'tishnikidek.
- `GET /me` — joriy foydalanuvchi, `{"data": {...}}`.
- `POST /auth/logout` — faqat joriy tokenni bekor qiladi, `204`.
- Token 30 kun amal qiladi (`SANCTUM_EXPIRATION`, minutlarda); tugagach qayta login qiling. Xuddi shu `device_name` bilan qayta kirish o'sha qurilmaning eski tokenlarini bekor qiladi.
- Login va ro'yxatdan o'tish bir IP uchun birgalikda 10 so'rov/minut bilan cheklanadi. Umumiy API limiti: 120 so'rov/minut, foydalanuvchi yoki IP bo'yicha.

Ochiq sotuvchi ma'lumotlarida email, parol, admin belgisi yoki moderatsiyaning ichki maydonlari berilmaydi. E'londagi `contact_phone` sotuvchi ochiq ko'rsatgan aloqa raqamidir.

Shaxsiy hisob javobi `phone_verified`, `seller_type`, `seller_ready` va `city` maydonlarini ham beradi. Bu yerdagi `seller_type` hisob profili uchun `individual` yoki `shop`; e'lon javobidagi biznesdan aniqlanadigan `seller_type` bilan alohida ma'noga ega.

E'lon/biznes yaratish va tahrirlash, e'lon holati, surat yuklash va CSV importi tasdiqlangan telefon hamda to'ldirilgan sotuvchi profilini talab qiladi. Aks holda `403` javobidagi `code` mos ravishda `phone_verification_required` yoki `seller_onboarding_required` bo'ladi. O'qish, o'chirish va admin moderatsiyasi uchun avvalgi vakolatlar saqlanadi. E'lonning aloqa raqami serverda tasdiqlangan hisob/biznes telefonidan olinadi.

## Ochiq endpointlar

| Usul | Yo'l | Vazifa |
| --- | --- | --- |
| GET | `/catalog` | Uch bo'lim va barcha tanlov qiymatlari |
| GET | `/catalog/form` | Tanlovga mos forma, turlar, majburiylik va cheklovlar |
| GET | `/listings` | Faqat chop etilgan e'lonlar |
| GET | `/listings/{id}` | Chop etilgan e'lon, xususiyatlar va rasmlar |
| GET | `/businesses` | Faqat tasdiqlangan salon va do'konlar |
| GET | `/businesses/{id}` | Faol biznes profili |

E'lon filtrlari: `section`, `condition`, `offer_type`, `business_id`, `city`, `region`, `q`, `currency`, `price_min`, `price_max`, `brand`, `model`, `year_min`, `year_max`, `moto_type`.

Qo'shimcha filtrlar: `seller_type`, `deal_term` (bitta shart), `fuel_type`, `transmission`, `generation`, `trim`, `body_type`, `technical_condition`, `abs`, `part_category`, `manufacturer`, `oem_number`, `part_number`, `part_origin`, `in_stock`, `tyre_width_mm`, `tyre_aspect_ratio`, `tyre_diameter_inch`, `tyre_season`, `driver_option`. Boolean query qiymatlari `1` / `0`; `in_stock=1` qoldiq > 0, `in_stock=0` qoldiq = 0. Turli yo'nalishning filtrlari birga berilsa ularning barchasiga mos e'lon izlanadi.

`brand`, `model` va yil filtrlari mashina/moto xususiyatlariga qo'llanadi. `moto_type` berilsa faqat shu moto turi chiqadi. Matnli marka/model bilan birga yagona transport katalogiga bog'lovchi `vehicle_variant_id` mavjud. `part_product_id`, `business_branch_id` ham filtrlanadi.

Narxlar turli valyutalarda aralashtirilmaydi: `price_min`, `price_max` yoki narx bo'yicha saralash berilganda `currency` majburiy. Valyutalar: `UZS`, `USD`. Pul miqdori javobda ikki kasrli **string** shaklida qaytadi, masalan `"15000.25"`.

`sort`: `newest` (standart), `oldest`, `price_asc`, `price_desc`. `per_page`: 1–50, standart 20. Javoblar `data`, `links`, `meta` bilan sahifalanadi.

Misollar:

```text
GET /listings?section=cars&condition=new&offer_type=sale
GET /listings?section=cars&offer_type=rent
GET /businesses?type=car_dealership&city=Buxoro
GET /businesses?type=parts_store
GET /listings?section=parts&business_id=12
GET /listings?section=moto&moto_type=scooter
GET /listings?currency=USD&price_max=20000&sort=price_asc
```

Biznes katalogida `type`, `city`, `q`, `per_page` filtrlari mavjud.

## Shaxsiy e'lonlar

| Usul | Yo'l | Vazifa |
| --- | --- | --- |
| GET | `/me/listings` | O'z e'lonlari, barcha holatlar; `status`, `per_page` filtrlari |
| POST | `/me/listings` | E'lon yaratish, `201` |
| GET | `/me/listings/{id}` | O'z e'loni, shu jumladan qoralama |
| PUT / PATCH | `/me/listings/{id}` | To'liq tahrir: barcha majburiy maydonlarni qayta yuboring |
| PATCH | `/me/listings/{id}/status` | `draft`, `pending`, `sold`, `archived` holatiga o'tkazish |
| DELETE | `/me/listings/{id}` | Yumshoq o'chirish, ommadan yashirish; `204` |
| POST | `/me/listings/{id}/images` | Multipart `images[]` yuklash, `201` |
| DELETE | `/me/listings/{id}/images/{imageId}` | O'z e'lonidagi rasmni o'chirish |

Mashina yaratish:

```json
{
  "section": "cars",
  "title": "Avtomobil sotiladi",
  "description": "Avtomobil haqida batafsil ma'lumot.",
  "condition": "used",
  "offer_type": "sale",
  "price": "15000.25",
  "currency": "USD",
  "is_negotiable": true,
  "region": "Buxoro",
  "city": "Buxoro",
  "contact_phone": "+998901234567",
  "submit": false,
  "details": {
    "brand": "Chevrolet",
    "model": "Cobalt",
    "year": 2024,
    "mileage_km": 15000,
    "engine_cc": 1500,
    "fuel_type": "gasoline",
    "transmission": "automatic",
    "body_type": "sedan",
    "drive_type": "fwd",
    "color": "Oq"
  }
}
```

`business_id` ixtiyoriy. U faqat o'zingizga tegishli, tasdiqlangan va mos turdagi biznesga ishora qilishi mumkin. `section` va biznesning `type` qiymati yaratilgandan keyin o'zgartirilmaydi.

- **Arenda:** `section=cars`, `offer_type=rent`; `rental_period=day/week/month` majburiy. `price` shu muddat uchun narx; `rental.deposit`, `rental.min_days`, `rental.driver_option` ham majburiy. Qo'shimcha kunlik/haftalik/oylik narx va kilometr limiti mavjud.
- **Zapchast:** kategoriya, ishlab chiqaruvchi, artikul/OEM, mos transportlar, original/analog, dona/komplekt va ombor qoldig'i. `details: {}` oddiy detal uchun saqlanib qolgan: kategoriya `other`, birlik `piece`, qoldiq `0`. `tyres` / `headlights` tanlanganda maxsus majburiy maydonlar paydo bo'ladi.
- **Moto:** `details.type=motorcycle/scooter/moped`, `brand`, `model`, `year` majburiy. Uzatma va ABS ham mavjud. `fuel_type=electric` tanlanganda quvvat, kuchlanish, batareya sig'imi va masofa majburiy; benzin tanlanganda motor hajmi majburiy. O'zingizning faol `moto_dealership` biznesingizni biriktirish mumkin.
- Mashinada `brand`, `model`, `year` majburiy; avlod, komplektatsiya va texnik holat ixtiyoriy. Yoqilg'i tanlangach benzin/dizel/gaz/gibrid uchun motor hajmi, elektr uchun batareya parametrlari talab qilinadi. Yoqilg'i hali ko'rsatilmagan oddiy eski payload ham ishlaydi.
- Yil 1900 dan kelgusi yilgacha; manfiy narx/probeg va noma'lum xususiyatlar qabul qilinmaydi.
- `title` 3–160, `description` 10–10000 belgi. Aloqa raqami E.164 formatida.
- `submit=false` yoki berilmasa qoralama; `submit=true` moderatsiyaga yuboradi.

Dinamik formani olish, maydonlar shartlari va to'liq JSON misollari: [Mahsulot profillari](product-profiles.md). `PUT` to'liq almashtirishdir: yuborilmagan ixtiyoriy maydonlar tozalanadi yoki standart qiymatiga qaytadi. Elektrdan benzinga, shinadan faraga yoki arendadan sotuvga o'tishda eski profil ma'lumotlari qolmaydi.

Yangi imkoniyatlar — umumiy detal kartochkasi va do'kon takliflari, ko'p yo'nalishli kompaniya, transport katalogi va detal mosligi, xodimlar, ommaviy yuklash, so'rov/taklif, xabarnoma/push, buyurtma va arenda kalendari: [Kengaytirilgan API](marketplace-ecosystem.md). Qoldiq to'liq PUT'da berilmasa saqlanadi; mavjud kelgusi bandlovlar bo'lsa arendadan sotuvga o'tish to'siladi.

Rasmlar: JPG, JPEG, PNG yoki WebP; har biri ko'pi bilan 5 MiB, bitta e'londa jami 10 ta. Bir nechta so'rov yuborish orqali limitni chetlab o'tib bo'lmaydi. Asosiy rasm — ro'yxatdagi birinchi rasm. Matn yoki rasm o'zgartirilgan chop etilgan e'lon qayta moderatsiyaga o'tadi. Yumshoq o'chirilgan e'lon va unga tegishli media bazadan darhol jismonan tozalanmaydi.

## E'lon hayot sikli

```text
draft → pending → published
           └──→ rejected → draft/pending
published → sold/archived/draft
archived → draft/pending
sold → draft/archived
```

`published` holatini faqat admin beradi. Admin hali yuborilmagan `draft` e'lonni tasdiqlay olmaydi. Admin chop etilgan e'lonni sabab bilan rad etib, ommadan olib tashlashi ham mumkin.

Rad etilgan e'lonning egasi `moderation_reason` ni ko'radi. Tahrirdan so'ng bu sabab tozalanadi. Sotilgan, arxivlangan, rad etilgan, qoralama va o'chirilgan e'lonlar ochiq API'da ko'rinmaydi.

## Salon va do'konlar

`/me/businesses` uchun GET/POST, `/me/businesses/{id}` uchun GET/PUT/DELETE mavjud. Yaratish/tahrir payload'i:

```json
{
  "type": "car_dealership",
  "name": "Salon nomi",
  "description": "Salon haqida",
  "city": "Buxoro",
  "region": "Buxoro",
  "address": "Manzil",
  "phone": "+998901234567"
}
```

Do'kon uchun `type=parts_store`, motosalon uchun `type=moto_dealership`. Yangi yoki tahrirlangan biznes `pending` bo'ladi. Faqat `active` biznes ommada ko'rinadi va unga yangi e'lon bog'lash mumkin. Biznes rad etilsa, qayta tekshirilsa yoki o'chirilsa, uning e'lonlari ham ochiq katalogdan yashiriladi.

## Administrator

Administrator huquqi ochiq API orqali olinmaydi. Avval oddiy account yarating, so'ng serverda:

```bash
cd /var/www/avto-platform/current
runuser -u avto_platform -- php8.3 artisan marketplace:admin admin@example.com
```

Admin UI bu bosqichda yo'q. Admin tokeni bilan API orqali:

- `GET /admin/listings?status=pending`
- `PATCH /admin/listings/{id}/moderation`: `{"status":"published"}` yoki `{"status":"rejected","reason":"Sabab"}`.
- `GET /admin/businesses?status=pending`
- `PATCH /admin/businesses/{id}/moderation`: `{"status":"active"}` yoki `{"status":"rejected","reason":"Sabab"}`.

Rad etish sababi majburiy. Faol biznesni ham admin sabab bilan rad etib, ommadan olib tashlashi mumkin.

## Javoblar va xatolar

- Bitta resurs: `{"data": {...}}`; ro'yxat: `{"data": [...], "links": {...}, "meta": {...}}`.
- Auth javoblari yuqorida ko'rsatilgan alohida shaklda qaytadi.
- `401` — token yo'q, bekor qilingan yoki muddati tugagan.
- `403` — egasiga yoki administratorga tegishli huquq yo'q.
- `404` — resurs mavjud emas yoki ochiq ko'rinishda emas.
- `422` — `{"message":"...","errors":{"field":["..."]}}` validatsiya xatosi.
- `429` — so'rov limiti; `Retry-After` sarlavhasiga amal qiling.

API xatolari `Accept` yuborilmagan holatda ham JSON bo'ladi. Web va Flutter mijozlari bir xil model, siyosat va servislar bilan ishlashi uchun biznes qoidalari controllerlardan alohida saqlangan.
