<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * صفحه‌بندی یکسان همه‌ی فهرست‌ها (config school.per_page، پیش‌فرض ۲۰ سطر).
 * فهرستی که در حافظه ساخته می‌شود (مثلاً پلان‌ها یا قرضداران) با collection() صفحه‌بندی می‌شود.
 */
final class Paging
{
    public static function size(): int
    {
        return max(5, (int) config('school.per_page', 20));
    }

    /** صفحه‌بندی یک Collection با همان شکل paginate() (links() و withQueryString کار می‌کند) */
    public static function collection(Collection $items, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= self::size();
        $page = max(1, Paginator::resolveCurrentPage());
        $lastPage = max(1, (int) ceil($items->count() / $perPage));
        $page = min($page, $lastPage);

        return (new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]))->withQueryString();
    }
}
