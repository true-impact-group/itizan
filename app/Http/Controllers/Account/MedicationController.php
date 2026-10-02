<?php

namespace App\Http\Controllers\Account;

use App\Actions\Medication\LogDose;
use App\Enums\DoseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\LogDoseRequest;
use App\Models\Prescription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The client can read prescriptions and record doses. Nothing here changes a prescription. */
class MedicationController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.medications.index', [
            'hasSubscription' => $request->user()->hasActiveSubscription(),
            'prescriptions' => Prescription::query()
                ->with('practitioner.user')
                ->where('user_id', $request->user()->id)
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function show(Prescription $prescription): View
    {
        $this->authorize('view', $prescription);
        abort_unless($prescription->user_id === request()->user()->id, 403);

        $prescription->load('practitioner.user');

        return view('account.medications.show', [
            'hasSubscription' => request()->user()->hasActiveSubscription(),
            'prescription' => $prescription,
            'logs' => $prescription->doseLogs()->latest('scheduled_for')->limit(20)->get(),
        ]);
    }

    public function logDose(LogDoseRequest $request, Prescription $prescription, LogDose $log): RedirectResponse
    {
        $result = $log->handle(
            $request->user(),
            $prescription,
            DoseStatus::from($request->string('status')->toString()),
            $request->input('answers', []),
            $request->input('note'),
        );

        if ($result->heldForDoctor) {
            return redirect()->route('account.medications.show', $prescription)
                ->with('warning', __('إجابتك تحتاج إلى مراجعة الطبيب قبل أخذ الجرعة. لا تأخذ الجرعة الآن، وقد أرسلنا طلبًا عاجلًا للطبيب.'));
        }

        return redirect()->route('account.medications.show', $prescription)->with('status', __('تم تسجيل الجرعة.'));
    }
}
