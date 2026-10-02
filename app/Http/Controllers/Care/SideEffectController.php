<?php

namespace App\Http\Controllers\Care;

use App\Actions\Medication\TriageSideEffect;
use App\Enums\SideEffectStatus;
use App\Http\Controllers\Controller;
use App\Models\SideEffectReport;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SideEffectController extends Controller
{
    public function index(Request $request): View
    {
        $status = SideEffectStatus::tryFrom((string) $request->query('status')) ?? SideEffectStatus::New;

        $reports = SideEffectReport::query()->with(['user', 'attachments'])
            ->where('status', $status->value)
            ->orderByRaw("case severity when 'severe' then 0 when 'moderate' then 1 else 2 end")->oldest('occurred_at')
            ->paginate(15)->withQueryString();

        // Reading symptom text is access to clinical data: one audit entry per page view, listing the report ids.
        Audit::log('side_effect.listed', null, ['report_ids' => $reports->pluck('id')->all()]);

        return view('care.side-effects.index', ['status' => $status, 'reports' => $reports]);
    }

    public function update(Request $request, SideEffectReport $report, TriageSideEffect $triage): RedirectResponse
    {
        $this->authorize('handle', $report);

        $data = $request->validate([
            'status' => ['required', Rule::in([SideEffectStatus::Reviewed->value, SideEffectStatus::Escalated->value])],
            'response_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $triage->handle($report, $request->user(), SideEffectStatus::from($data['status']), $data['response_note'] ?? null);

        return back()->with('status', __('تم تحديث حالة العَرَض.'));
    }
}
