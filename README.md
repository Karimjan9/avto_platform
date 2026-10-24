# Avto Platform

Avto platformasining backend va Blade interfeysi. Vizitka saytidan alohida Laravel ilovasi va Git repozitoriysi.

Marketplace backend: mashinalar (yangi/bu, sotuv/arenda), avtosalonlar, zapchastlar va do'konlar, moto (velosipedsiz). Flutter uchun versiyalangan API, tokenli kirish, e'lonlar, rasmlar va moderatsiya mavjud. API shartnomasi va misollar: [docs/api.md](docs/api.md).

Mashina, elektromobil, arenda, shina, fara va elektr moto uchun dinamik forma sxemasi hamda tekshirish qoidalari bitta servisdan beriladi: `GET /api/v1/catalog/form`. Motosalon, alohida kelishuv shartlari, ombor qoldig'i va texnik filtrlar mavjud. Parametrlar va payload misollari: [docs/product-profiles.md](docs/product-profiles.md).

Yagona transport katalogi va detal mosligi, umumiy detal kartochkasidagi do'kon takliflari, ko'p yo'nalishli kompaniyalar, filiallar/xodimlar, ommaviy yuklash, xaridor so'rovlari, saqlangan qidiruv va ichki bildirishnomalar, buyurtma rezervi va arenda kalendari ham API orqali ishlaydi. Firebase push ulashga tayyor; loyiha yaratilguncha o'chirilgan. Endpointlar va sozlamalar: [docs/marketplace-ecosystem.md](docs/marketplace-ecosystem.md).

Detal mosligi transport varianti va yilini tanlash orqali aniqlanadi. Shaxsiy transportni akkauntda saqlash funksiyasi mahsulot doirasiga kirmaydi.

- Git: https://github.com/Karimjan9/avto_platform.git
- Stek: Laravel 12, Blade, Alpine.js 3, daisyUI 5, Vite 7, Tailwind CSS 4, Axios. Kabinet va moderatsiya dizayni shu loyihada yozilgan; Filament ishlatilmaydi.
- Talablar: PHP 8.2+, Composer 2 va Node.js 24 LTS / npm.
- Lokal manzil: http://127.0.0.1:8001
- Vite: http://127.0.0.1:5174

## Ishga tushirish

Shu papkada PowerShell orqali:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\dev.ps1
```

Laravel serveri, navbat ishchisi va Vite birga ishga tushadi. To'xtatish: `Ctrl+C`.
Skript mavjud bo'lsa `../.tools` ichidagi lokal Node.js va Composer'dan foydalanadi.

Oddiy terminalda Node.js 24 LTS faol bo'lsa, `composer dev` ham ishlaydi.

## Yangi klondan o'rnatish

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\dev.ps1 setup
```

Bu buyruq Composer va npm paketlarini o'rnatadi, mavjud bo'lmasa `.env` va SQLite bazasini yaratadi, ilova kalitini generatsiya qiladi, migratsiyalarni bajaradi va frontendni yig'adi. Kalit generatsiyasi sababli `setup` yangi lokal o'rnatish uchun mo'ljallangan.

