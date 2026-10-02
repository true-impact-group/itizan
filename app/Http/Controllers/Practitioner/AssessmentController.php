<?php

namespace App\Http\Controllers\Practitioner;

use App\Actions\Assessment\ReviewAssessment;
use App\Enums\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practitioner\ReviewAssessmentRequest;
use App\Models\MedicalAssessment;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = AssessmentStatus::tryFrom((string) $request->query('status')) ?? AssessmentStatus::Submitted;

        return view('practitioner.assessments.index', [
            'status' => $status,
            'assessments' => MedicalAssessment::query()->with('user')
                ->where('status', $status->value)
                ->when($request->boolean('red_flags'), fn ($query) => $query->where('has_red_flags', true))
                // Cases with safety flags first, then oldest first.
                ->orderByDesc('has_red_flags')->oldest('submitted_at')
                ->paginate(15)->withQueryString(),
        ]);
    }

    public function show(MedicalAssessment $assessment): View
    {
        $this->authorize('view', $assessment);

        Audit::log('assessment.viewed', $assessment);

        return view('practitioner.assessments.show', [
            'assessment' => $assessment->load(['user', 'reviewer']),
            'questions' => config('itizan.safety_questions'),
        ]);
    }

    public function review(ReviewAssessmentRequest $request, MedicalAssessment $assessment, ReviewAssessment $review): RedirectResponse
    {
        $review->handle(
            $assessment,
            $request->user(),
            AssessmentStatus::from($request->string('decision')->toString()),
            $request->input('notes'),
        );

        return redirect()->route('practitioner.assessments.index')->with('status', __('تم حفظ قرارك وإبلاغ العميل.'));
    }
}
