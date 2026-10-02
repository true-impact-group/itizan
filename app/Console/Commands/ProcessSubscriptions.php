<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\RenewalReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('itizan:process-subscriptions')]
#[Description('Expire finished subscriptions, close abandoned checkouts and remind clients whose subscription ends soon')]
class ProcessSubscriptions extends Command
{
    /** Days before the end date on which the renewal reminder is sent. */
    private const REMINDER_DAYS = 7;

    public function handle(): int
    {
        $expired = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        $abandoned = Subscription::query()
            ->where('status', SubscriptionStatus::Pending->value)
            ->where('created_at', '<', now()->subDays(2))
            ->update(['status' => SubscriptionStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => 'abandoned']);

        $reminded = 0;
        Subscription::query()
            ->with('user')
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNull('renewal_reminded_at')
            ->whereBetween('ends_at', [now(), now()->addDays(self::REMINDER_DAYS)])
            ->chunkById(200, function ($subscriptions) use (&$reminded) {
                foreach ($subscriptions as $subscription) {
                    $subscription->user->notify(new RenewalReminderNotification($subscription));
                    $subscription->update(['renewal_reminded_at' => now()]);
                    $reminded++;
                }
            });

        $this->info("Expired: {$expired}, abandoned closed: {$abandoned}, renewal reminders queued: {$reminded}");

        return self::SUCCESS;
    }
}
