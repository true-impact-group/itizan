<?php

namespace App\Http\Controllers\Account;

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\CancelAppointment;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\PractitionerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\BookAppointmentRequest;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Subscription;
use App\Services\SlotFinder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.appointments.index', [
            'appointments' => Appointment::query()
                ->with('practitioner.user')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('scheduled_at')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, SlotFinder $finder): View|RedirectResponse
    {
        $user = $request->user();

        $freeUsed = Appointment::query()->where('user_id', $user->id)
            ->where('type', AppointmentType::FreeIntro->value)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)->exists();
        $subscription = Subscription::query()->where('user_id', $user->id)->current()->latest('ends_at')->first();

        $type = AppointmentType::tryFrom((string) $request->query('type')) ?? ($freeUsed ? AppointmentType::FollowUp : AppointmentType::FreeIntro);

        if ($type === AppointmentType::FreeIntro && $freeUsed) {
            return redirect()->route('account.appointments.index')->with('error', __('لقد استفدت من الاستشارة المجانية من قبل.'));
        }

        if ($type === AppointmentType::FollowUp && ($subscription === null || $subscription->remainingFollowups() < 1)) {
            return redirect()->route('account.subscription.show')
                ->with('error', __('تحتاج إلى اشتراك فعّال وفيه استشارات متابعة متبقية لحجز استشارة متابعة.'));
        }

        $date = $this->selectedDate($request);

        // Continuity of care: offer the client's own doctor first, with a switch to "any available doctor".
        $primary = $user->primaryPractitioner()->verified()->with('user')->first();
        $with = $primary !== null && $request->query('with') !== 'any' ? 'primary' : 'any';

        return view('account.appointments.create', [
            'type' => $type,
            'subscription' => $subscription,
            'date' => $date,
            'days' => collect(range(0, (int) config('itizan.booking.horizon_days')))->map(fn (int $offset) => today()->addDays($offset)),
            'primary' => $primary,
            'with' => $with,
            'times' => $finder->forDate($date, $with === 'primary' ? $primary->id : null)->unique(fn (array $slot) => $slot['starts_at']->timestamp)->pluck('starts_at')->values(),
            'modes' => AppointmentMode::cases(),
            'instantAvailable' => Practitioner::query()->verified()->ofType(PractitionerType::Doctor)->where('accepts_instant', true)->exists(),
        ]);
    }

    public function store(BookAppointmentRequest $request, BookAppointment $book): RedirectResponse
    {
        $appointment = $book->handle($request->user(), $request->bookingData());

        return redirect()->route('account.appointments.show', $appointment)
            ->with('status', $appointment->is_instant
                ? __('تم إرسال طلبك. سيتواصل معك أول طبيب متاح.')
                : __('تم تأكيد موعدك.'));
    }

    public function show(Appointment $appointment): View
    {
        $this->authorize('view', $appointment);
        abort_unless($appointment->user_id === request()->user()->id, 403);

        $appointment->load('practitioner.user');

        return view('account.appointments.show', ['appointment' => $appointment]);
    }

    public function cancel(Request $request, Appointment $appointment, CancelAppointment $cancel): RedirectResponse
    {
        $this->authorize('cancel', $appointment);

        $cancel->handle($appointment, $request->user(), $request->input('reason'));

        return redirect()->route('account.appointments.index')->with('status', __('تم إلغاء الموعد.'));
    }

    private function selectedDate(Request $request): Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $request->query('date', today()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            return today();
        }

        return $date->lt(today()) ? today() : $date;
    }
}
