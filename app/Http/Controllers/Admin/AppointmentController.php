<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Appointments\CancelAppointment;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Scheduling overview. Shows who/when/status only: no consultation notes. */
class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = AppointmentStatus::tryFrom((string) $request->query('status'));

        return view('admin.appointments.index', [
            'status' => $status,
            'appointments' => Appointment::query()->with(['user', 'practitioner.user'])
                ->when($status, fn ($query) => $query->where('status', $status->value))
                ->orderByDesc('scheduled_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, CancelAppointment $cancel): RedirectResponse
    {
        $this->authorize('cancel', $appointment);

        $cancel->handle($appointment, $request->user(), $request->input('reason', 'admin'));

        return back()->with('status', __('تم إلغاء الموعد.'));
    }
}
