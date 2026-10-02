<?php

namespace App\Notifications;

use App\Models\Prescription;

/** Deliberately says nothing about which medication or dose. */
class DoseReminderNotification extends PlatformNotification
{
    public function __construct(private readonly Prescription $prescription)
    {
        parent::__construct();
    }

    protected function title(): string
    {
        return __('حان موعد جرعتك');
    }

    protected function body(): string
    {
        return __('لديك جرعة مجدولة قريبًا. افتح حسابك لتراجعها وتسجّلها.');
    }

    protected function url(): ?string
    {
        return route('account.medications.show', $this->prescription);
    }
}
