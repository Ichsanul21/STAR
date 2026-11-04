<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevRole
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth()->user()?->isDev(), 403, 'Hanya akun Developer yang dapat mengakses Dev Tools.');

        return $next($request);
    }
}
