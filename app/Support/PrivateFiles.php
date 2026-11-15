<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * فایل‌های خصوصی (عکس، اسناد) در storage/app/private — بیرون از دسترس مستقیم مرورگر.
 * نمایش فقط از راه کنترلری که پیش از آن مجوز را بررسی کرده است.
 */
final class PrivateFiles
{
    private const DISK = 'local';

    public static function store(UploadedFile $file, string $directory): string
    {
        return Storage::disk(self::DISK)->putFile($directory, $file);
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    /** نمایش داخل مرورگر (عکس) با cache خصوصی کوتاه */
    public static function inline(string $path): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($path, null, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function download(string $path, string $name): StreamedResponse
    {
        return Storage::disk(self::DISK)->download($path, $name);
    }
}
