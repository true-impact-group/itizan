<?php

namespace App\Http\Controllers\Practitioner;

use App\Actions\Plans\CreateWeightPlan;
use App\Actions\Plans\UpdateWeightPlan;
use App\Enums\PractitionerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practitioner\SavePlanRequest;
use App\Models\Practitioner;
use App\Models\User;
use App\Models\WeightPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlanController extends Controller
{
    public function create(User $client): View
    {
        $this->authorize('viewClinical', $client);
        $this->authorize('create', WeightPlan::class);

        return view('practitioner.plans.form', [
            'client' => $client,
            'plan' => null,
            'dietitians' => $this->dietitians(),
        ]);
    }

    public function store(SavePlanRequest $request, User $client, CreateWeightPlan $create): RedirectResponse
    {
        $this->authorize('viewClinical', $client);
        $this->authorize('create', WeightPlan::class);

        $create->handle($client, $request->user()->practitioner, $request->validated());

        return redirect()->route('practitioner.clients.show', $client)->with('status', __('تم إعداد الخطة وإبلاغ العميل.'));
    }

    public function edit(WeightPlan $plan): View
    {
        $this->authorize('update', $plan);

        return view('practitioner.plans.form', [
            'client' => $plan->loadMissing('user')->user,
            'plan' => $plan,
            'dietitians' => $this->dietitians(),
        ]);
    }

    public function update(SavePlanRequest $request, WeightPlan $plan, UpdateWeightPlan $update): RedirectResponse
    {
        $this->authorize('update', $plan);

        $update->handle($plan, $request->user()->practitioner, $request->validated());

        return redirect()->route('practitioner.clients.show', $plan->user_id)->with('status', __('تم تحديث الخطة.'));
    }

    private function dietitians()
    {
        return Practitioner::query()->verified()->ofType(PractitionerType::Dietitian)->with('user')->get();
    }
}
