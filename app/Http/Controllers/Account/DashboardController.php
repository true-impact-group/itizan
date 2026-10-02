<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ContactRequest;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\Subscription;
use App\Models\WeightPlan;
use App\Services\ClientJourney;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ClientJourney $journey): View
    {
        $user = $request->user();

        $plan = WeightPlan::query()->where('user_id', $user->id)->where('status', 'active')->first();
        $latest = Measurement::query()->where('user_id', $user->id)->latest('measured_on')->latest('id')->first();

        return view('account.dashboard', [
            'nextStep' => $journey->nextStep($user),
            'steps' => $journey->steps($user),
            'subscription' => Subscription::query()->with('package')->where('user_id', $user->id)->current()->latest('ends_at')->first(),
            'upcoming' => Appointment::query()->with('practitioner.user')->where('user_id', $user->id)->upcoming()->orderBy('scheduled_at')->limit(3)->get(),
            'plan' => $plan,
            'latestMeasurement' => $latest,
            'progress' => $plan && $latest ? $plan->progressAt($latest->weight_kg) : null,
            'dueDoses' => Prescription::query()->where('user_id', $user->id)->active()->whereNotNull('next_dose_at')->orderBy('next_dose_at')->limit(3)->get(),
            'openOrders' => Order::query()->where('user_id', $user->id)
                ->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value, OrderStatus::Rejected->value])->latest()->limit(3)->get(),
            'openContactRequests' => ContactRequest::query()->where('user_id', $user->id)->where('status', '!=', 'resolved')->count(),
            'notifications' => $user->notifications()->limit(5)->get(),
        ]);
    }
}
