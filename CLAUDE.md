# CLAUDE.md — دستورالعمل کار روی این پروژه

## پروژه چیست
سیستم مدیریت مکتب برای مکاتب دولتی و خصوصی افغانستان (کوچک و متوسط).
برنامه‌ی وب در شبکه‌ی داخلی (LAN) است، نه سرویس اینترنتی: یک کامپیوتر در مکتب سرور است و بقیه با مرورگر از طریق Wi-Fi داخلی وصل می‌شوند.

اسناد اصلی (قبل از هر کار بخوان):
- [ARCHITECTURE.md](ARCHITECTURE.md) — معماری، ماژول‌ها، نقش‌ها، منوها و صفحات
- [DATABASE.md](DATABASE.md) — جدول‌ها، روابط، قواعد داده
- [ROADMAP.md](ROADMAP.md) — مراحل، وضعیت فعلی، تصمیم‌های باز
- [DEVELOPMENT.md](DEVELOPMENT.md) — محیط، نصب، اجرا، تست، پشتیبان‌گیری
- [API.md](API.md) — مسیرهای داخلی JSON و خروجی CSV

## وضعیت فعلی
مرحله‌های ۰ تا ۶ آزمایش شده (بهبودهای خواسته‌شده‌ی صاحب پروژه هم انجام شد). مرحله‌های ۷ و ۷+ (مالی، مشروطی، درجه‌ی حرفی) تأیید شد. مرحله‌ی ۸ (کورس‌ها) تأیید شد. مرحله‌ی ۹ (داشبورد و گزارش‌ها) ساخته، آزمایش و push شده و منتظر آزمایش صاحب پروژه است. بعدی و آخرین: مرحله‌ی ۱۰ (پشتیبان، پایگاه داده‌ی تازه برای مکتب، راه‌اندازی در شبکه). راهنمای نصب از صفر: README.md.
وضعیت دقیق همیشه در ROADMAP.md است؛ تغییرات در CHANGELOG.md.

## قواعد سخت (استثنا ندارد)
1. **کاملاً محلی:** هیچ CDN، Google Fonts، API آنلاین یا ارسال داده به بیرون. همه‌ی CSS، JS، فونت و تصویر داخل `public/assets` است.
2. **زبان رابط:** فارسی دری، راست‌به‌چپ. همه‌ی متن‌ها در `lang/fa` باشد، نه داخل Blade یا کنترلر. واژگان مکاتب افغانستان: صنف، بخش، مضمون، حاضری، فیس، کارنامه، نمبر اساس، اوسط، کامیاب، ناکام.
3. **تاریخ:** در پایگاه داده میلادی (`DATE`)، در رابط هجری شمسی با نام ماه‌های افغانستان (حمل، ثور، …، حوت). تبدیل فقط از راه `App\Support\JalaliDate`. منطقه‌ی زمانی `Asia/Kabul`.
4. **پول:** افغانی (AFN)، نوع `DECIMAL(12,2)`. هرگز `float`.
5. **حذف:** پرداخت، نمره، تراکنش مالی و پرونده‌ی دانش‌آموز حذف فیزیکی نمی‌شود. تراکنش مالی «باطل» می‌شود (با دلیل و نام کاربر)، پرونده غیرفعال می‌شود. هر تغییر حساس در `activity_logs` ثبت می‌شود.
6. **محیط:** بدون تأیید صاحب پروژه، تنظیمات XAMPP، PHP، Apache، MySQL یا Windows را تغییر نده.
7. **مرحله‌به‌مرحله:** هر مرحله‌ی ROADMAP: طرح فنی ← کد ← migration ← تست ← به‌روزرسانی اسناد. تا مرحله‌ی جاری درست کار نکرده و تأیید نشده، سراغ بعدی نرو.
8. **داده‌ی واقعی:** هیچ اطلاعات واقعی دانش‌آموز، فایل `.env`، نسخه‌ی پشتیبان یا فایل آپلودشده وارد git نشود. داده‌ی Seed فقط ساختگی.
9. **فایل zip کنار پروژه را باز نکن.**
10. **RosarioSIS** (`references/rosariosis/`، خارج از git): فقط برای شناخت گردش‌کار. کد یا ساختار جدولش را کپی نکن (مجوز آن GPL v2 است). هیچ ابزاری (Pint، جست‌وجو و جایگزینی) روی این پوشه اجرا نشود؛ `pint.json` آن را مستثنا کرده است.
11. **پایگاه داده:** روی `maktab` هرگز `migrate:fresh`، `db:wipe`، `migrate:reset` یا truncate اجرا نشود. تست‌ها فقط روی `maktab_test`.

