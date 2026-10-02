<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\PractitionerType;
use App\Models\Appointment;
use App\Models\Practitioner;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns weekly practitioner availability into concrete bookable slots.
 * A slot is free when the practitioner is verified, in the future (after the minimum notice)
 * and has no active appointment at that time.
 */
class SlotFinder
{
    /**
     * @return Collection<int, array{practitioner: Practitioner, starts_at: Carbon}> sorted by time
     */
    public function forDate(CarbonInterface $date, ?int $practitionerId = null): Collection
    {
        $day = Carbon::instance($date)->startOfDay();

        if ($day->lt(today()) || $day->gt(today()->addDays((int) config('itizan.booking.horizon_days')))) {
            return collect();
        }

        $practitioners = Practitioner::query()
            ->verified()
            ->ofType(PractitionerType::Doctor)
            ->when($practitionerId, fn ($query) => $query->whereKey($practitionerId))
            ->with(['user', 'availabilities' => fn ($query) => $query->where('weekday', $day->dayOfWeek)])
            ->get();

        $booked = Appointment::query()
            ->whereIn('practitioner_id', $practitioners->pluck('id'))
            ->whereIn('status', AppointmentStatus::active())
            ->whereBetween('scheduled_at', [$day, $day->copy()->endOfDay()])
            ->get(['practitioner_id', 'scheduled_at'])
            ->map(fn (Appointment $appointment) => $appointment->practitioner_id.'|'.$appointment->scheduled_at->timestamp)
            ->flip();

        $earliest = now()->addMinutes((int) config('itizan.booking.min_notice_minutes'));
        $slots = collect();

        foreach ($practitioners as $practitioner) {
            foreach ($practitioner->availabilities as $window) {
                $step = $window->slot_minutes ?: (int) config('itizan.booking.default_slot_minutes');
                $cursor = $day->copy()->setTimeFromTimeString($window->start_time);
                // "23:59" means "until midnight", so a doctor can cover the whole day with no lost final slot.
                $end = $window->end_time >= '23:59'
                    ? $day->copy()->addDay()->startOfDay()
                    : $day->copy()->setTimeFromTimeString($window->end_time);

                while ($cursor->copy()->addMinutes($step)->lte($end)) {
                    if ($cursor->gte($earliest) && ! $booked->has($practitioner->id.'|'.$cursor->timestamp)) {
                        $slots->push(['practitioner' => $practitioner, 'starts_at' => $cursor->copy()]);
                    }
                    $cursor->addMinutes($step);
                }
            }
        }

        return $slots->sortBy(fn (array $slot) => $slot['starts_at']->timestamp)->values();
    }

    /** The practitioner who can take the given start time, optionally restricted to one practitioner. */
    public function practitionerFor(CarbonInterface $startsAt, ?int $practitionerId = null): ?Practitioner
    {
        $slot = $this->forDate($startsAt, $practitionerId)
            ->first(fn (array $slot) => $slot['starts_at']->equalTo($startsAt));

        return $slot['practitioner'] ?? null;
    }
}
