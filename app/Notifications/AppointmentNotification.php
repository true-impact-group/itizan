<?php

namespace App\Notifications;

use App\Models\Appointment;

/** Confirmation and reminder for a consultation; carries the time only, never the clinical reason. */
class AppointmentNotification extends PlatformNotification
{
    public function __construct(private readonly Appointment $appointment, private readonly bool $reminder = false)
    {
        parent::__construct();
    }

    protected function title(): string
    {
        return $this->reminder ? __('تذكير بموعد استشارتك') : __('تم تأكيد موعد استشارتك');
    }

    protected function body(): string
    {
        return __('موعدك: :time. يمكنك الدخول للجلسة من حسابك قبل الموعد بعشر دقائق.', [
            'time' => $this->appointment->scheduled_at->translatedFormat('l j F، h:i A'),
        ]);
    }

    protected function url(): ?string
    {
        return route('account.appointments.show', $this->appointment);
    }
}