## فناوری
- Laravel 13، PHP 8.4، MariaDB 10.4 (XAMPP)، Apache 2.4
- Blade + Bootstrap 5.3 RTL + Font Awesome 6 + فونت وزیرمتن + JavaScript ساده
- بدون Node/npm/Vite (روی این دستگاه Node نصب نیست و لازم هم نیست). بدون React/Vue/Livewire/Docker.
- بسته‌ی اضافی Composer فقط با دلیل روشن. موارد زیر عمداً بدون بسته ساخته می‌شوند:
  - تاریخ شمسی: افزونه‌ی `intl` خود PHP
  - نقش و مجوز: Gate و Policy خود Laravel با جدول‌های ساده
  - چاپ: صفحه‌ی چاپ مرورگر با CSS چاپ (PDF ساز نداریم)
  - خروجی: CSV با BOM (در Excel درست باز می‌شود)

## قراردادهای کد
- کد، نام جدول‌ها، ستون‌ها و کلاس‌ها انگلیسی؛ متن‌های کاربر فارسی در `lang/fa`.
- کنترلر نازک؛ اعتبارسنجی در Form Request؛ منطق چندمرحله‌ای (ارتقای صنف، محاسبه‌ی نتیجه، فیس، دفتر مالی کورس، پشتیبان) در `app/Services`.
- مقدارهای ثابت (وضعیت‌ها، نوع‌ها) با PHP Enum در `app/Enums`.
- کنترل دسترسی: `can:` روی مسیرها + Policy برای محدوده‌ی معلم (فقط صنف و مضمون خودش).
- هر نوشتن چندجدولی داخل `DB::transaction`.
- ارقام فارسی/عربی ورودی به لاتین تبدیل می‌شوند؛ نمایش با ارقام فارسی.
- JavaScript داخل صفحه (inline) ننویس؛ فایل جدا در `public/assets/js`.
- هر ماژول تست Feature دارد؛ محاسبات (نتیجه، مانده‌ی مالی، تبدیل تاریخ) تست Unit.

## دستورها
```
php artisan migrate             # جدول‌های تازه
php artisan db:seed             # نقش‌ها، تنظیمات، مدیر اول؛ کاربران demo.* فقط در local (اجرای دوباره امن)
php artisan test                # تست‌ها روی maktab_test
php artisan serve               # اجرای توسعه (یا Apache: http://localhost/High%20school%20management%20system/)
vendor\bin\pint                 # قالب‌بندی کد (با pint.json)
php artisan school:backup       # نسخه‌ی پشتیبان (مرحله‌ی دهم)
```

## نکته‌های پیاده‌سازی مرحله‌ی ۲
- مجوز تازه: کلید در `config/permissions.php` + برچسب در `lang/fa/permissions.php` + `php artisan db:seed --class=RolePermissionSeeder`.
- منو: `config/menu.php`؛ `route => null` یعنی «به‌زودی».
- `User::hasPermission()` مدیر سیستم را همیشه مجاز می‌داند؛ Gate::before نداریم تا Policyها (مثلاً «خودت را غیرفعال نکن») برای مدیر هم کار کنند.
- `app.css` و `app.js` از پروژه‌ی قبلی بدون تغییر‌اند؛ سبک تازه در `school.css`، JS تازه در `school.js`. انتخاب‌گر تاریخ: `input[data-jdate]`.

## نکته‌های پیاده‌سازی مرحله‌ی ۳
- تغییر پرونده‌ها فقط از راه `StudentService` / `StaffService` (transaction + ثبت رویداد با `ModelChanges::capture` پس از fill و پیش از save).
- فرم‌ها با `x-form.input/select/textarea` و `x-jalali-date`؛ چند فرم هم‌نام در یک صفحه (پنجره‌ها) با `x-modal-form` و `:use-old="old('_modal') === '<id>'"`.
- فایل خصوصی: `PrivateFiles::store/inline/download` روی دیسک `local` (`storage/app/private`). هرگز `storage:link`.
- در تست‌ها رکورد را با نام پیدا نکنید وقتی factory نام تصادفی می‌دهد (`latest('id')`)؛ پیام موفقیت flash در درخواست بعدی دیده می‌شود.

