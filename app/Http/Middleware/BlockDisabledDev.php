<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BlockDisabledDev
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user?->isDev() && ! config('app.dev_tools')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun developer dinonaktifkan.']);
        }

        return $next($request);
    }
}
