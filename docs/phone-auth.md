# Telefon orqali kirish va sotuvchi kabineti

Web oqim: telefon raqami → olti xonali SMS kod → shaxsiy sotuvchi yoki do'kon profili → e'lon → kabinet. Kod bir marta ishlatiladi va 5 daqiqa amal qiladi. SMS haqiqatan provayder tomonidan qabul qilingandan keyingina foydalanuvchiga yuborilgani aytiladi.

## Yuborish cheklovlari

- Birinchi va qayta yuborish orasida kamida 60 soniya bor. Brauzerdagi hisoblagich server muddatiga asoslanadi; sahifani yangilash kutishni bekor qilmaydi.
- Bir telefon raqamiga har qanday ketma-ket bir soat ichida ko'pi bilan 3 ta SMS yuboriladi: dastlabki kod va ikkita qayta yuborish. Uchinchi yuborishdan keyin keyingi ruxsat vaqti shu uchta xabarning eng eskisi yuborilganidan bir soat o'tganda keladi. Interfeys qolgan aniq vaqtni ko'rsatadi.
- Yangi sessiya yoki telefon formasiga qaytish cheklovni chetlab o'tmaydi. Kunlik telefon va umumiy IP cheklovlari ham saqlanadi; provayderga muvaffaqiyatsiz murojaatlar alohida chegaralanadi.
- `429` javobidagi `Retry-After` qayta urinishgacha qolgan soniyalarni bildiradi. Foydalanuvchi kod kiritayotgan bo'lsa, kutish davomida hali amal qilayotgan kodni tasdiqlashi mumkin.
- Bir kodni ko'pi bilan 5 marta tekshirish mumkin. Qayta yuborilganda oldingi kod bekor qilinadi.

## Sozlamalar

`.env.example` ichidagi bo'sh `ESKIZ_EMAIL`, `ESKIZ_PASSWORD`, `ESKIZ_SENDER`, `SMS_LOGIN_MESSAGE`, `SMS_CACHE_STORE` va `SMS_ENABLED` sozlanadi. Haqiqiy kirish ma'lumotlari faqat serverning yopiq `.env` faylida turadi; Git, reliz arxivi va brauzerga kiritilmaydi.

Eskiz uchun resurs nomi va kirish maqsadi ko'rsatilgan SMS matni hamda o'zgaruvchan olti raqamli kod shabloni oldindan tasdiqlangan bo'lishi kerak. `:code` faqat ilovaning almashtirish belgisi; provayderga haqiqiy tasodifiy kod yuboriladi. Hisobga kirishning muvaffaqiyatli javobi SMS matni tasdiqlanganini yoki xabar yetkazilganini bildirmaydi.

`SMS_ENABLED=false` holatida telefon formasi xizmat hozircha mavjud emasligini ko'rsatadi va email orqali kirishni taklif qiladi. Kod yuborilmaydi, hisoblagich boshlanmaydi va OTP yozuvi yaratilmaydi. Email orqali mavjud kabinetni ochish mumkin; e'lon yaratish uchun telefonni tasdiqlash talabi saqlanadi. SMSni yoqishdan oldin provayder shabloni, jo'natuvchi va ruxsat berilgan raqamga haqiqiy SMS tekshirilsin; keyin konfiguratsiya keshi yangilansin.

Production'da OTP locklari va Eskiz tokeni PHP workerlar orasida umumiy saqlanadi; standart `SMS_CACHE_STORE=database`. Kodning o'zi bazaga, sessiyaga, logga yoki xato formaning eski qiymatlariga yozilmaydi. Bazada ilova kaliti bilan bog'langan xesh saqlanadi; challenge sessiya va hisobga bog'lanadi.

## Web manzillar

| Usul | Yo'l | Vazifa |
| --- | --- | --- |
| GET | `/kirish` | Telefon orqali kirish |
| GET | `/kirish/email` | Mavjud email/parol orqali kirish |
| GET | `/telefonni-tasdiqlash` | Joriy hisob telefonini tasdiqlash |
| POST | `/kirish/telefon` | Kod yuborishni so'rash |
| GET / POST | `/telefon/kod` | Kod formasi / tasdiqlash |
| POST | `/telefon/kod/qayta` | Shu raqamga kodni qayta so'rash |
| GET / POST | `/sotuvchi/sozlash` | Birinchi sotuvchi profili |
| GET / PATCH | `/kabinet/profil` | Profilni tahrirlash |
| GET | `/kabinet` | E'lonlar, holat filtrlari va qidirish |

Auth sahifalari umumiy keshga kiritilmaydi; web yozuvlar CSRF bilan himoyalangan. Bu SMS oqimi web sessiyasi uchun. Email/parol orqali Sanctum API tokenlari alohida ishlashda davom etadi.

## Sotuvchi va e'lonlar

Shaxsiy sotuvchi ism va shaharni kiritadi. Do'kon egasi qo'shimcha biznes nomi, turi, yo'nalishlari, manzili va tavsifini kiritadi. Telefon tasdiqlangan hisobdan olinadi. Do'kon odatdagi moderatsiyaga `pending` bo'lib tushadi; profilni qayta saqlash takroriy do'kon yaratmaydi va tasdiqlangan holatni o'z-o'zidan bermaydi.

E'lon yaratish, tahrirlash, holat o'zgartirish, surat yuklash, CSV importi hamda biznes yozuvlari serverda telefon va sotuvchi profili talablari bilan himoyalangan. Shaxsiy e'lon uchun tasdiqlangan hisob telefoni, do'kon e'loni uchun imkon bo'lsa tasdiqlangan egasining biznes telefoni ishlatiladi. Mijoz yuborgan boshqa raqam tasdiqlangan aloqa o'rniga qo'yilmaydi.

Kabinet faqat o'z e'lonlarini va haqiqiy hisoblagichlarni ko'rsatadi. E'lon qoralama, tekshiruvda, faol, sotilgan yoki arxiv holatida boshqariladi. Rasm yuklash va avvalgi arenda/ombor vakolatlari saqlangan. Admin moderatsiyasi sotuvchi profilini talab qilmaydi.

## Tekshirish

`PhoneOtpTest`, `PhonePostingFlowTest`, `SellerOnboardingTest`, `AuthApiTest` va `BusinessCabinetTest` kodning muddati, urinish/yuborish cheklovlari, sessiyalararo himoya, provayder xatolari, cheklovni API/CSV orqali chetlab o'tish va do'kon egaligini tekshiradi. Haqiqiy SMS yubormasdan test HTTP javoblari ishlatiladi. Real SMS sinovi alohida, ruxsat berilgan raqamga amalga oshiriladi.
