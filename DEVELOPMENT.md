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

## ۲. ساختار مخزن (بعد از مرحله‌ی ۲)

```
app/Enums/                    Role، Gender، StudentStatus، StaffType، EmploymentStatus
app/Http/Controllers/         Auth/، Settings/، Students/، Staff/، DashboardController
app/Http/Middleware/          NormalizeInput (ارقام و ی/ک)، EnsureUserIsActive، EnsurePasswordIsChanged، SecurityHeaders
app/Http/Requests/            Form Requestها + Concerns/ConvertsJalaliDates
app/Policies/                 UserPolicy، StudentPolicy
app/Services/                 ActivityLogger، StudentService، StaffService
app/Support/                  JalaliDate، Digits، PersianText، Menu، PrivateFiles، ModelChanges
config/afghanistan.php        ۳۴ ولایت، نسبت‌های سرپرست
config/permissions.php        نقش‌ها، مجوزها، مجوزهای هر نقش (منبع اصلی)
config/menu.php               منوی اصلی
config/school.php             مقدار پیش‌فرض تنظیمات مکتب
lang/fa/                      همه‌ی متن‌های رابط
public/assets/                Bootstrap 5.3.6 RTL، Font Awesome 6.7.2، وزیرمتن،
                              app.css / app.js / print.js (از پروژه‌ی قبلی، بدون تغییر)، school.css (افزوده‌ها)
resources/views/              layouts/ (app, auth, print)، components/jalali-date، auth/، settings/، errors/
tests/                        Unit/ و Feature/ — Concerns/UsesTestDatabase
.htaccess                     پنهان کردن فایل‌های ریشه از Apache (پروژه زیر htdocs است)
pint.json                     Pint پوشه‌ی references را دست نزند
references/                   RosarioSIS — فقط مرجع، در git نیست
```

## ۳. نصب برای توسعه

```
git clone https://github.com/4466127/High-School-management-system.git
cd High-School-management-system
composer install
copy .env.example .env
php artisan key:generate
```

در phpMyAdmin یا خط فرمان دو پایگاه داده بسازید (دومی فقط برای تست‌ها):

```sql
CREATE DATABASE maktab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE maktab_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`.env.example` همه‌ی مقدارهای لازم را دارد (`APP_LOCALE=fa`، `DB_CONNECTION=mariadb`، `DB_DATABASE=maktab`، `SESSION_DRIVER=database`، `CACHE_STORE=file`، `QUEUE_CONNECTION=sync`، `MAIL_MAILER=log`). منطقه‌ی زمانی پیش‌فرض در `config/app.php` همان `Asia/Kabul` است.

سپس:

```
php artisan migrate
php artisan db:seed
```

`db:seed` این کارها را می‌کند (اجرای دوباره امن است و چیزی را پاک نمی‌کند):
- نقش‌ها و مجوزها را با `config/permissions.php` هماهنگ می‌کند؛
- تنظیمات پیش‌فرض را فقط برای کلیدهای نبوده می‌نویسد؛
- اگر هیچ «مدیر سیستم» نباشد، کاربر `admin` با **رمز تصادفی** می‌سازد و رمز را یک بار در خروجی نشان می‌دهد (در اولین ورود باید عوض شود)؛
- صنف‌های اول تا دوازدهم و ۱۹ مضمون آغازین (`GradeLevelSeeder`، `SubjectSeeder`؛ فقط وقتی جدول خالی است).
- فقط وقتی `APP_ENV=local` است: شش کاربر آزمایشی `demo.admin`، `demo.principal`، `demo.office`، `demo.teacher`، `demo.accountant`، `demo.course-manager` با رمز `password`؛ دانش‌آموزان و کارکنان ساختگی (`DemoSchoolSeeder`)؛ سال ۱۴۰۵ با بخش‌ها، معلمان مضامین و شمولیت (`DemoAcademicSeeder`)؛ و حاضری ساختگی ۴ هفته‌ی گذشته برای بخش‌ها و کارکنان، بدون امروز (`DemoAttendanceSeeder`). هر کدام فقط وقتی داده‌ی مربوط نیست اجرا می‌شوند. روی سرور مکتب (`APP_ENV=production`) ساخته نمی‌شوند.

### فایل‌های آپلودی
عکس‌ها و اسناد در `storage/app/private/` (بیرون از `public`) ذخیره می‌شوند و فقط از راه برنامه، پس از بررسی مجوز، نمایش داده می‌شوند. `php artisan storage:link` لازم نیست و نباید اجرا شود. این پوشه در git نیست و باید در نسخه‌ی پشتیبان باشد.

### پشتیبان پیش از migration
پیش از migration روی `maktab`، یک نسخه در `backups/` (بیرون از git) بگیرید:
```
mysqldump -u root --single-transaction --routines maktab --result-file=backups\maktab-YYYYMMDD.sql
```

### باز کردن برنامه در توسعه
- با Apache خود XAMPP: `http://localhost/High%20school%20management%20system/` (به `.../public/` می‌رود)
- یا: `php artisan serve` و `http://127.0.0.1:8000`

## ۴. کار روزانه

| کار | دستور |
|---|---|
| اجرای تست‌ها | `php artisan test` |
| قالب‌بندی کد | `vendor\bin\pint` (با `pint.json`؛ پوشه‌ی `references` را دست نمی‌زند) |
| migrationهای تازه | `php artisan migrate` |
| فهرست مسیرها | `php artisan route:list --except-vendor` |

### تست‌ها
- روی MariaDB واقعی و پایگاه داده‌ی جدای `maktab_test` اجرا می‌شوند (`phpunit.xml`).
- `Tests\Concerns\UsesTestDatabase`: اگر نام پایگاه داده به `_test` ختم نشود، تست‌ها اجرا نمی‌شوند. در هر اجرا یک بار `migrate` عادی (نه `migrate:fresh`) و seed نقش‌ها و تنظیمات؛ هر تست داخل transaction اجرا و در پایان برگردانده می‌شود، پس پایگاه داده‌ی تست خالی می‌ماند.
- اگر migration موجودی را عوض کردید (فقط پیش از نصب روی سرور مکتب)، جدول‌های `maktab_test` را دستی پاک کنید تا دوباره ساخته شوند.

### قواعد پایگاه داده
- `migrate:fresh`، `db:wipe` و `migrate:reset` همه‌ی داده را پاک می‌کنند؛ **روی `maktab` اجرا نشوند.** تغییر ساختار همیشه با migration تازه.
- MariaDB 10.4: از `renameColumn` (نیاز به 10.5.2) و ستون `uuid` بومی (نیاز به 10.7) استفاده نکنید. ستون `json` به `LONGTEXT` با `CHECK (json_valid(...))` تبدیل می‌شود و درست کار می‌کند.

### نوشتن صفحه‌ی تازه
- متن‌ها در `lang/fa/*.php`؛ JavaScript داخل صفحه ممنوع (CSP آن را می‌بندد) — فایل جدا در `public/assets/js`.
- مسیر را با `->middleware('can:<مجوز>')` ببندید و مورد منو را در `config/menu.php` با همان مجوز اضافه کنید.
- تاریخ: `<x-jalali-date name="..." />` در فرم، `use ConvertsJalaliDates` + `new ValidJalaliDate` در Form Request، `@jdate($date)` برای نمایش.
- هر تغییر حساس: `app(ActivityLogger::class)->log('module.action', $model, ...)` و برچسب فارسی در `lang/fa/activity.php`.

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
