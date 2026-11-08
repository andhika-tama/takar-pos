<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 60;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Per username and address, so one locked-out name does not block the shop's other Kasir.
        $key = Str::lower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            Log::warning('Sign-in throttled', ['username' => $credentials['username'], 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'username' => __('Too many sign-in attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        // An unknown, wrong or deactivated account gets the same answer.
        if (! Auth::attempt([...$credentials, 'active' => true])) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'username' => __('The username or password is wrong.'),
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // A Kasir always starts at the till: on a shared device the intended URL may be an Owner page left by someone else.
        if ($request->user()->role === Role::Kasir) {
            $request->session()->forget('url.intended');

            return redirect()->route('pos');
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
