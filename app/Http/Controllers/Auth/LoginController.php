<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $login = trim($request->string('login')->toString());
        $field = str_contains($login, '@') ? 'email' : 'phone';
        $value = $field === 'email' ? mb_strtolower($login) : (Phone::normalize($login) ?? $login);

        $attempt = Auth::attempt(
            [$field => $value, 'password' => $request->string('password')->toString(), 'is_active' => true],
            $request->boolean('remember'),
        );

        if (! $attempt) {
            // Same message for unknown user / wrong password / inactive account: no account enumeration.
            Log::warning('auth.login_failed', ['ip' => $request->ip(), 'identifier_hash' => substr(hash('sha256', $value), 0, 12)]);

            throw ValidationException::withMessages(['login' => __('بيانات الدخول غير صحيحة.')]);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        Log::info('auth.login', ['user_id' => $user->id, 'role' => $user->role->value]);

        return redirect()->intended(route($user->role->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
