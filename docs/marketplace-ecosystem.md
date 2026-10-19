# Katalog, kompaniya kabineti va savdo API

Barcha yo'llar `/api/v1` prefiksi bilan. Ochiq o'qishdan tashqari `Authorization: Bearer <token>` kerak. Bu hujjat backend API shartnomasidir; Blade va Flutter shu endpointlardan foydalanadi.

## Yagona transport katalogi va detal mosligi

Ierarxiya: **marka → model → avlod → motor/komplektatsiya varianti**. Modelning `section` qiymati `cars` yoki `moto`; velosiped kiritilmaydi. Zapchast mosligi ham shu transport variantlariga bog'lanadi.

Shaxsiy transportni akkauntda saqlash funksiyasi mahsulot doirasiga kirmaydi. Xaridor detal izlashda kerakli transport variantini va yilini tanlaydi.

| GET | Filtr |
| --- | --- |
| `/catalog/vehicles/makes` | `section`, `q` |
| `/catalog/vehicles/models` | `vehicle_make_id`, `section`, `q` |
| `/catalog/vehicles/generations` | `vehicle_model_id`, `section`, `q` |
| `/catalog/vehicles/variants` | `vehicle_generation_id`, `section`, `q` |

`per_page` 1–100, standart 50. Administrator shu yo'llarning `/admin` prefiksli variantiga `POST` bilan katalog yozuvini qo'shadi. Identifikatori bir xil yozuv qayta yuborilsa mavjud yozuv qaytadi. Katalogga tekshirilgan ma'lumot kiriting; production avtomatik ravishda taxminiy avlod, yil yoki motor ma'lumotlari bilan to'ldirilmaydi.

Payload'lar ketma-ketligi:

```json
{"name":"Chevrolet"}
```
```json
{"vehicle_make_id":1,"section":"cars","name":"Cobalt"}
```
```json
{"vehicle_model_id":1,"name":"Avlod nomi","year_from":2020,"year_to":null}
```
```json
{"vehicle_generation_id":1,"name":"1.5 AT","engine_code":"Motor kodi","engine_cc":1500,"fuel_type":"gasoline","power_kw":78}
```

Yuqoridagi yil va motor qiymatlari API shaklini tushuntiruvchi misol; ular avtomatik seed qilinmaydi. E'londa `vehicle_variant_id` berilsa marka, model, avlod, komplektatsiya va yoqilg'i katalogdan olinadi. Boshqa bo'lim varianti, avlodga mos kelmagan yil yoki katalogga zid qiymat `422` qaytaradi. Eski matnli marka/model payload'i ham ishlaydi.

`GET /catalog/form?section=cars&vehicle_variant_id=1` tanlangan katalog ma'lumotlarini `prefill` va `read_only_fields` bilan qaytaradi.

Mos detallar `GET /parts?vehicle_variant_id=1&year=2024` orqali topiladi. Moslik variant va yil oralig'i bo'yicha katalog ma'lumotidan aniqlanadi.

## Umumiy detal kartochkasi va do'kon takliflari

`part_products` — ishlab chiqaruvchi, artikul, OEM, kategoriya, o'zgarmas texnik xususiyatlar va mos transportlar. `listings` / `part_details` — har bir sotuvchining narxi, yangi/bu holati, original/analog, savdo birligi, qoldig'i, yetkazishi va kafolati.

Kartochka ishlab chiqaruvchi + normallashtirilgan artikul bilan aniqlanadi. Artikuldagi bo'shliq, tire, nuqta va pastki chiziq olib tashlanadi, harflar katta shaklga o'tadi. **Boshqa ishlab chiqaruvchining ayni raqami alohida kartochka** bo'ladi. OEM raqamining o'zi analoglarni bir kartochkaga birlashtirmaydi.

