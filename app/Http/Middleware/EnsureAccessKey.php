<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lindungi endpoint diagnostik (dokumentasi API Scramble, halaman /status) di
 * produksi: akses hanya jika menyertakan kunci yang cocok dengan
 * DOCS_ACCESS_KEY lewat query ?key=... atau header X-Access-Key. Tanpa kunci
 * -> 404 agar keberadaan endpoint tidak terbocorkan.
 */
class EnsureAccessKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('scramble.access_key');

        if ($key === '') {
            // Fail closed: tanpa kunci terkonfigurasi, hanya lingkungan lokal yang boleh.
            if (app()->environment('production')) {
                abort(404);
            }

            return $next($request);
        }

        $provided = (string) ($request->query('key') ?? $request->header('X-Access-Key', ''));

        if ($provided !== '' && hash_equals($key, $provided)) {
            return $next($request);
        }

        abort(404);
    }
}
