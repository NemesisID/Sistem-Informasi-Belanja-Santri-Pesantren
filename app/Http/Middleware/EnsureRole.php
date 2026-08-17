<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Cek role user dari token. Pemakaian: ->middleware('role:admin,staff')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            throw new HttpException(403, 'Anda tidak punya akses ke resource ini.');
        }

        if (! $user->is_active) {
            throw new HttpException(403, 'Akun Anda dinonaktifkan.');
        }

        return $next($request);
    }
}
