<?php

namespace App\Http\Controllers\Care;

use App\Enums\ContactRequestStatus;
use App\Enums\SideEffectStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\SideEffectReport;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $open = ContactRequest::query()->where('status', '!=', ContactRequestStatus::Resolved->value);

        return view('care.dashboard', [
            'openRequests' => (clone $open)->count(),
            'urgentRequests' => (clone $open)->where('priority', 'urgent')->count(),
            'newSideEffects' => SideEffectReport::query()->where('status', SideEffectStatus::New->value)->count(),
            'escalated' => SideEffectReport::query()->where('status', SideEffectStatus::Escalated->value)->count(),
            'latestRequests' => (clone $open)->with('user')->orderByRaw("case when priority = 'urgent' then 0 else 1 end")->oldest()->limit(8)->get(),
        ]);
    }
}
