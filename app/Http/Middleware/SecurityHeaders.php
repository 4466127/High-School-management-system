<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * سرصفحه‌های امنیتی. CSP همه‌ی منابع بیرونی را می‌بندد (برنامه کاملاً محلی است)
 * و JavaScript داخل صفحه را اجازه نمی‌دهد؛ JS فقط از فایل‌های public/assets/js.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]));

        return $response;
    }
}
