<?php

namespace App\Actions\Appointments;

use App\Models\Practitioner;
use Illuminate\Support\Facades\DB;

class SaveAvailability
{
    /**
     * Replaces the practitioner's weekly availability.
     *
     * @param  array<int, list<array{start?: ?string, end?: ?string}>>  $days  weekday (0 = Sunday) => time windows
     */
    public function handle(Practitioner $practitioner, array $days, int $slotMinutes): void
    {
        DB::transaction(function () use ($practitioner, $days, $slotMinutes) {
            $practitioner->availabilities()->delete();

            foreach ($days as $weekday => $windows) {
                foreach ($windows as $window) {
                    if (blank($window['start'] ?? null) || blank($window['end'] ?? null)) {
                        continue;
                    }

                    $practitioner->availabilities()->updateOrCreate(
                        ['weekday' => (int) $weekday, 'start_time' => $window['start']],
                        ['end_time' => $window['end'], 'slot_minutes' => $slotMinutes],
                    );
                }
            }
        });
    }
}
