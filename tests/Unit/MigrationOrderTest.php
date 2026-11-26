<?php

namespace Tests\Unit;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * نصب تازه migrationها را به ترتیب نام فایل اجرا می‌کند. هر جدولی که یک migration به آن اشاره می‌کند
 * (کلید خارجی، Schema::table، DB::table) باید در همان فایل یا فایل پیش از آن ساخته شده باشد.
 * (مرحله‌ی ۱۱: فایل‌های staff و students نام دیرتر داشتند و نصب تازه خطا می‌داد.)
 */
class MigrationOrderTest extends TestCase
{
    public function test_every_referenced_table_is_created_earlier(): void
    {
        $files = glob(dirname(__DIR__, 2).'/database/migrations/*.php');
        sort($files, SORT_STRING);

        $created = [];
        $problems = [];
        foreach ($files as $file) {
            $code = (string) file_get_contents($file);
            $up = Str::before($code, 'public function down');

            preg_match_all("/Schema::create\\('(\\w+)'/", $up, $m);
            $own = $m[1];

            $refs = [];
            preg_match_all("/constrained\\('(\\w+)'\\)/", $up, $m);
            $refs = array_merge($refs, $m[1]);
            preg_match_all("/foreignId\\('(\\w+)_id'\\)(?:->\\w+\\([^)]*\\))*?->constrained\\(\\)/", $up, $m);
            $refs = array_merge($refs, array_map(fn ($n) => Str::plural($n), $m[1]));
            preg_match_all("/(?:Schema|DB)::table\\('(\\w+)'/", $up, $m);
            $refs = array_merge($refs, $m[1]);

            foreach (array_unique($refs) as $table) {
                if (! in_array($table, $created, true) && ! in_array($table, $own, true)) {
                    $problems[] = basename($file).' → '.$table;
                }
            }
            $created = array_merge($created, $own);
        }

        $this->assertSame([], $problems, 'Tables used before they are created (fresh install would fail).');
    }
}
