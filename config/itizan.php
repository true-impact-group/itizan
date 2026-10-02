<?php

/*
|--------------------------------------------------------------------------
| Itizan platform settings
|--------------------------------------------------------------------------
| Business constants live here so that they can be reviewed in one place.
| Anything environment-specific is read from .env (see .env.example).
*/

return [

    'whatsapp' => [
        // International format without "+" or leading zeros (wa.me requirement).
        'number' => env('WHATSAPP_NUMBER', '966551475212'),
        'message' => 'السلام عليكم، أنا عميل برنامج اتزان، وأحتاج مساعدة بخصوص: ',
    ],

    'support_email' => env('SUPPORT_EMAIL', 'support@itizan.test'),

    // Shown on payment receipts.
    'company' => [
        'name' => env('COMPANY_NAME', 'اتزان'),
        'vat_number' => env('COMPANY_VAT_NUMBER'),
    ],

    // Saudi Red Crescent emergency number shown wherever care is discussed.
    'emergency_number' => '997',

    'currency' => 'SAR',

    // Saudi VAT, percent.
    'vat_rate' => 15,

    'ftms' => [
        // Telemedicine provider. {reference} is replaced with the appointment reference.
        'join_url' => env('FTMS_JOIN_URL', 'https://ftms.example.com/join/{reference}'),
    ],

    'booking' => [
        'horizon_days' => 14,
        'min_notice_minutes' => 30,
        'default_slot_minutes' => 20,
        'cancel_before_hours' => 2,
    ],

    'delivery' => [
        'fee_halalas' => 2500,
        // Cities where delivery is free (subject to coverage and availability).
        'free_cities' => ['الرياض', 'جدة', 'الدمام'],
    ],

    'cities' => [
        'الرياض', 'جدة', 'مكة المكرمة', 'المدينة المنورة', 'الدمام', 'الخبر',
        'الظهران', 'الأحساء', 'بريدة', 'تبوك', 'أبها', 'خميس مشيط', 'حائل', 'جازان', 'نجران', 'الطائف', 'أخرى',
    ],

    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'sandbox'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        // Tabby/Tamara stay disabled until the merchant account is approved.
        'methods' => [
            'apple_pay' => env('PAYMENT_APPLE_PAY_ENABLED', true),
            'mada' => env('PAYMENT_MADA_ENABLED', true),
            'tabby' => env('PAYMENT_TABBY_ENABLED', false),
            'tamara' => env('PAYMENT_TAMARA_ENABLED', false),
        ],
        'installments' => 4,
    ],

    'uploads' => [
        'disk' => env('PRIVATE_UPLOADS_DISK', 'local'),
        'max_kb' => 5120,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    'dose_reminder_lead_minutes' => 30,

    /*
     * Safety screening. "red_flag" is the answer that asks the doctor to look
     * closely at the case. It never blocks the client: the doctor decides.
     */
    'safety_questions' => [
        'thyroid_cancer' => ['text' => 'هل لديك أو لدى أحد أقاربك تاريخ مع سرطان الغدة الدرقية النخاعي أو متلازمة MEN2؟', 'red_flag' => true],
        'pancreatitis' => ['text' => 'هل سبق أن أصبت بالتهاب البنكرياس؟', 'red_flag' => true],
        'eating_disorder' => ['text' => 'هل لديك أو سبق أن شُخّصت باضطراب في الأكل؟', 'red_flag' => true],
        'kidney_liver' => ['text' => 'هل تعاني من مرض شديد في الكلى أو الكبد؟', 'red_flag' => true],
        'gallbladder' => ['text' => 'هل تعاني من أمراض المرارة أو حصوات المرارة؟', 'red_flag' => true],
        'type1_diabetes' => ['text' => 'هل لديك السكري من النوع الأول؟', 'red_flag' => true],
        'planning_pregnancy' => ['text' => 'هل تخططين للحمل خلال الأشهر القادمة؟', 'red_flag' => true],
        'under_18' => ['text' => 'هل عمرك أقل من 18 سنة؟', 'red_flag' => true],
    ],
];
