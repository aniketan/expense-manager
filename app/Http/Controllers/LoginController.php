<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS_PER_MINUTE = 5;

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get(RequireLogin::SESSION_KEY) === true) {
            return redirect()->route('home');
        }

        return Inertia::render('Auth/Login', [
            'passwordConfigured' => filled(config('auth.login_password')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|string|max:255']);

        $throttleKey = 'login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'password' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        if (! $this->passwordMatches($request->input('password'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages(['password' => 'Incorrect password.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->put(RequireLogin::SESSION_KEY, true);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function passwordMatches(string $given): bool
    {
        $configured = (string) config('auth.login_password');

        // Fail closed: an unset password must never let anyone in.
        if ($configured === '') {
            return false;
        }

        if (password_get_info($configured)['algoName'] !== 'unknown') {
            return Hash::check($given, $configured);
        }

        return hash_equals($configured, $given);
    }
}
