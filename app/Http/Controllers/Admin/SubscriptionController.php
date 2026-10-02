<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $status = SubscriptionStatus::tryFrom((string) $request->query('status'));

        return view('admin.subscriptions.index', [
            'status' => $status,
            'subscriptions' => Subscription::query()->with(['user', 'package'])
                ->when($status, fn ($query) => $query->where('status', $status->value))
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }
}