## Tekshirish va yig'ish

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\dev.ps1 test
powershell -NoProfile -ExecutionPolicy Bypass -File .\dev.ps1 build
```

Vite'siz, yig'ilgan CSS/JS bilan ishlatish:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

## Tuzilma

- `routes/web.php` — web marshrutlar.
- `resources/views/layouts/app.blade.php` — asosiy Blade layout, CSRF va Vite ulanishi.
- `resources/views/home.blade.php` — bosh sahifa.
- `resources/js/app.js` — JavaScript kirish nuqtasi.
- `resources/js/pages` va `resources/js/widgets` — faqat tegishli sahifada yuklanadigan forma, galereya, ombor, CSV, arenda va diagramma modullari.
- `resources/css/app.css` — Tailwind va uslublar.
- `app/Http/Controllers` — kontrollerlar.
- `database/migrations` — baza migratsiyalari.

Har bir loyihaning `.env`, `APP_KEY`, SQLite bazasi, sessiya cookie nomi va kesh prefiksi alohida. Vaqt zonasi `Asia/Tashkent`, asosiy til `uz`, tarjima topilmasa `en` ishlatiladi. `.env`, baza, `vendor`, `node_modules` va build fayllari Git'ga kiritilmaydi; ikkala lock fayli saqlanadi.

XAMPP/MySQL kerak bo'lsa `.env` ichida `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` ni sozlang va `php artisan migrate` ni bajaring. Vizitka va platforma uchun alohida bazalar yarating.

Apache virtual host'ining `DocumentRoot` qiymati ushbu loyihaning `public` papkasiga yo'naltirilishi kerak.

Production server, baza, HTTPS, zaxiralar va keyingi deploy tartibi: [deploy/README.md](deploy/README.md).

Telefon orqali kirish, 60 soniyalik qayta yuborish, bir soatda uchta SMS cheklovi va shaxsiy sotuvchi/do'kon kabineti: [docs/phone-auth.md](docs/phone-auth.md). SMS provayderi va matni tasdiqlanguncha `SMS_ENABLED=false`; interfeys bu holatni aniq ko'rsatadi va email orqali kirishni taklif qiladi.

## Frontend muhiti va tekshirish

`npm ci` lock fayldagi aniq versiyalarni o'rnatadi. `npm run build` Vite yig'ilishini bajaradi, JS/CSS fayllarning gzip nusxalarini tayyorlaydi va boshlang'ich yuklama chegarasini tekshiradi: JavaScript 80 KiB, CSS 30 KiB (gzip). Kalendar, FilePond/Tom Select, Tabulator, Papa Parse, PhotoSwipe va Chart.js umumiy JS tarkibiga kirmaydi. noUiSlider shkalasi ochilgandagina yuklanadi. Tailwind faqat haqiqiy Blade/JS va pagination manbalarini ko'radi; eski compiled view fayllari CSS hajmini oshirmaydi.

Web manzillar: `/`, `/elonlar`, `/elonlar/{id}`, `/kirish`, `/kirish/email`, `/royxatdan-otish`, `/sotuvchi/sozlash`, `/kabinet`, `/kabinet/profil`, `/kabinet/elonlar/yangi`, `/kabinet/elonlar/{id}/tahrirlash`, `/kabinet/biznes/{id}/ombor`, `/kabinet/arenda/{id}`, `/admin`. Kabinet sessiya va CSRF bilan ishlaydi; brauzer tokenlari localStorage'ga yozilmaydi. Flutter tokenli API ishlashda davom etadi. Odatda ma'lumot bo'lmasa bo'sh holat chiqadi. Mijozga namoyish uchun `MARKETPLACE_PRESENTATION_ENABLED` va alohida `MarketplacePresentationSeeder` bilan ochiq belgilangan test ma'lumotlari yoqilishi mumkin; deploy bu seederni avtomatik ishlatmaydi. `/1` va `/2` faqat asl dizayn rasmlarini ko'rsatadi.

`npm run test:browser` Playwright orqali desktop va Pixel 7 o'lchamida ish oqimlarini tekshiradi. Odatiy sinov manzili `http://127.0.0.1:8012`; `PLAYWRIGHT_BASE_URL` bilan o'zgartiriladi. Faqat alohida sinov bazasi va sinov akkauntlari ishlatilsin. Chromium: `npx playwright install chromium`. Oddiy foydalanuvchi bazasiga avtomatik fixture kiritilmaydi. `test-results` va `playwright-report` Git/release'dan chiqarilgan.

CSV importi zapchast uchun namuna, dastlabki ko'rinish va 50 qatorlik guruhlarni qo'llaydi. Oldingi muvaffaqiyatli guruhlar keyingi xato sabab qaytarilmaydi. Tarmoq/server javobi noma'lum bo'lsa, import avtomatik takrorlanmaydi. Qoldiq yangilashida server versiyasi tekshiriladi; ziddiyatda 409 xabari ko'rsatiladi. Arenda bandligi serverda qayta tekshiriladi; mijoz tomondagi bo'sh sana yakuniy kafolat bermaydi.
