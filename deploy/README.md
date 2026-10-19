# Avto Platform — production

- Domen: `https://platform.buxoroavto.uz`.
- Server: `169.58.252.161`, Ubuntu 24.04, Nginx, PHP 8.3 FPM, MariaDB.
- Ilova: `/var/www/avto-platform/current` — aktiv release'ga symlink.
- Release'lar: `/var/www/avto-platform/releases/<UTC-release-id>`.
- Doimiy fayllar: `/var/www/avto-platform/shared/.env` va `shared/storage`.
- Linux foydalanuvchisi: `avto_platform`.
- MySQL bazasi: `avto_platform`; foydalanuvchi: `avto_platform_usr@127.0.0.1`.

Production `.env` serverda `0600` huquq bilan saqlanadi. Parollar va `APP_KEY` Git yoki release arxiviga kiritilmaydi. Yangi baza foydalanuvchisi faqat o'z bazasiga kira oladi. `client_demo` CRM bazasi bilan ulanish yo'q.

## Yangi release

Lokal `dev.ps1 build` bilan frontendni yig'ing. Manba va `public/build` fayllarini yangi `.tar.gz` arxivga kiriting. Arxivda `.env`, `.git`, `vendor`, `node_modules`, `storage`, `bootstrap/cache`, `public/hot` va SQLite bazalari bo'lmasin.

Arxivni SSH/SFTP orqali serverga yuklang, so'ng root sifatida:

```bash
bash /var/www/avto-platform/current/deploy/release.sh \
  /root/avto-deploy/platform-new.tar.gz \
  /var/www/avto-platform/current/deploy/site.conf \
  20261018T150000Z
```

Oxirgi argument har release uchun yangi UTC vaqt identifikatori bo'lishi kerak. Skript Composer paketlarini `--no-dev` bilan ilova foydalanuvchisi nomidan o'rnatadi, migratsiya va Laravel keshlarini tayyorlaydi, `current` symlink'ini almashtiradi hamda PHP FPM va faqat shu loyihaning worker'ini yangilaydi. Frontend serverda qayta yig'ilmaydi.

Oldingi release yo'li `/var/www/avto-platform/previous-release` faylida saqlanadi. Kodni qaytarish uchun `current` symlink'ini oldingi release'ga almashtiring, PHP FPM'ni reload qiling va worker'ni restart qiling. Migratsiyalar avtomatik orqaga qaytarilmaydi; baza o'zgarishlarini alohida tekshiring.

## Xizmatlar

- Nginx: `/etc/nginx/sites-available/platform.buxoroavto.uz`.
- PHP pool: `/etc/php/8.3/fpm/pool.d/avto-platform.conf`.
- Queue: Supervisor'dagi `avto-platform-worker`.
- Scheduler: `/etc/cron.d/avto-applications`, har daqiqada ilova foydalanuvchisi nomidan.
- Laravel loglari: `/var/www/avto-platform/shared/storage/logs`.
- Nginx loglari: `/var/log/nginx/avto-platform.*.log`.
- Health endpoint: `https://platform.buxoroavto.uz/up`.
- Platformaga alohida Redis: `avto-platform-redis.service`, faqat `127.0.0.1:6381`, parol bilan. Mavjud umumiy Redis o'zgartirilmaydi. Sessiyalar DB 0, kesh/rate limiter DB 1. AOF yoqilgan; 256 MB `noeviction` chegarasi sessiyalarni kesh tufayli o'chirib yubormaydi. Xotira to'lsa yozuv xatolari bo'lishi mumkin: `used_memory`, `evicted_keys` va loglarni kuzatish zarur. Queue hozir database driver'da.
- PHP pool: ushbu 4 CPU / 8 GB RAM serverda `dynamic`, ko'pi bilan 16 worker, boshlanishda 2 ta. Sekin so'rovlar 5 soniyadan keyin `php-fpm-slow.log` ga yoziladi. Boshqa serverga ko'chirilganda RAM/CPU va worker RSS bo'yicha qayta hisoblanadi.
- `/build/assets/` uchun gzip tayyor fayllar va `public, immutable` / 1 yil kesh. Hashli fayllar `shared/assets` ichida oldingi release'lardan ham saqlanadi, ochiq sahifalar keyingi deploy'dan keyin modullarni topa oladi. Ularni tozalash alohida retention siyosati bilan bajariladi; release vaqtida o'chirilmaydi.
- Kabinet/auth HTML umumiy reverse-proxy/CDN keshiga kiritilmaydi. API qoldiq, bron va shaxsiy javoblari ham umumiy keshga kiritilmaydi.

