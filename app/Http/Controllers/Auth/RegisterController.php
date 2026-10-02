<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, RegisterClient $register): RedirectResponse
    {
        $user = $register->handle($request->registrationData());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))
            ->with('status', __('أهلًا بك في اتزان. خطوتك الأولى تبدأ باستشارة، وفريقنا يرشدك إلى الخطوة التالية.'));
    }
}
