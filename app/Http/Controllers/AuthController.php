<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->isDealer() ? 'portal.index' : 'dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['is_active'] = true;

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (Auth::user()->isDev() && ! config('app.dev_tools')) {
                Auth::logout();

                return back()->withErrors(['email' => 'Akun developer dinonaktifkan.'])->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended(
                Auth::user()->isDealer() ? route('portal.index') : route('dashboard')
            );
        }

        return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
    }

    public function demoLogin(string $account)
    {
        abort_unless(config('app.demo_login'), 404);

        $accounts = [
            'admin' => 'admin@star.test',
            'eo' => 'eo@star.test',
            'finance' => 'finance@star.test',
            'pm' => 'pm@star.test',
            'field' => 'field@star.test',
            'pic' => 'pic@star.test',
            'reviewer' => 'reviewer@star.test',
            'dealer-jkt' => 'dealer@star.test',
            'dealer-bdg' => 'dealer.bdg@star.test',
            'dealer-sby' => 'dealer.sby@star.test',
            'dealer-viewer' => 'dealer.viewer@star.test',
        ];

        abort_unless(isset($accounts[$account]), 404);

        $user = \App\Models\User::where('email', $accounts[$account])->where('is_active', true)->firstOrFail();

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route($user->isDealer() ? 'portal.index' : 'dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
