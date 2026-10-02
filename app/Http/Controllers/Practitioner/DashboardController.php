<?php

namespace App\Http\Controllers\Practitioner;

use App\Enums\AppointmentStatus;
use App\Enums\AssessmentStatus;
use App\Enums\ContactRequestStatus;
use App\Enums\ContactTarget;
use App\Enums\LabStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ContactRequest;
use App\Models\LabRequest;
use App\Models\MedicalAssessment;
use App\Models\Prescription;
use App\Models\User;
use App\Models\WeightPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $practitioner = $user->practitioner;
        $isDoctor = $user->hasRole(Role::Doctor);

        return view('practitioner.dashboard', [
            'practitioner' => $practitioner,
            'isDoctor' => $isDoctor,
            'todayAppointments' => $practitioner
                ? Appointment::query()->with('user')->where('practitioner_id', $practitioner->id)
                    ->where('status', AppointmentStatus::Confirmed->value)
                    ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])->orderBy('scheduled_at')->get()
                : collect(),
            'instantQueue' => $isDoctor && $practitioner?->accepts_instant && $practitioner->isVerified()
                ? Appointment::query()->with('user')->where('is_instant', true)->whereNull('practitioner_id')
                    ->where('status', AppointmentStatus::Requested->value)->oldest()->limit(10)->get()
                : collect(),
            'pendingAssessments' => $isDoctor ? MedicalAssessment::query()->where('status', AssessmentStatus::Submitted->value)->count() : null,
            'redFlagAssessments' => $isDoctor ? MedicalAssessment::query()->where('status', AssessmentStatus::Submitted->value)->where('has_red_flags', true)->count() : null,
            'labsToReview' => $isDoctor ? LabRequest::query()->where('status', LabStatus::Uploaded->value)->count() : null,
            'urgentRequests' => $isDoctor
                ? ContactRequest::query()->with('user')->where('target', ContactTarget::Doctor->value)
                    ->where('status', '!=', ContactRequestStatus::Resolved->value)->orderByRaw("case when priority = 'urgent' then 0 else 1 end")->latest()->limit(5)->get()
                : collect(),
            // Continuity of care and loose ends: my clients, finished sessions left open, prescriptions whose subscription lapsed.
            'myClients' => $isDoctor && $practitioner ? User::query()->where('primary_practitioner_id', $practitioner->id)->orderBy('name')->limit(10)->get() : collect(),
            'staleAppointments' => $practitioner ? Appointment::query()->awaitingClosure()->with('user')->where('practitioner_id', $practitioner->id)->orderBy('scheduled_at')->limit(10)->get() : collect(),
            'lapsedPrescriptions' => $isDoctor ? Prescription::query()->lapsed()->with('user')->orderBy('id')->limit(10)->get() : collect(),
            'assignedPlans' => ! $isDoctor && $practitioner
                ? WeightPlan::query()->with('user')->where('dietitian_id', $practitioner->id)->where('status', 'active')->latest()->limit(20)->get()
                : collect(),
        ]);
    }
}
