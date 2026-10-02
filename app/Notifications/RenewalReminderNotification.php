<?php

namespace App\Notifications;

use App\Models\Subscription;

class RenewalReminderNotification extends PlatformNotification
{
    public function __construct(private readonly Subscription $subscription)
    {
        parent::__construct();
    }

    protected function title(): string
    {
        return __('اشتراكك يقارب على الانتهاء');
    }

    protected function body(): string
    {
        return __('ينتهي اشتراكك في :date. يمكنك مراجعة التجديد وإدارة اشتراكك من حسابك.', [
            'date' => $this->subscription->ends_at->translatedFormat('j F Y'),
        ]);
    }

    protected function url(): ?string
    {
        return route('account.subscription.show');
    }
}
