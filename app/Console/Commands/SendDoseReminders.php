<?php

namespace App\Console\Commands;

use App\Models\Prescription;
use App\Notifications\DoseReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('itizan:dose-reminders')]
#[Description('Queue a reminder for every dose that is due soon and has not been reminded yet')]
class SendDoseReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        Prescription::query()
            ->active()
            ->with('user')
            ->whereNotNull('next_dose_at')
            ->where('next_dose_at', '<=', now()->addMinutes((int) config('itizan.dose_reminder_lead_minutes')))
            // Do not chase doses that are already long overdue.
            ->where('next_dose_at', '>=', now()->subHours(12))
            ->where(fn ($query) => $query->whereNull('reminder_sent_for')->orWhereColumn('reminder_sent_for', '!=', 'next_dose_at'))
            ->chunkById(200, function ($prescriptions) use (&$sent) {
                foreach ($prescriptions as $prescription) {
                    $prescription->user->notify(new DoseReminderNotification($prescription));
                    $prescription->update(['reminder_sent_for' => $prescription->next_dose_at]);
                    $sent++;
                }
            });

        $this->info("Dose reminders queued: {$sent}");

        return self::SUCCESS;
    }
}