| Usul | Yo'l | Amal |
| --- | --- | --- |
| GET | `/parts` | `part_number`, `manufacturer`, `category`, `vehicle_variant_id`, `year` bilan izlash |
| GET | `/parts/{product}` | Faol kartochka va mosliklar |
| GET | `/parts/{product}/offers` | Do'konlarning chop etilgan takliflarini solishtirish |
| POST | `/part-products` | Kartochka taklif qilish; dastlab `pending` |
| GET | `/me/part-products` | O'zim taklif qilgan kartochkalar |
| GET | `/admin/part-products` | Tasdiqlanishi kutilayotgan kartochkalar |
| PATCH | `/admin/part-products/{product}/moderation` | `status=active/rejected`, rad etishda `reason` |

Kartochka yaratish misoli:

```json
{
  "title":"Chap old fara",
  "manufacturer":"Ishlab chiqaruvchi",
  "part_number":"HL-001",
  "oem_number":"OEM-001",
  "category":"headlights",
  "attributes":{"side":"left","position":"front"},
  "fitments":[{"vehicle_variant_id":1,"year_from":2020,"year_to":2025}]
}
```

`attributes` faqat kategoriyaga mos texnik maydonlardan iborat. Shina uchun o'lcham va mavsum, fara uchun tomon/joylashuv va kamida bitta moslik talab qilinadi. Har bir moslikda `vehicle_variant_id`; yillar ixtiyoriy va katalog avlodi oralig'ida bo'lishi kerak. Bir variant kartochkada bir marta uchraydi, jami 50 tagacha.

Taklif oddiy `/me/listings` orqali yaratiladi: `section=parts`, `part_product_id`, `business_id`, ixtiyoriy `business_branch_id` va umumiy e'lon maydonlari. Kartochkaga tegishli maydonlar avtomatik to'ldiriladi va ularga zid ma'lumot qabul qilinmaydi. `GET /catalog/form?section=parts&part_product_id=1` tayyor qiymatlarni beradi.

Do'konning `details` obyektiga misol:

```json
{
  "origin":"original",
  "unit":"set",
  "quantity_per_set":2,
  "stock_quantity":5,
  "delivery_available":true,
  "pickup_available":true,
  "delivery_terms":"Shahar ichida bir kunda, haqini do'kon bilan kelishing",
  "warranty_months":12,
  "return_days":14,
  "return_terms":"Qadoq va chek saqlangan bo'lishi kerak"
}
```

Narx **bir savdo birligi** uchun: dona yoki komplekt. Qoldiq ham shu birlikda. Yetkazish narxi mahsulot narxiga avtomatik qo'shilmaydi. Kamida bitta olish usuli yoqilgan bo'lsin. `warranty_months=0` / `return_days=0` — shu imkoniyat yo'q; `null` — ko'rsatilmagan. Bu maydonlar sotuvchi shartlarini bildiradi.

Solishtirishda narxlarni bir valyutada saralang: `/parts/1/offers?currency=USD&sort=price_asc&in_stock=1`. Komplektdagi dona soni va `origin` har bir taklifda alohida ko'rinadi. Faol bo'lmagan biznes, filial, kartochka va chop etilmagan e'lonlar natijaga kirmaydi.

## Kompaniya, filial va huquqlar

Kompaniya payload'ida `sections: ["cars","parts","moto"]` bitta profilga uch yo'nalishni bog'laydi. Eski `type` asosiy yo'nalish sifatida saqlanadi va yaratilgach o'zgarmaydi; shu yo'nalish `sections` ichida qoladi. `sections` berilmasa eski kompaniyaning yo'nalishlari saqlanadi. Profil tahriri yana moderatsiyaga o'tadi.

Ochiq `/businesses/{id}` profilida yo'nalishlar, faol filiallar, ish vaqti, aloqa va tekshiruv belgisi bor. Mahsulotlar: `GET /businesses/{id}/listings?section=parts`. `type=parts_store` biznes filtri shu yo'nalishda ishlaydigan ko'p yo'nalishli kompaniyani ham topadi. E'lonning `seller_type` qiymati uning bo'limiga qarab aniqlanadi.

Quyidagi kabinet yo'llari `/me/businesses/{business}` prefiksi bilan:

