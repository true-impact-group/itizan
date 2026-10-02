<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ConsentType: string
{
    use HasLabel;

    case TermsPrivacy = 'terms_privacy';
    case Marketing = 'marketing';
    case MedicalData = 'medical_data';
    case Telemedicine = 'telemedicine';
    case AutoRenewal = 'auto_renewal';

    public static function labels(): array
    {
        return [
            'terms_privacy' => 'الشروط وسياسة الخصوصية',
            'marketing' => 'الرسائل التسويقية',
            'medical_data' => 'معالجة البيانات الصحية',
            'telemedicine' => 'الاستشارة عن بُعد',
            'auto_renewal' => 'التجديد التلقائي',
        ];
    }
}
