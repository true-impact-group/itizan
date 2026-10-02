<?php

namespace App\Http\Controllers\Account;

use App\Actions\Assessment\SaveAssessment;
use App\Enums\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\SaveAssessmentRequest;
use App\Models\MedicalAssessment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function show(Request $request): View
    {
        $assessment = MedicalAssessment::query()->where('user_id', $request->user()->id)->latest('id')->first();

        // After a finished review the client can start an updated assessment, pre-filled from the last one.
        $startingNew = $assessment !== null && ! $assessment->status->isEditableByClient()
            && $assessment->status !== AssessmentStatus::Submitted
            && $request->boolean('update');

        return view('account.assessment.show', [
            'assessment' => $assessment,
            'editable' => $assessment === null || $assessment->status->isEditableByClient() || $startingNew,
            'prefill' => $startingNew ? $assessment : null,
            'questions' => config('itizan.safety_questions'),
        ]);
    }

    public function update(SaveAssessmentRequest $request, SaveAssessment $save): RedirectResponse
    {
        $assessment = MedicalAssessment::query()->where('user_id', $request->user()->id)
            ->whereIn('status', [AssessmentStatus::Draft->value, AssessmentStatus::NeedsInfo->value])->first();

        if ($assessment !== null) {
            $this->authorize('update', $assessment);
        }

        $saved = $save->handle($request->user(), $request->assessmentData(), $request->isSubmit());

        return redirect()->route('account.assessment.show')->with('status', $saved->status === AssessmentStatus::Submitted
            ? __('تم إرسال تقييمك إلى الطبيب. سنخبرك فور اكتمال المراجعة.')
            : __('تم حفظ المسودة. يمكنك إكمالها لاحقًا.'));
    }
}
