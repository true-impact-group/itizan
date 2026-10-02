<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Reset links are sent by email, so only accounts that registered an email can use this flow. */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => mb_strtolower($request->string('email')->toString())]);

        // Always the same answer so the form cannot be used to discover registered emails.
        return back()->with('status', __('إذا كان البريد مسجلًا لدينا فسيصلك رابط لإعادة تعيين كلمة المرور.'));
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            [...$data, 'email' => mb_strtolower($data['email'])],
            function ($user) use ($data) {
                $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('رابط إعادة التعيين غير صالح أو منتهي.')]);
        }

        return redirect()->route('login')->with('status', __('تم تغيير كلمة المرور. يمكنك تسجيل الدخول الآن.'));
    }
}
