# Dinamik mahsulot formalari

Web (Blade + JS) va Flutter `GET /api/v1/catalog/form` dan maydonlarni oladi. `App\Services\ListingFormSchema` shu metama'lumotlardan `SaveListingRequest` validatsiya qoidalarini ham yaratadi. Har bir forma serverda tekshiriladi.

## So'rov va javob

`section` majburiy. Qolgan selektorlar ixtiyoriy: `condition` (standart `used`), `offer_type` (standart `sale`), `rental_period`, `fuel_type`, `part_category`, `part_unit`, `moto_type`.

```text
GET /api/v1/catalog/form?section=cars&fuel_type=gasoline
GET /api/v1/catalog/form?section=cars&fuel_type=electric&condition=used
GET /api/v1/catalog/form?section=cars&offer_type=rent&rental_period=day
GET /api/v1/catalog/form?section=parts&part_category=tyres&part_unit=set
GET /api/v1/catalog/form?section=parts&part_category=headlights
GET /api/v1/catalog/form?section=moto&moto_type=scooter&fuel_type=electric
```

Javob `data.schema_version=1`, `context`, `refresh_on`, `fields`, `forbidden_fields`, `seller_type` dan iborat. `fields` nuqtali yo'llar bilan indekslangan obyekt. Masalan, `fields["details.battery_capacity_kwh"]`:

```json
{
  "label": "Batareya (kWh)",
  "type": "number",
  "required": true,
  "nullable": false,
  "visible": true,
  "constraints": {"min": 0.01, "max": 1000, "decimal_places": 2}
}
```

`type`: `string`, `integer`, `number`, `money`, `boolean`, `select`, `list`, `object`, `date`. `options` qiymat → nom xaritasi. `default` bo'lsa, maydon yuborilmaganda shu qiymat ishlatiladi; `null` standart qiymat o'rnini bosmaydi. `money` javobda ikki kasrli string, fizik o'lchovlar raqam, `date` esa `YYYY-MM-DD` shaklida qaytadi.

`*` ro'yxat elementini anglatadi: `details.compatible_vehicles.*.brand`. Ro'yxatlar ketma-ket indeksli JSON array bo'lishi kerak. `constraints` ichida `min`, `max`, `decimal_places`, `pattern`, `distinct`, `allowed_keys`, `before_or_equal`, `gte_field` va `equals_field` ishlatiladi. Mos transportning `year_to` qiymati berilganda `year_from` ham berilsin va oxirgi yil boshlang'ich yildan kichik bo'lmasin.

`refresh_on` maydonlaridan biri o'zgarsa, yangi selektorlar bilan sxemani qayta oling. Faqat ko'rinadigan maydonlarni yuboring; `forbidden_fields` dagi qiymatlarni payload'dan olib tashlang. Noma'lum `details`, `rental` va mos transport ichki kalitlari qabul qilinmaydi. `details` har doim obyekt bo'lishi kerak (`{}` ham oddiy zapchastda mumkin).

Forma qoidalari qoralama va moderatsiyaga yuborishda bir xil. Eski minimal mashina/moto payload'larida yoqilg'i ko'rsatilmasa umumiy maydonlar ishlashda davom etadi; yoqilg'i yoki maxsus kategoriya tanlanganida profil talablari kuchga kiradi.

## Mashina

Umumiy maydonlar: `brand`, `model`, `generation`, `trim`, `year`, `mileage_km`, `fuel_type`, `transmission`, `body_type`, `drive_type`, `color`, `technical_condition`, `power_kw`.

- `gasoline`, `diesel`, `lpg`, `cng`, `hybrid`: `engine_cc` majburiy.
- `gasoline`, `lpg`, `cng`, `hybrid`: `gas_equipment=none/lpg/cng` ixtiyoriy.
- `electric`: `battery_capacity_kwh`, `range_km`, `charging_types` majburiy; b/u mashinada `battery_health_percent` ham majburiy. `engine_cc` va gaz uskunasi qabul qilinmaydi.
- `hybrid`: motor bilan birga batareya ma'lumotlari ham ixtiyoriy ko'rsatiladi.

Elektr mashina uchun `details`:

```json
{
  "brand": "BYD",
  "model": "Yuan",
  "generation": "II",
  "trim": "Premium",
  "year": 2025,
  "mileage_km": 18000,
  "fuel_type": "electric",
  "power_kw": 150,
  "battery_capacity_kwh": 60.48,
  "range_km": 420,
  "charging_types": ["type2", "ccs2"],
  "battery_health_percent": 95.5,
  "technical_condition": "good"
}
```