| Usul | Yo'l | Huquq |
| --- | --- | --- |
| GET | `/inventory` | Egasi, `catalog` yoki `inventory` |
| POST / PUT | `/branches`, `/branches/{branch}` | Faqat egasi |
| GET / PUT | `/members` | Faqat egasi |
| DELETE | `/members/{member}` | Faqat egasi |
| PATCH | `/inventory/{listing}/stock` | Egasi yoki `inventory` |
| POST | `/import` | Egasi yoki `catalog` |
| GET / PATCH | `/inquiries`, `/inquiries/{inquiry}` | Egasi yoki `requests` |
| GET / POST | `/part-requests`, `/part-requests/{request}/quotes` | Egasi yoki `requests`; faol zapchast yo'nalishi |
| GET / PATCH | `/orders`, `/orders/{order}` | Egasi yoki `orders` |

Xodim mavjud foydalanuvchi bo'lishi kerak: `PUT /members` uchun `{"user_id":25,"permissions":["catalog","inventory","requests","orders","rentals"]}`. Huquqlar mustaqil. `catalog` to'liq mahsulot tahriri va boshlang'ich qoldiqni ham qamraydi; `inventory` alohida qoldiq endpointini ishlatadi. Xodim kompaniya egasini, profilini yoki boshqa xodimlarning huquqlarini o'zgartira olmaydi. Xodim yaratgan korporativ e'lon kompaniya egasiga tegishli bo'ladi; xodim chiqarilgach ruxsati yo'qoladi.

Filial: `name`, `city`, `address`, ixtiyoriy `phone`, `is_active`, `working_hours`. Ish vaqti misoli: `[{"day":1,"closed":false,"opens":"09:00","closes":"18:00"}]`; 1 — dushanba, 7 — yakshanba. Vaqt `Asia/Tashkent` da. Yopiq kun uchun vaqt shart emas; yopilish ochilishdan kichik bo'lsa keyingi kunni anglatadi. Filialni yopish uchun `is_active=false`; unga tegishli takliflar ommadan yashiriladi.

Qoldiqni yangilash: `{"stock_quantity":8,"expected_version":3}`. Versiya javobdagi `details.stock_version` dan olinadi. Boshqa amal qoldiqni o'zgartirgan bo'lsa `409`; taklifni qayta oling. `stock_updated_at` server vaqti, mijoz uni yozolmaydi. Qoldiq yangilanishi e'lonni qayta moderatsiyaga yubormaydi. To'liq `PUT` da qoldiq berilmasa avvalgi qoldiq saqlanadi; berilsa vaqt/versiya yangilanadi.

Mavjud zapchastni to'liq `PUT` yoki import orqali tahrirlashda `details.stock_quantity` berilsa, ildizda `expected_stock_version` ham majburiy. Bu narx tahriri bilan birga yuborilgan eski qoldiq yangi buyurtma rezervini bosib ketishini to'sadi. Qoldiqni o'zgartirmasangiz shu maydonni yubormang.

Ommaviy yuklash `{"rows":[<e'lon payload'i>, <e'lon payload'i>]}` ko'rinishida, 1–50 ta qator. Tahrirlanadigan qatorda `id` beriladi. `business_id` kabinetdagi kompaniyadan olinadi. Har qator oddiy e'lon bilan aynan bir xil tekshiruvdan o'tadi. Bitta xato bo'lsa barchasi bekor bo'ladi; xato kaliti masalan `rows.1.price`. Javob: `listing_ids`, `count`.

Murojaat yuborish: `POST /businesses/{id}/inquiries` uchun `message`, ixtiyoriy shu kompaniyaning chop etilgan `listing_id` si. Xaridor tarixi `/me/inquiries` da. Javob: kabinetdagi `PATCH /inquiries/{id}`, `reply` va `status=in_progress/closed`.

## “Menga topib bering”

`POST /me/part-requests`:

```json
{"vehicle_variant_id":1,"vehicle_year":2024,"title":"Chap fara kerak","description":"Original chap old fara qidiryapman.","category":"headlights","part_number":"HL-001","city":"Buxoro"}
```

