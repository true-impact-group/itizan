<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\GenericNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('itizan:stale-appointments')]
#[Description('Remind the doctor, once, about a finished session that was never closed as completed or no-show')]
class RemindStaleAppointments extends Command
{
    public function handle(): int
    {
        $reminded = 0;

        Appointment::query()->awaitingClosure()->whereNull('closure_reminded_at')->whereNotNull('practitioner_id')
            ->with('practitioner.user')
            ->chunkById(100, function ($appointments) use (&$reminded) {
                foreach ($appointments as $appointment) {
                    $appointment->practitioner->user->notify(new GenericNotification(
                        'لديك موعد لم يُغلق',
                        'انتهى وقت أحد مواعيدك. أغلقه بـ «مكتملة» أو «لم يحضر» ليكتمل مسار العميل.',
                        route('practitioner.appointments.show', $appointment),
                    ));
                    $appointment->update(['closure_reminded_at' => now()]);
                    $reminded++;
                }
            });

        $this->info("Closure reminders queued: {$reminded}");

        return self::SUCCESS;
    }
}