## نکته‌های پیاده‌سازی مرحله‌ی ۴
- صنف/بخش دانش‌آموز فقط در `enrollments` (یک سطر برای هر سال). حاضری و نمرات به `enrollment_id` وصل شوند، نه `student_id`.
- تغییر شمولیت فقط از راه `EnrollmentService` (enroll، changeSection، leave، promote، renumber). سطر سال‌های قبل ویرایش نمی‌شود.
- سال جاری: `AcademicYear::current()` (cache در static؛ پس از تغییر `forgetCurrent()`؛ در TestCase خودکار).
- محدوده‌ی معلم: `$user->taughtSectionIds()` (نگران صنف + معلم مضمون در سال جاری). دیدن همه‌ی دانش‌آموزان: `students.view_all` یا `students.manage`.
- `Section::ordered()` ستون‌ها را فقط اگر خالی باشد `sections.*` می‌گذارد (تا `withCount` از بین نرود).
- Form Requestی که مسیرش با middleware مجوز بسته نیست، باید `authorize()` داشته باشد (مجوز پیش از اعتبارسنجی).
- تست‌ها: `makeYear()` و `makeSection()` در `Tests\TestCase`.

## نکته‌های پیاده‌سازی مرحله‌ی ۵
- ثبت و اصلاح حاضری فقط از راه `AttendanceService::saveSection` / `saveStaff` (transaction، `createOrFirst` روی کلید یکتا، ثبت رویداد).
- مجوز: `Gate::authorize('viewSection'|'record'|'manageStaff', [AttendanceSession::class, …])` (`AttendancePolicy`، ثبت‌شده در `AppServiceProvider`). محدوده‌ی همه یا بخش‌های خود: `AttendanceService::scopedSectionIds()`.
- «امروز» همیشه `AttendanceService::today()` (کابل). تاریخ در آدرس: `?date=Y-m-d` یا شمسی؛ ماه: `?month=1405-08` (`ResolvesDates`).
- گزارش چاپی: همان view با `?print=1` (`@extends($print ? 'layouts.print' : 'layouts.app')`). CSV: `App\Support\Csv::download`.
- در تست‌ها تاریخ را با `Carbon::setTestNow` و `CarbonImmutable::setTestNow` ثابت کنید.

## نکته‌های پیاده‌سازی مرحله‌ی ۶
- فورمول فقط در `ResultCalculator` (بدون پایگاه داده). نمره و نتیجه فقط از راه `MarkService` و `ResultService`.
- نمایش نتیجه‌ی نهایی و کارنامه همیشه از `ResultService::reportCards()`: نهایی‌شده ← `final_snapshot`، در غیر آن زنده با `provisional`. ستون‌های `final_*` فقط با `finalize()`/`unfinalize()` نوشته می‌شوند.
- مجوز: `Gate::authorize('enterMarks', [$exam, $section, $subject])`، `viewMarks`/`viewResults` با `[Exam::class, …]`، `manage` با `Exam::class`.
- در یک فایل Blade، `@php(...)` یک‌خطی را پیش از بلوک `@php … @endphp` ننویسید (Blade کل متن میان آن دو را یک بلوک می‌خواند).
- تست‌های امتحانات از `Tests\Feature\Exams\ExamTestCase` (بخش، دو مضمون، معلم، سه دانش‌آموز، دو امتحان).

## نکته‌های پیاده‌سازی مرحله‌ی ۷
- هر عملیات مالی فقط از راه `FinanceService` (transaction + قفل + ثبت رویداد). حساب داخلی به پول (سنت، int) با `Money::toCents/fromCents`؛ هرگز float.
- مبلغ ورودی کاربر: قاعده‌ی `ValidAmount` + `FinanceService::parseAmount`. نمایش: `Money::format`.
- `NormalizeInput` ارقام فارسی ورودی را لاتین می‌کند؛ متنی که با ارقام فارسی ذخیره می‌شود (مثل `period_label`) را پیش از مقایسه با `Digits::toPersian` برگردانید.
- مسیرهای اختیاری با `->middleware('feature:fees')` / `feature:ledger`؛ زیرمنو با `'feature' => 'fees'`.
- مانده‌ی بل ذخیره نمی‌شود: `StudentInvoice::withPaid()` (رسید باطل حساب نمی‌شود).