`vehicle_variant_id` va `vehicle_year` majburiy. So'rov 14 kun ochiq turadi; bir foydalanuvchida 20 ta faol so'rovgacha.

Xaridor: `GET /me/part-requests`, `GET /me/part-requests/{id}`, `PATCH /me/part-requests/{id}/close`. Do'kon kabinetida ochiq so'rovlar `category` va `city` bilan filtrlanadi. Begona xaridor yoki anonim mijoz boshqa so'rovning takliflarini ko'ra olmaydi.

Do'kon taklifi: `POST /me/businesses/{business}/part-requests/{id}/quotes`, `price`, `currency`, `message`, ixtiyoriy o'zining chop etilgan `listing_id` si. Kompaniya har so'rovga bitta taklif beradi; yangi yuborish uni tahrirlaydi.

Xaridor `POST /me/part-requests/{id}/quotes/{quote}/accept` bilan bittasini tanlaydi. So'rov yopiladi, qolgan takliflar rad etiladi. Ikkinchi taklifni parallel qabul qilish `409` bilan to'siladi. **Qabul qilish buyurtma yoki ombor rezervi yaratmaydi**; buyurtma alohida yuboriladi.

## Saqlangan qidiruv va ichki bildirishnomalar

`POST /me/saved-searches`: `{"name":"Cobalt izlayapman","filters":{"section":"cars","model":"Cobalt","currency":"USD","price_max":15000}}`.

Filtrlar ochiq e'lon qidiruvi bilan bitta `ListingSearch` servisidan foydalanadi. `GET /me/saved-searches`, `PATCH /me/saved-searches/{id}` uchun `is_active`, `DELETE` mavjud. Limit 50 ta. Har bir e'lon har qidiruv uchun ko'pi bilan bir marta xabar beradi; qidiruv saqlangan vaqtdan keyin chop etilgan e'lonlar tekshiriladi.

`GET /me/notifications?unread=1`, `PATCH /me/notifications/{uuid}/read`. Bildirishnoma egasigagina ochiq. Taklif, murojaat javobi, buyurtma va arenda o'zgarishlari ham shu yerga keladi. Bildirishnomalar asosiy tranzaksiya bilan saqlanadi; xato sababli bekor bo'lgan amal uchun xabar qolmaydi.

`php artisan marketplace:maintenance` saqlangan qidiruvlarni tekshiradi, rezervlarni tugatadi va push navbatini yuritadi. Scheduler uni har daqiqa bajaradi. Bir aylanishda 100 ta qidiruv va har biridan 50 ta yangi natija; katta hajmda keyingi aylanishlar davom ettiradi.

## Flutter push: Firebase ulash

Firebase loyihasi hali yaratilmagani sababli production'da push o'chirilgan. Ichki bildirishnomalar to'liq ishlaydi. Backend FCM HTTP v1, xizmat akkaunti orqali OAuth2, navbat, takroriy urinish va qurilma tokenini yangilashni qo'llaydi. Rasmiy qo'llanmalar: [server ulanishi](https://firebase.google.com/docs/cloud-messaging/send/v1-api), [Flutter ulanishi](https://firebase.google.com/docs/cloud-messaging/flutter/get-started).

Firebase tayyor bo'lgach, serverdagi himoyalangan katalogga service-account JSON joylanadi; misol yo'l `/var/www/avto-platform/shared/firebase-service-account.json`. Fayl `avto_platform` foydalanuvchisiga o'qiladigan, `0600` bo'lsin. Git, `public` yoki Flutter paketiga qo'shilmaydi. `.env`:

```ini
FIREBASE_ENABLED=true
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CREDENTIALS=/var/www/avto-platform/shared/firebase-service-account.json
```

So'ng `php artisan config:cache` va faqat platforma queue worker'ini qayta ishga tushiring. Flutter uchun Firebase konfiguratsiyasi, foydalanuvchi bildirishnoma ruxsati va iOS APNs sozlamasi mobil loyihada ulanadi. Hozir real qurilmaga yetkazish sinovi bajarilmagan; Firebase chaqiriqlari testlarda soxta javoblar bilan tekshirilgan.

