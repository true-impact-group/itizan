<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('itizan:appointment-reminders')]
#[Description('Queue a reminder for confirmed appointments starting within the next hour')]
class SendAppointmentReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        Appointment::query()
            ->with('user')
            ->where('status', AppointmentStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_at', [now(), now()->addHour()])
            ->chunkById(200, function ($appointments) use (&$sent) {
                foreach ($appointments as $appointment) {
                    $appointment->user->notify(new AppointmentNotification($appointment, reminder: true));
                    $appointment->update(['reminder_sent_at' => now()]);
                    $sent++;
                }
            });

        $this->info("Appointment reminders queued: {$sent}");

        return self::SUCCESS;
    }
}