Quvvatlash standarti ro'yxati ulagich turini bildiradi. Backend ulagichning transportga mosligini texnik sertifikat bilan tasdiqlamaydi; ma'lumot sotuvchi tomonidan kiritiladi.

## Arenda

Faqat mashina uchun. `condition=used/new` va sotuvchi turi bundan mustaqil. `price` / `rental_period` asosiy ko'rsatiladigan narxni belgilaydi. `rental.daily_price`, `weekly_price`, `monthly_price` boshqa muddat narxlarini qo'shadi. Asosiy muddatning qiymati `price` dan avtomatik yoziladi; uni alohida yuborsangiz `price` ga teng bo'lishi kerak.

```json
{
  "offer_type": "rent",
  "condition": "used",
  "rental_period": "day",
  "price": "30.25",
  "currency": "USD",
  "rental": {
    "monthly_price": "700.00",
    "deposit": "0.00",
    "min_days": 2,
    "daily_mileage_limit_km": 200,
    "driver_option": "without_driver"
  }
}
```

Bu fragmentga umumiy majburiy maydonlar va mashina `details` obyektini qo'shing. Barcha narx/depozit bir xil `currency` da. `deposit=0` — depozitsiz, `daily_mileage_limit_km=null` — cheklanmagan. `driver_option=with_driver/without_driver/either`. Minimal muddat kunlarda, hatto asosiy narx haftalik yoki oylik bo'lsa ham. Sotuvga qaytarilganda `rental` yozuvi o'chadi.

## Zapchast

`category`, `manufacturer`, `part_number`, `oem_number`, `origin=original/aftermarket`, `unit=piece/set`, `stock_quantity`, `compatible_vehicles`. Eski `compatible_brands` / `compatible_models` matn ro'yxatlari ham ishlaydi. Mosliklar hali sotuvchi ma'lumoti; avtomatik moslik tekshirish yoki transport katalogi mavjud emas.

`stock_quantity` savdo birligini sanaydi: `unit=set`, `quantity_per_set=4`, `stock_quantity=3` — uchta to'rt donalik komplekt. `set` bo'lsa `quantity_per_set` majburiy (2–100), `piece` bo'lsa bu maydon yuborilmaydi. Narx ham bir dona yoki bir komplekt uchun. Buyurtma va ombor rezervi API'si qo'shilgan; [savdo va kompaniya kabineti](marketplace-ecosystem.md) hujjatini ko'ring.

Shina uchun `details`:

```json
{
  "category": "tyres",
  "manufacturer": "Test manufacturer",
  "origin": "aftermarket",
  "unit": "set",
  "quantity_per_set": 4,
  "stock_quantity": 3,
  "tyre_width_mm": 205,
  "tyre_aspect_ratio": 55,
  "tyre_diameter_inch": 16,
  "tyre_season": "winter",
  "manufactured_at": "2025-03-01"
}
```

Shina o'lchamining uch qismi va mavsum majburiy. Ishlab chiqarilgan sana ixtiyoriy, kelajakdagi sana qabul qilinmaydi. Shinaning yangi/bu holati umumiy `condition` maydonida saqlanadi.

Fara uchun `details`:

```json
{
  "category": "headlights",
  "part_number": "HL-001",
  "oem_number": "OEM-001",
  "origin": "original",
  "unit": "piece",
  "stock_quantity": 5,
  "side": "left",
  "position": "front",
  "compatible_vehicles": [
    {"brand": "Chevrolet", "model": "Cobalt", "generation": "II", "year_from": 2020, "year_to": 2025, "engine": "1.5"}
  ]
}
```

Fara uchun `side=left/right/pair`, `position=front/rear`, `origin` va kamida bitta mos transport majburiy. Har bir mos transportda marka va model majburiy, qolganlari ixtiyoriy.

## Moto

`type=motorcycle/scooter/moped`, `brand`, `model`, `year` majburiy. `mileage_km`, `transmission`, `abs` ham mavjud. Benzinli turda motor hajmi, elektr turda quyidagi quvvat va batareya maydonlari majburiy. Velosiped turi kiritilmagan.

```json
{
  "type": "scooter",
  "brand": "Test brand",
  "model": "Electric",
  "year": 2025,
  "fuel_type": "electric",
  "power_kw": 3.5,
  "battery_voltage_v": 72,
  "battery_capacity_ah": 30,
  "range_km": 80,
  "transmission": "automatic",
  "abs": false
}
```

`business_id` berilmasa shaxsiy sotuvchi; berilsa o'zingizga tegishli tasdiqlangan `moto_dealership` bo'lishi kerak. Boshqa yo'nalish biznesini biriktirib bo'lmaydi.
