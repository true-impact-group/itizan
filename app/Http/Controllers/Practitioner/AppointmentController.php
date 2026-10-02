<?php

namespace App\Http\Controllers\Practitioner;

use App\Actions\Appointments\ClaimInstantAppointment;
use App\Actions\Appointments\CompleteAppointment;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $practitioner = $request->user()->practitioner;
        $tab = $request->query('tab') === 'past' ? 'past' : 'upcoming';

        $query = Appointment::query()->with('user')->where('practitioner_id', $practitioner?->id);
        $query = $tab === 'past'
            ? $query->whereNotIn('status', AppointmentStatus::active())->orderByDesc('scheduled_at')
            : $query->whereIn('status', AppointmentStatus::active())->orderBy('scheduled_at');

        return view('practitioner.appointments.index', ['appointments' => $query->paginate(15)->withQueryString(), 'tab' => $tab]);
    }

    public function show(Appointment $appointment): View
    {
        $this->authorize('view', $appointment);

        $appointment->load('user');
        Audit::log('appointment.viewed', $appointment);

        return view('practitioner.appointments.show', ['appointment' => $appointment]);
    }

    public function complete(Request $request, Appointment $appointment, CompleteAppointment $complete): RedirectResponse
    {
        $this->authorize('complete', $appointment);

        $data = $request->validate([
            'outcome' => ['required', Rule::in([AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $complete->handle($appointment, AppointmentStatus::from($data['outcome']), $data['notes'] ?? null);

        return redirect()->route('practitioner.appointments.show', $appointment)->with('status', __('تم تحديث حالة الموعد.'));
    }

    public function claim(Request $request, Appointment $appointment, ClaimInstantAppointment $claim): RedirectResponse
    {
        $this->authorize('claim', $appointment);

        $claim->handle($appointment, $request->user()->practitioner);

        return redirect()->route('practitioner.appointments.show', $appointment)->with('status', __('تم استلام الاستشارة. ابدأ الجلسة عبر رابط FTMS.'));
    }
}
