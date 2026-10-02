<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum OrderStatus: string
{
    use HasLabel;

    case Requested = 'requested';
    case Quoted = 'quoted';
    case Approved = 'approved';
    case Preparing = 'preparing';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public static function labels(): array
    {
        return [
            'requested' => 'بانتظار مراجعة الصيدلية',
            'quoted' => 'بانتظار موافقتك على السعر',
            'approved' => 'تمت الموافقة',
            'preparing' => 'قيد التجهيز',
            'out_for_delivery' => 'في الطريق إليك',
            'delivered' => 'تم التسليم',
            'rejected' => 'غير متوفر',
            'cancelled' => 'ملغى',
        ];
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Quoted, self::Approved, self::Preparing, self::OutForDelivery => 'info',
            self::Delivered => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Rejected, self::Cancelled], true);
    }
}