## نکته‌های پیاده‌سازی مرحله‌ی ۸
- کورس، گروه، اشتراک‌کننده و حاضری فقط از راه `CourseService`؛ دفتر مالی فقط از راه `CourseLedgerService` (قفل کورس، بررسی مانده). جمع‌ها فقط `CourseLedgerCalculator` (پول به سنت).
- مجوز: `Gate::authorize('view'|'manage'|'ledger', …)` و `viewAttendance`/`recordAttendance` با `[Course::class, $group, $date]` (`CoursePolicy`؛ `attendanceBlock()` دلیل بسته بودن را می‌دهد).
- حاضری و نمره‌ی مکتب به `enrollment_id` وصل است؛ حاضری کورس به `course_participant_id` (کورس وابسته به سال تحصیلی نیست).
- نام مسیرها: `courses.list.*`، `courses.attendance.*`، `courses.ledger.*` (زیرمنوی جدا).

## نکته‌های پیاده‌سازی مرحله‌ی ۹
- داشبورد فقط از `DashboardService::for($user)`؛ هر کارت null یعنی برای این کاربر نیست. گزارش‌ها فقط خواندن، از `ReportService`؛ محدوده‌ی بخش‌ها همیشه `ResultService::visibleSections`.
- گزارش تازه: متد در `ReportService` + دو مسیر `reports.<name>.index`/`export` + زیرمنو در `config/menu.php` + view با `reports._filters` و `?print=1`.

## نکته‌های امتحان مشروطی و درجه‌ی حرفی
- درجه فقط `ResultCalculator::grade()` (a/b/c/d؛ برچسب در `exams.grades`). حدها `grade_a_min`/`grade_b_min`/`grade_c_min` در تنظیمات و در `final_snapshot.settings`. نسخه‌ی ثابت قدیمی بدون `grade` ← `ResultService::reportCards` آن را حساب می‌کند. `class_rank` دیگر نوشته نمی‌شود.
- مشروطی فقط از راه `ReexamService` (sheet، save، finalize، unfinalize)؛ محاسبه `ResultCalculator::reexamResult` با قاعده‌های نسخه‌ی ثابت (`fromSnapshot`). نتیجه‌ی اصلی در `final_snapshot.reexam.original`. `ResultService::unfinalize` تا مشروطی نهایی است خطا می‌دهد.

## نکته‌های رابط (پس از مرحله‌ی ۶)
- پیام کاربر فقط با `->with('success'|'error'|'warning', …)`؛ `layouts/partials/alerts` آن را toast می‌کند (خطای اعتبارسنجی هم خودکار toast سرخ می‌شود). alert تازه‌ی Bootstrap برای flash ننویسید.
- منو: هر ماژول چندصفحه‌ای در `config/menu.php` با `children`؛ نام مسیرها `module.page.index` تا فقط همان زیرمنو فعال شود (`Menu::routePattern`).
- ورود گروهی: ستون‌ها در `lang/fa/import.php` (کلید ← [سربرگ، اجباری، مثال، توضیح])؛ بررسی و ذخیره در `ImportService`؛ فایل xlsx با `App\Support\Xlsx` (بدون بسته).

## Git و GitHub
- بعد از تمام شدن هر مرحله: commit و push به `origin main` (صاحب پروژه این را خواسته است).
- commitها فقط به نام صاحب پروژه (`4466127 <4466127@users.noreply.github.com>`). **هیچ خط `Co-Authored-By` یا نام Claude/AI در پیام commit نباشد.**
- force-push و بازنویسی تاریخچه فقط با درخواست صریح صاحب پروژه.

## ارتباط با صاحب پروژه
به فارسی دری جواب بده. قبل از هر کار برگشت‌ناپذیر (حذف فایل، تغییر محیط) تأیید بگیر.