Ilova FCM yuborish tokenini login qilingan foydalanuvchi nomidan ro'yxatdan o'tkazadi:

```http
POST /me/push-devices
```
```json
{"device_id":"installation-uuid","platform":"android","token":"FCM registration token"}
```

`platform=android/ios`. Token o'zgarsa shu `device_id` bilan qayta yuboring. Token bazada shifrlanadi, API javobida ko'rinmaydi. Limit 5 ta qurilma. Logout'dan oldin `DELETE /me/push-devices/{id}` chaqiring. Boshqa account bilan ro'yxatdan o'tgan ayni token oldingi account'dan uziladi.

Push faqat umumiy sarlavha va `notification_id`, `type` ni olib yuradi; telefon, manzil yoki taklif narxi yuborilmaydi. Tafsilotni ilova autentifikatsiyalangan API'dan oladi. Yaroqsiz `UNREGISTERED` token o'chiriladi. Muvaffaqiyatli qurilmaga navbat qayta ishlaganda yana yuborilmaydi; tarmoq uzilishida yetkazish tasdig'i yo'qolsa takroriy push ehtimoli bor. Ichki bildirishnoma esa yagona yozuv bo'lib qoladi. 24 soatdan eski kutilayotgan push'lar keyin ommaviy yuborilmaydi.

## Aniq tekshiruv va reklama belgilari

`POST /admin/verifications` faqat admin uchun: `kind=phone/business_documents/vehicle_inspection`, mos ravishda faqat `user_id` / `business_id` / `listing_id`, `status=verified/revoked`, majburiy yopiq `evidence_reference`, ixtiyoriy `expires_at`.

- `seller.phone_verified`: aynan profil telefoni tekshirilgan; e'lonning ixtiyoriy boshqa `contact_phone` raqami avtomatik tasdiqlanmaydi.
- `contact_phone_verified`: e'londagi raqam tekshirilgan profil raqami bilan bir xil. Profil raqami o'zgarsa eski tekshiruv yangi raqam uchun ishlamaydi.
- `business.documents_verified`: biznes hujjatlari tekshirilgan. Oddiy biznes moderatsiyasi bu belgini bermaydi.
- `vehicle_inspected`: aynan shu transport ko'rigi. Zapchastga qo'yilmaydi; e'lon tahririda qayta tekshirish uchun bekor qilinadi.

Muddati tugagan yoki bekor qilingan belgi `false`. Dalil havolasi va tekshiruvchi identifikatori ochiq resurslarga chiqmaydi. Biznes nomi/manzili o'zgarsa hujjat belgisi bekor qilinadi.

`PUT /admin/listings/{id}/promotion`: `starts_at`, `ends_at`, `is_active`. Faol muddatdagi chop etilgan e'londa `promotion.is_sponsored=true`, `label="Reklama"` bo'ladi. Reklama tekshiruv belgilariga ta'sir qilmaydi. Bu endpoint reklama joylashuvini belgilaydi; pul yechish/to'lov provayderi ulanmagan.

## Zapchast buyurtmalari va rezerv

`POST /me/orders`:

```json
{
  "client_request_id":"123e4567-e89b-42d3-a456-426614174000",
  "items":[{"listing_id":15,"quantity":2}],
  "fulfillment":"pickup",
  "recipient_name":"Xaridor",
  "contact_phone":"+998901234567"
}
```

1–20 ta turli e'lon, har birida 1–1000 savdo birligi. Bitta buyurtmada bir kompaniya, bir filial va bir valyuta bo'lsin. Yetkazishda `fulfillment=delivery` va `delivery_address` majburiy. Tanlangan olish usuli taklifda yoqilgan bo'lishi kerak.

Buyurtma yaratilganda qoldiq tranzaksiyada tekshiriladi va kamayadi. Yetarli bo'lmasa `409`, barcha qatorlar bekor bo'ladi. Pul hisobida butun tiyin/sent ishlatiladi. Narx, birlik, kafolat va qaytarish shartlari buyurtmada nusxa sifatida saqlanadi; e'lon keyin o'zgarsa eski buyurtma o'zgarmaydi.

