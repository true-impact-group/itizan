<?php

namespace App\Http\Controllers\Account;

use App\Actions\Consent\RecordConsent;
use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request, RecordConsent $consents): View
    {
        return view('account.profile.edit', [
            'user' => $request->user(),
            'marketingConsent' => $consents->hasConsent($request->user(), ConsentType::Marketing),
            'cities' => config('itizan.cities'),
        ]);
    }

    public function update(UpdateProfileRequest $request, RecordConsent $consents): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $wantsMarketing = (bool) ($data['marketing_consent'] ?? false);
        unset($data['marketing_consent']);

        $user->update($data);

        // Only a change is recorded, so the consent ledger shows real decisions.
        if ($wantsMarketing !== $consents->hasConsent($user, ConsentType::Marketing)) {
            $consents->handle($user, ConsentType::Marketing, $wantsMarketing);
        }

        $request->session()->put('locale', $user->locale);

        return redirect()->route('account.profile.edit')->with('status', __('تم حفظ بياناتك.'));
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return redirect()->route('account.profile.edit')->with('status', __('تم تغيير كلمة المرور.'));
    }
}
