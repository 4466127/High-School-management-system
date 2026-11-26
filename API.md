# API

## خلاصه
نسخه‌ی اول **API عمومی یا مستقل ندارد**. سیستم یک برنامه‌ی Blade است و صفحه‌ها را سرور می‌سازد. اپلیکیشن موبایل، دسترسی از بیرون مکتب و توکن API در برنامه نیست.

این سند فقط دو چیز را ثبت می‌کند: چند مسیر داخلی که JSON برمی‌گردانند، و مسیرهای خروجی CSV. با ساخت هر ماژول، جدول‌های پایین به‌روز می‌شوند.

## قواعد مسیرهای داخلی JSON
- زیر پیشوند `/ajax/…` در `routes/web.php` (نه `routes/api.php`).
- احراز هویت با همان session مرورگر؛ درخواست‌های `POST/PUT/DELETE` سرصفحه‌ی `X-CSRF-TOKEN` لازم دارند.
- همان مجوزها و Policyهای صفحه‌های عادی اعمال می‌شود.
- قالب جواب:

```json
{ "ok": true, "data": { } }
{ "ok": false, "message": "متن خطا به فارسی", "errors": { "field": ["…"] } }
```

- کد وضعیت: `200` موفق، `403` بدون مجوز، `404` نبود، `422` خطای اعتبارسنجی.
- تاریخ در JSON میلادی `YYYY-MM-DD` است و در کنار آن متن شمسی برای نمایش (`date_fa`).

## مسیرهای پیش‌بینی‌شده

| روش | مسیر | کار | مجوز | مرحله |
|---|---|---|---|---|
| GET | `/ajax/students/search?q=` | جست‌وجوی سریع دانش‌آموز (نام، نام پدر، نمبر اساس) | `students.view` | ۳ |
| GET | `/ajax/sections/{section}/students` | فهرست دانش‌آموزان یک بخش | `students.view` | ۴ |
| GET | `/ajax/students/{student}/invoices` | بل‌های باز برای صفحه‌ی ثبت پرداخت | `finance.manage` | ۷ |

ثبت حاضری و نمره با فرم عادی (ارسال کل صفحه) انجام می‌شود، نه با JSON؛ این روش روی شبکه‌ی ضعیف و موبایل مطمئن‌تر است.

## خروجی CSV
هر گزارش یک مسیر `…/export` دارد که فایل CSV با کدگذاری UTF-8 و BOM برمی‌گرداند (در Excel مستقیم باز می‌شود).

| مسیر | محتوا | مرحله |
|---|---|---|
| `/attendance/export?date=` | غیرحاضران، رخصت‌ها، مریض‌ها و تأخیرهای یک روز (بخش‌های در محدوده‌ی کاربر) | ۵ ✅ |
| `/attendance/monthly/export?month=1405-08&section=` | جدول ماهوار یک بخش؛ بدون `section`: خلاصه‌ی صنف‌ها | ۵ ✅ |
| `/attendance/absentees/export?min=&section=` | دانش‌آموزان با غیابت زیاد در سال جاری | ۵ ✅ |
| `/attendance/staff/monthly/export?month=` | خلاصه‌ی ماهوار حاضری کارکنان | ۵ ✅ |
| `/exams/results/export?section=&exam=` | نمره‌های یک امتحان بخش؛ بدون `exam`: نتیجه‌ی نهایی (نمره‌ی نهایی هر مضمون، مجموع، اوسط، نتیجه، درجه) | ۶ ✅ |
| `/reports/students/export?section=&gender=` | فهرست دانش‌آموزان شامل با فیلتر | ۹ ✅ |
| `/reports/attendance/export?section=&from=&to=` | حاضری هر بخش در بازه (شمار وضعیت‌ها، فیصدی) | ۹ ✅ |
| `/reports/results/export?section=` | ناکام، مشروط و محروم با مضامین ناکام | ۹ ✅ |
| `/reports/finance/export?from=&to=` | خلاصه‌ی مالی مکتب و کورس‌ها (جدا) | ۹ ✅ |
| `/finance/debtors/export?section=` | بدهکاران سال جاری | ۷ ✅ |
| `/finance/payments/export?from=&to=&q=` | رسیدها | ۷ ✅ |
| `/finance/ledger/export?type=&category=&from=&to=` | درآمد و مصرف مکتب | ۷ ✅ |
| `/courses/finance/{course}/export?type=&voided=` | دفتر مالی یک کورس (کمک، معاش، مصرف) | ۸ ✅ |
| `/letters/export?direction=outgoing\|incoming&q=&type=&status=&from=&to=` | بایگانی مکاتیب صادره یا وارده | ۱۲ ✅ |
| `/student-transfers/export?direction=&q=&from=&to=` | انتقال‌های میان مکاتب | ۱۳ ✅ |
| `/student-archive/export?status=&year=&q=` | دانش‌آموزان سابق (فارغ، منتقل، ترک، غیرفعال) | ۱۳ ✅ |

**فایل سوابق دانش‌آموز (JSON، مرحله‌ی ۱۳):** `/student-transfers/record/{student}/file` — قالب `maktab-student-record`، نسخه‌ی ۱ (DATABASE.md بخش پرونده‌ی دانش‌آموز). در صفحه‌ی «ثبت انتقال از مکتب دیگر» آپلود می‌شود.

## اگر در آینده API واقعی لازم شد
- مسیرها در `routes/api.php` با نسخه (`/api/v1`)، احراز هویت با Laravel Sanctum.
- از همان Serviceها و Policyها استفاده شود؛ منطق تکرار نشود.
- این تصمیم نیاز به تأیید دارد، چون با اصل «کاملاً محلی» پروژه ارتباط دارد.