```bash
supervisorctl status avto-platform-worker
nginx -t
systemctl status php8.3-fpm
systemctl status avto-platform-redis
```

Yangi serverda platformaga xos ishlash sozlamalari root orqali `python3 deploy/configure-performance.py` bilan tayyorlanadi. Oldin PHP 8.3 Redis extension va redis-server o'rnatilgan bo'lsin. Skript `.env` va konfiguratsiyalarni root uchun yopiq zaxiraga saqlaydi, faqat platformaga tegishli pool/vhost va yangi Redis instance'ni sozlaydi; maxfiy parollarni chiqarmaydi. PHP/Nginx konfiguratsiyasi tekshirilib, keyin reload qilinadi. `.env` o'zgarishidan so'ng yangi release'da `artisan optimize` bajarilishi kerak.

Bu sozlamalar cheksiz foydalanuvchi sig'imini kafolatlamaydi. Katta katalogda qidiruvdagi `%LIKE%`, chuqur offset pagination va suratlarning bir nechta o'lchamlarini alohida o'lchash kerak. Haqiqiy ma'lumotlar bilan yuklama profiliga qarab full-text qidiruv, cursor pagination, thumbnail/CDN va qo'shimcha worker/serverlar tanlanadi. Yangi yuklangan rasmlar queue orqali 640 px va 1600 px WebP nusxalarga aylantiriladi: kartochka kichik rasmni, sahifa 1600 px nusxani, zoom esa originalni oladi. Oldingi rasmlarda nusxa bo'lmasa, originalga qaytiladi. Queue va GD extension ishlashi kuzatiladi.

## DNS va HTTPS

2026-10-19 kuni tekshirildi: `platform` A yozuvi `169.58.252.161` ga yo'naltirilgan, HTTPS va HTTP → HTTPS yo'naltirish ishlayapti. Let's Encrypt sertifikati 2027-01-16 gacha amal qiladi; `/up` HTTPS orqali `200 OK` qaytaradi.

`/usr/local/sbin/avto-enable-platform-tls` DNS tayyor bo'lgach sertifikatni oldi va HTTPS'ni yoqdi. `avto-platform-tls.timer` muvaffaqiyatdan so'ng avtomatik o'chgan. Yangi serverda yoki DNS qayta tayyorlanganda xizmatni qo'lda ishga tushirish mumkin:

```bash
systemctl start avto-platform-tls.service
journalctl -u avto-platform-tls.service -n 30 --no-pager
```

Keyingi sertifikat yangilanishlarini mavjud `certbot.timer` bajaradi. Nginx reload hook: `/etc/letsencrypt/renewal-hooks/deploy/50-avto-nginx-reload`.

## Zaxiralar

`/usr/local/sbin/avto-backup` har kuni server vaqti bilan 03:15 da ikkala yangi loyihaning bazasi, `.env` va `storage/app` fayllarini `/var/backups/avto` ga saqlaydi. Saqlash muddati 14 kun, papka va fayllar faqat root uchun ochiq. Birinchi zaxira deploy vaqtida olindi. Bu server ichidagi zaxira; boshqa serverga nusxalash sozlanmagan.

Server sozlamalarining dastlabki nusxasi `/root/avto-deploy/backups` ichida. Yangi loyihalarning kirish ma'lumotlari `/root/avto-deployment-credentials.json` ichida `0600` huquq bilan saqlanadi.
