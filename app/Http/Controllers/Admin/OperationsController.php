<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LabStatus;
use App\Enums\OrderStatus;
use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\LabRequest;
use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Monitoring of prescriptions, labs, pharmacy requests and delivery, by status only: no clinical content. */
class OperationsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        return view('admin.operations', [
            'status' => $status,
            'prescriptionCounts' => Prescription::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'labCounts' => LabRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'prescriptionStatuses' => PrescriptionStatus::cases(),
            'labStatuses' => LabStatus::cases(),
            'orders' => Order::query()->with(['pharmacy', 'user'])
                ->when($status, fn ($query) => $query->where('status', $status->value))
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }
}
