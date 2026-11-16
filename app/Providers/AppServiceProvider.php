<?php

namespace App\Providers;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Exam;
use App\Models\User;
use App\Policies\AttendancePolicy;
use App\Policies\CoursePolicy;
use App\Policies\ExamPolicy;
use App\Support\Menu;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // هر مجوز config/permissions.php یک Gate است: @can('students.manage')، ->middleware('can:students.manage')
        foreach (array_keys(config('permissions.permissions', [])) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // حاضری: Gate::authorize('record', [AttendanceSession::class, $section, $date])
        Gate::policy(AttendanceSession::class, AttendancePolicy::class);
        // امتحانات: Gate::authorize('enterMarks', [$exam, $section, $subject])، Gate::authorize('viewResults', [Exam::class, $section])
        Gate::policy(Exam::class, ExamPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);

        // شناسه‌ی همه‌ی مدل‌ها در آدرس فقط عدد است (تا students/create با students/{student} قاطی نشود)
        foreach (['student', 'staff', 'user', 'guardian', 'document', 'history', 'section', 'year', 'term', 'grade', 'subject', 'exam', 'type', 'enrollment', 'feeType', 'invoice', 'payment', 'transaction', 'category'] as $parameter) {
            Route::pattern($parameter, '[0-9]+');
        }

        Paginator::useBootstrapFive();

        // @jdate($date) ← ۱۴۰۵/۰۸/۲۴ — @jdate($date, 'long') ← ۲۴ عقرب ۱۴۰۵
        Blade::directive('jdate', fn (string $args) => "<?php echo e(\\App\\Support\\JalaliDate::format({$args})); ?>");
        // @fa($number) ← ارقام فارسی
        Blade::directive('fa', fn (string $args) => "<?php echo e(\\App\\Support\\Digits::toPersian({$args})); ?>");

        View::composer('layouts.partials.sidebar', function ($view) {
            $user = auth()->user();
            $view->with('menu', $user ? app(Menu::class)->for($user) : []);
        });
    }
}
