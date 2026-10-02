<?php

namespace App\Notifications;

class WelcomeNotification extends PlatformNotification
{
    protected function title(): string
    {
        return __('أهلًا بك في اتزان');
    }

    protected function body(): string
    {
        return __('أهلًا بك في اتزان. خطوتك الأولى تبدأ باستشارة، وفريقنا يرشدك إلى الخطوة التالية.');
    }

    protected function url(): ?string
    {
        return route('account.dashboard');
    }
}
