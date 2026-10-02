<?php

namespace App\Http\Controllers\Practitioner;

use App\Enums\PractitionerType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\LabRequest;
use App\Models\Measurement;
use App\Models\MedicalAssessment;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use App\Models\WeightPlan;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Clinical file of one client. Reached by searching (minimum 3 characters) rather than by browsing
 * every client, and every opening is written to the audit log.
 */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $user = $request->user();
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $clients = collect();

        if (mb_strlen($term) >= 3) {
            $clients = User::query()
                ->where('role', Role::Client->value)
                ->where(fn ($query) => $query
                    ->where('name', 'ilike', '%'.addcslashes($term, '%_\\').'%')
                    ->when(strlen($digits) >= 3, fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%")))
                // A dietitian only reaches clients assigned to them.
                ->when($user->hasRole(Role::Dietitian), fn ($query) => $query->whereIn('id', WeightPlan::query()
                    ->where('dietitian_id', $user->practitioner?->id)->select('user_id')))
                ->orderBy('name')->limit(25)->get();
        }

        return view('practitioner.clients.index', ['term' => $term, 'clients' => $clients]);
    }

    public function show(Request $request, User $client): View
    {
        $this->authorize('viewClinical', $client);

        Audit::log('client.clinical_viewed', $client);

        $isDoctor = $request->user()->hasRole(Role::Doctor);

        return view('practitioner.clients.show', [
            'client' => $client,
            'isDoctor' => $isDoctor,
            'hasSubscription' => $client->hasActiveSubscription(),
            'assessment' => MedicalAssessment::query()->where('user_id', $client->id)->latest('id')->first(),
            'plan' => WeightPlan::query()->with(['practitioner.user', 'dietitian.user'])->where('user_id', $client->id)->where('status', 'active')->first(),
            'measurements' => Measurement::query()->where('user_id', $client->id)->latest('measured_on')->latest('id')->limit(15)->get(),
            'prescriptions' => Prescription::query()->with('practitioner.user')->where('user_id', $client->id)->latest('id')->get(),
            'labs' => LabRequest::query()->with('attachments')->where('user_id', $client->id)->latest()->get(),
            'appointments' => Appointment::query()->with('practitioner.user')->where('user_id', $client->id)->latest('scheduled_at')->limit(10)->get(),
            'medications' => $isDoctor ? Product::query()->where('type', 'medication')->orderable()->orderBy('name_ar')->get() : collect(),
            'dietitians' => $isDoctor ? Practitioner::query()->verified()->ofType(PractitionerType::Dietitian)->with('user')->get() : collect(),
        ]);
    }
}
