<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Role: string
{
    use HasLabel;

    case Client = 'client';
    case Doctor = 'doctor';
    case Dietitian = 'dietitian';
    case CareTeam = 'care_team';
    case Pharmacy = 'pharmacy';
    case Delivery = 'delivery';
    case Support = 'support';
    case Admin = 'admin';

    public static function labels(): array
    {
        return [
            'client' => 'عميل',
            'doctor' => 'طبيب / استشاري',
            'dietitian' => 'أخصائي تغذية',
            'care_team' => 'فريق الرعاية',
            'pharmacy' => 'صيدلية',
            'delivery' => 'شريك التوصيل',
            'support' => 'الدعم',
            'admin' => 'الإدارة',
        ];
    }

    public function isStaff(): bool
    {
        return $this !== self::Client;
    }

    /** Roles that may open clinical records (least privilege: admin and support cannot). */
    public function canAccessMedicalData(): bool
    {
        return in_array($this, [self::Doctor, self::Dietitian, self::CareTeam], true);
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Client => 'account.dashboard',
            self::Doctor, self::Dietitian => 'practitioner.dashboard',
            self::CareTeam => 'care.dashboard',
            self::Pharmacy => 'pharmacy.orders.index',
            self::Delivery => 'delivery.orders.index',
            self::Support, self::Admin => 'admin.dashboard',
        };
    }
}
