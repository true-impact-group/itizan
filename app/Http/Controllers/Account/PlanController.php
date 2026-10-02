<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Measurement;
use App\Models\MedicalAssessment;
use App\Models\WeightPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $plan = WeightPlan::query()->with(['practitioner.user', 'dietitian.user'])
            ->where('user_id', $user->id)->where('status', 'active')->first();

        if ($plan !== null) {
            $this->authorize('view', $plan);
        }

        $latest = Measurement::query()->where('user_id', $user->id)->latest('measured_on')->latest('id')->first();

        return view('account.plan.show', [
            'plan' => $plan,
            'latest' => $latest,
            'progress' => $plan && $latest ? $plan->progressAt($latest->weight_kg) : null,
            'assessment' => MedicalAssessment::query()->where('user_id', $user->id)->latest('id')->first(),
        ]);
    }
}
