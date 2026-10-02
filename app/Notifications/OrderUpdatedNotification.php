<?php

namespace App\Notifications;

use App\Models\Order;

class OrderUpdatedNotification extends PlatformNotification
{
    public function __construct(private readonly Order $order)
    {
        parent::__construct();
    }

    protected function title(): string
    {
        return __('تحديث على طلبك رقم :id', ['id' => $this->order->id]);
    }

    protected function body(): string
    {
        return __('حالة طلبك الآن: :status', ['status' => $this->order->status->label()]);
    }

    protected function url(): ?string
    {
        return route('account.orders.show', $this->order);
    }
}
