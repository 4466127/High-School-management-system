# تغییرات

## [مرحله‌ی ۲] — ۲۵ عقرب ۱۴۰۵ (2026-11-16) — تأیید شد

### افزوده شد
- پروژه‌ی Laravel 13.42 در ریشه‌ی مخزن (بدون Node/Vite)؛ زبان `fa`، منطقه‌ی زمانی `Asia/Kabul`، پایگاه داده‌ی `maktab` روی MariaDB 10.4.32.
- جدول‌ها: `users` (نام کاربری، بدون ایمیل)، `sessions`، `cache`، `roles`، `permissions`، `role_user`، `permission_role`، `permission_user`، `activity_logs`، `settings`.
- ورود با نام کاربری و رمز، «مرا به خاطر بسپار»، محدودیت ۵ تلاش در دقیقه، بیرون کردن کاربر غیرفعال، تغییر رمز اجباری در اولین ورود.
- شش نقش و ۲۲ مجوز مطابق ARCHITECTURE.md؛ Gate برای هر مجوز؛ `UserPolicy`؛ مجوز اضافی برای یک کاربر.
- مدیریت کاربران (فقط مدیر سیستم): فهرست با فیلتر، ساخت، ویرایش نقش‌ها، فعال/غیرفعال، نو کردن رمز.
- ثبت رویدادها (`ActivityLogger`) با مقدار قبلی/جدید، بدون رمز؛ فقط افزودن. صفحه‌ی مشاهده با فیلتر تاریخ شمسی.
- قالب RTL: منوی کنار براساس مجوز، قالب ورود، قالب چاپ A4، صفحه‌های خطای فارسی (۴۰۳، ۴۰۴، ۴۱۹، ۴۲۹، ۵۰۰، ۵۰۳).
- تاریخ شمسی با افزونه‌ی intl (`App\Support\JalaliDate`)، جزء `<x-jalali-date>`، قاعده‌ی `ValidJalaliDate`، `ConvertsJalaliDates`، دستورهای Blade `@jdate` و `@fa`.
- تبدیل خودکار ارقام فارسی/عربی همه‌ی ورودی‌ها به لاتین (`NormalizeDigits`).
- سرصفحه‌های امنیتی و CSP (فقط منابع محلی، بدون JS داخل صفحه).
- `.htaccess` ریشه: فایل‌های پروژه (`.env`، `vendor`، `storage` …) از راه Apache دیده نمی‌شوند.
- Seederهای قابل اجرای دوباره: نقش‌ها و مجوزها، تنظیمات پیش‌فرض (نمره‌دهی ۴۰٪، فیس خاموش، AFN)، مدیر اول با رمز تصادفی، کاربران آزمایشی فقط در local.
- ۷۲ تست (Unit و Feature) روی پایگاه داده‌ی جدای `maktab_test`؛ مقایسه‌ی روزبه‌روز تاریخ سرور با انتخاب‌گر مرورگر از ۱۳۷۰ تا ۱۴۵۰.
- `pint.json` (پوشه‌ی `references` مستثنا)، `CHANGELOG.md`.

### تغییر کرد
- `assets/` با `git mv` به `public/assets/` رفت (محتوا بدون تغییر). `school.css` تازه اضافه شد.
- `.gitignore` با فایل‌های Laravel ادغام شد؛ `.env.example` برای این پروژه.

### حذف شد (فایل‌های اسکلت Laravel که لازم نبود)
- `package.json`، `vite.config.js`، `resources/js`، `resources/css`، `welcome.blade.php`، migration جدول `jobs`، جدول `password_reset_tokens`، تست‌های نمونه.

### مشکل‌های شناخته‌شده
- اجرای اول Pint (پیش از ساخت `pint.json`) ۲۴۴ فایل PHP در `references/rosariosis/modules` را قالب‌بندی کرد (فاصله‌ها، سبک کد و برخی توضیحات PHPDoc). این پوشه در git نیست و برنامه از آن استفاده نمی‌کند؛ بازگرداندن آن به تصمیم صاحب پروژه بستگی دارد.

## [مرحله‌ی ۰ و ۱] — ۲۴ عقرب ۱۴۰۵ (2026-11-15)
- بررسی محیط و اسناد طراحی: CLAUDE.md، ARCHITECTURE.md، DATABASE.md، ROADMAP.md، API.md، DEVELOPMENT.md (commit `adbf9c8`).
