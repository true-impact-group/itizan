<?php

namespace App\Http\Controllers\Account;

use App\Actions\Medication\ReportSideEffect;
use App\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreSideEffectRequest;
use App\Models\Prescription;
use App\Models\SideEffectReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SideEffectController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('account.side-effects.index', [
            'reports' => SideEffectReport::query()->with('attachments')->where('user_id', $user->id)->latest('occurred_at')->paginate(10),
            'prescriptions' => Prescription::query()->where('user_id', $user->id)->latest('id')->get(),
        ]);
    }

    public function store(StoreSideEffectRequest $request, ReportSideEffect $report): RedirectResponse
    {
        $data = $request->validated();
        $data['severity'] = Severity::from($data['severity']);

        $saved = $report->handle($request->user(), $data, $request->file('files', []));

        $redirect = redirect()->route('account.side-effects.index')->with('status', __('تم تسجيل العَرَض. سيطّلع عليه فريقك الطبي.'));

        // Severe symptoms: point to emergency care immediately.
        return $saved->severity === Severity::Severe
            ? $redirect->with('warning', __('إذا كانت الأعراض شديدة أو مقلقة، اتصل بالهلال الأحمر على :number أو توجه إلى أقرب طوارئ الآن. أرسلنا طلبًا عاجلًا لطبيبك.', ['number' => config('itizan.emergency_number')]))
            : $redirect;
    }
}