Xaridor: `GET /me/orders`, `GET /me/orders/{id}`, `PATCH /me/orders/{id}/cancel` (faqat `pending`). Do'kon: kabinetdagi `PATCH /orders/{id}` uchun `status`.

```text
pending → confirmed → ready → completed
   └────────┴──────────┴────→ cancelled
```

Do'kon bekor qilishi mumkin; `completed` yakuniy. Bekor qilish qoldiqni bir marta qaytaradi. `pending` 24 soatda tasdiqlanmasa avtomatik bekor qilinadi. Tasdiqlangach rezerv muddati cheklanmaydi, bajarish/bekor qilishni do'kon boshqaradi. Qoldiq endpointidagi raqam — boshqa rezervlardan tashqari hozir sotish mumkin bo'lgan birliklar.

`client_request_id` ixtiyoriy UUID, mobil tarmoq qayta urinishlari uchun tavsiya etiladi. Bir xil foydalanuvchi, kalit va payload avvalgi buyurtmani qaytaradi; qoldiq yana kamaymaydi. Shu kalit bilan boshqa payload `409` beradi. Yangi buyurtma uchun yangi UUID yuboring. Onlayn to'lov va kuryer integratsiyasi bu oqimga kiritilmagan.

## Arenda kalendari

Ochiq `GET /listings/{id}/availability?from=2026-11-01&to=2026-12-01` faqat band vaqtlarni beradi, xaridor yoki ichki izohlarni bermaydi. So'rov oralig'i ko'pi bilan 93 kun.

`POST /listings/{id}/reservations`:

```json
{"client_request_id":"123e4567-e89b-42d3-a456-426614174001","starts_at":"2026-11-05T08:00:00+05:00","ends_at":"2026-11-07T08:00:00+05:00","driver_option":"without_driver"}
```

Vaqt ISO 8601 va aniq vaqt zonasi bilan yuboriladi. API javobi UTC; server bazasida loyiha vaqt zonasiga o'tkazib saqlanadi. `[boshlanish, tugash)` oralig'i ishlaydi: oldingi bandlov tugagan vaqtda keyingisi boshlanishi mumkin. Bir transport uchun to'qnashuvchi bandlovlar row lock bilan to'siladi. Minimal kun, haydovchi sharti va maksimal 365 kun tekshiriladi.

Narx asosiy `price` / `rental_period` bo'yicha hisoblanadi: boshlangan kun yuqoriga yaxlitlanadi; hafta 7 kun, oy 30 kun. Minimal muddat kunlarda. Boshqa muddat narxlari ma'lumot sifatida saqlanadi, avtomatik eng arzon tarif tanlanmaydi. Hisob va depozit bandlovda saqlanadi; bu to'lov qabul qilinganini bildirmaydi.

Xaridor: `GET /me/rentals`, `PATCH /me/rentals/{reservation}/cancel`. Sotuvchi yoki kompaniyaning `rentals` xodimi: `GET /me/listings/{listing}/calendar`, `PATCH /me/listings/{listing}/calendar/{reservation}` uchun `status=confirmed/cancelled`.

Sotuvchi servis/ta'til vaqtini `POST /me/listings/{id}/calendar-blocks` bilan `starts_at`, `ends_at`, ixtiyoriy yopiq `note` orqali band qiladi. Xaridor bandlovi dastlab `pending`, 24 soat tasdiqlash oynasi; muddati o'tsa bo'sh vaqtga ta'sir qilmaydi va qayta tasdiqlab bo'lmaydi. Egasi qo'ygan blok darhol `confirmed` bo'ladi. Kelgusi faol bandlovlar bor transportni o'chirish, sotuvga o'tkazish yoki boshqa kompaniya/filialga ko'chirish to'siladi.

Bandlov uchun ham `client_request_id` mobil qayta urinishda avvalgi yozuvni qaytaradi. Haqiqiy bo'sh vaqt har POST'da serverda tekshiriladi; mijozda ko'ringan kalendar o'z-o'zicha kafolat emas.
