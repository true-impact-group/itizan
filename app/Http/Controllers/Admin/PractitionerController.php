<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\VerifyPractitionerLicense;
use App\Http\Controllers\Controller;
use App\Models\Practitioner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PractitionerController extends Controller
{
    public function index(): View
    {
        return view('admin.practitioners.index', [
            'practitioners' => Practitioner::query()->with('user')->withCount('availabilities')->orderBy('type')->orderBy('sort_order')->paginate(20),
        ]);
    }

    public function edit(Practitioner $practitioner): View
    {
        return view('admin.practitioners.edit', ['practitioner' => $practitioner->load('user')]);
    }

    public function update(Request $request, Practitioner $practitioner): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['nullable', 'string', 'max:150'],
            'specialty_ar' => ['required', 'string', 'max:150'],
            'specialty_en' => ['nullable', 'string', 'max:150'],
            'license_number' => ['nullable', 'string', 'max:60', 'unique:practitioners,license_number,'.$practitioner->id],
            'license_authority' => ['required', 'string', 'max:60'],
            'license_expires_on' => ['nullable', 'date', 'after:today'],
            'bio_ar' => ['nullable', 'string', 'max:2000'],
            'bio_en' => ['nullable', 'string', 'max:2000'],
            'years_experience' => ['nullable', 'integer', 'between:0,70'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
        ]);

        // Changing the licence number invalidates the earlier verification.
        $licenceChanged = ($data['license_number'] ?? null) !== $practitioner->license_number;

        $practitioner->update([
            ...$data,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'accepts_instant' => $request->boolean('accepts_instant'),
            ...($licenceChanged ? ['license_verified_at' => null, 'license_verified_by' => null] : []),
        ]);

        return redirect()->route('admin.practitioners.index')->with('status', __('تم تحديث بيانات المختص.'));
    }

    public function verify(Request $request, Practitioner $practitioner, VerifyPractitionerLicense $verify): RedirectResponse
    {
        $data = $request->validate(['license_expires_on' => ['nullable', 'date', 'after:today']]);

        $verify->handle($practitioner, $request->user(), $data['license_expires_on'] ?? null);

        return back()->with('status', __('تم توثيق الترخيص. يظهر المختص الآن للعملاء.'));
    }

    public function revoke(Practitioner $practitioner, VerifyPractitionerLicense $verify): RedirectResponse
    {
        $verify->revoke($practitioner);

        return back()->with('status', __('تم سحب التوثيق. لم يعد المختص ظاهرًا للعملاء.'));
    }
}
