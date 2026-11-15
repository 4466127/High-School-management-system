# راهنمای توسعه و اجرا

## ۱. محیط تأییدشده (۲۴ عقرب ۱۴۰۵ / 2026-11-15)

| مورد | مقدار | وضعیت |
|---|---|---|
| سیستم عامل | Windows 11 Pro | ✔ |
| PHP خط فرمان | 8.4.27 (ZTS, x64) — `C:\xampp\php\php.exe` | ✔ |
| PHP در Apache | 8.4.27 (Apache 2 Handler، همان `C:\xampp\php`) | ✔ با CLI یکی است |
| `php.ini` | `C:\xampp\php\php.ini` (مشترک بین CLI و Apache) | ✔ |
| Apache | 2.4.58، `mod_rewrite` فعال | ✔ |
| MariaDB | 10.4.32 | ✔ (در مرحله‌ی ۲ با migration تأیید می‌شود) |
| Composer | 2.11.2 — `C:\composer\composer.bat` | ✔ |
| Git | 2.49.0 | ✔ |
| Node / npm | نصب نیست | لازم نیست |
| Laravel 13 | نیاز: PHP `^8.3` | ✔ |

افزونه‌های PHP بررسی‌شده و فعال: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`, `intl`, `gd`, `bcmath`, `exif`, `sodium`.

تنظیمات `php.ini`: `memory_limit=512M`، `upload_max_filesize=40M`، `post_max_size=40M`، `max_input_vars=5000`، `date.timezone=Asia/Kabul`.

پوشه‌ی `C:\xampp\php82` نسخه‌ی قبلی PHP است و استفاده نمی‌شود.

## ۲. ساختار فعلی مخزن

```
CLAUDE.md, ARCHITECTURE.md, DATABASE.md, ROADMAP.md, API.md, DEVELOPMENT.md
assets/            کتابخانه‌های محلی (در مرحله‌ی ۲ به public/assets می‌رود)
  vendor/bootstrap     Bootstrap 5.3.6 (CSS راست‌به‌چپ + JS)
  vendor/fontawesome   Font Awesome Free 6.7.2
  fonts/vazirmatn      وزیرمتن (Regular, Medium, Bold)
  css/app.css, js/app.js, js/print.js, icons/   از پروژه‌ی قبلی؛ پایه‌ی ظاهر، در مرحله‌ی ۲ بازنویسی می‌شود
references/        RosarioSIS — فقط مرجع، در git نیست
```

## ۳. نصب برای توسعه (بعد از مرحله‌ی ۲)

```
git clone https://github.com/4466127/High-School-management-system.git
cd High-School-management-system
composer install
copy .env.example .env
php artisan key:generate
```

در phpMyAdmin یا خط فرمان پایگاه داده بسازید:

```sql
CREATE DATABASE maktab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

در `.env`:

```
APP_NAME="سیستم مدیریت مکتب"
APP_LOCALE=fa
APP_TIMEZONE=Asia/Kabul
DB_CONNECTION=mariadb
DB_DATABASE=maktab
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
```

سپس:

```
php artisan migrate --seed
php artisan serve
```

و در مرورگر `http://127.0.0.1:8000`.

## ۴. کار روزانه

| کار | دستور |
|---|---|
| اجرای تست‌ها | `php artisan test` |
| قالب‌بندی کد | `vendor\bin\pint` |
| ساخت دوباره‌ی پایگاه داده‌ی توسعه | `php artisan migrate:fresh --seed` |
| فهرست مسیرها | `php artisan route:list` |

- تست‌ها روی پایگاه داده‌ی جدا (`maktab_test`) اجرا می‌شوند تا رفتار با MariaDB واقعی یکی باشد.
- `migrate:fresh` همه‌ی داده را پاک می‌کند؛ **هرگز روی سرور مکتب اجرا نشود.**

## ۵. Git و GitHub
- مخزن: `https://github.com/4466127/High-School-management-system.git`، شاخه‌ی `main`.
- هر مرحله‌ی ROADMAP یک یا چند commit با پیام روشن؛ بعد از تأیید مرحله push می‌شود.
- در git **نمی‌رود:** `.env`، `vendor/`، `storage/` (فایل‌های آپلودی و log)، نسخه‌های پشتیبان، `references/`، فایل‌های `zip`.
- داده‌ی واقعی دانش‌آموزان هیچ‌وقت وارد مخزن نشود؛ Seederها فقط داده‌ی ساختگی دارند.

## ۶. راه‌اندازی در شبکه‌ی داخلی مکتب (مرحله‌ی ۱۰)
این کارها تنظیمات Windows و XAMPP را تغییر می‌دهد و فقط با تأیید انجام می‌شود.

1. به کامپیوتر سرور IP ثابت بدهید (مثلاً `192.168.1.100`) — در روتر یا تنظیمات شبکه.
2. در Apache یک VirtualHost بسازید که `DocumentRoot` آن پوشه‌ی `public` پروژه باشد:
   ```apache
   <VirtualHost *:80>
       DocumentRoot "C:/xampp/htdocs/High school management system/public"
       <Directory "C:/xampp/htdocs/High school management system/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
3. در Windows Firewall پورت 80 را فقط برای شبکه‌ی خصوصی (Private) باز کنید.
4. برای `root` در MariaDB رمز بگذارید و یک کاربر جدا برای برنامه بسازید؛ phpMyAdmin و صفحه‌ی XAMPP فقط از خود سرور باز شود.
5. در `.env`: `APP_ENV=production`، `APP_DEBUG=false`، `APP_URL=http://192.168.1.100`.
6. `php artisan config:cache`، `php artisan route:cache`، `php artisan view:cache`.
7. Apache و MySQL را به‌صورت سرویس Windows نصب کنید تا با روشن‌شدن کامپیوتر خودکار شروع شوند.
8. کاربران در مرورگر موبایل یا کامپیوتر `http://192.168.1.100` را باز می‌کنند.

## ۷. پشتیبان‌گیری و بازیابی (مرحله‌ی ۱۰)
- `php artisan school:backup --to="E:\maktab-backup"` یک فایل فشرده می‌سازد: خروجی کامل پایگاه داده (`mysqldump`) + پوشه‌ی `storage/app` (عکس‌ها، اسناد، لوگو).
- همین کار از صفحه‌ی «تنظیمات ← پشتیبان» هم ممکن است.
- پشتیبان خودکار روزانه با Windows Task Scheduler؛ نگهداری ۳۰ نسخه‌ی آخر.
- بازیابی: `php artisan school:restore "E:\maktab-backup\backup-….zip"` — قبل از جایگزینی تأیید می‌خواهد و از وضعیت فعلی یک نسخه می‌گیرد.
- تعویض کامپیوتر سرور: نصب XAMPP با PHP 8.4 ← کپی پروژه ← `composer install` ← `.env` ← بازیابی آخرین پشتیبان.
- توصیه: هر هفته یک نسخه روی فلش جدا که بیرون از مکتب نگهداری شود.

## ۸. تاریخ شمسی
PHP با افزونه‌ی `intl` تبدیل را انجام می‌دهد (تقویم `persian`، محل `fa_AF`). روی همین دستگاه آزمایش شد:

- 2026-11-15 ← ۲۴ عقرب ۱۴۰۵
- ۱ حمل ۱۴۰۵ ← 2026-03-21

نام ماه‌ها در `lang/fa` تعریف می‌شود (حمل، ثور، جوزا، سرطان، اسد، سنبله، میزان، عقرب، قوس، جدی، دلو، حوت) تا وابسته به نسخه‌ی ICU نباشد.
