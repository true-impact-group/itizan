<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['هل الاستشارة الأولى مجانية؟', 'Is the first consultation free?',
                'نعم. يحصل العميل الجديد على استشارة طبية واحدة مجانية قبل الاشتراك، ولا تلزمك بالاشتراك. بعدها تقرر إن كنت تريد متابعة البرنامج.',
                'Yes. A new client gets one free medical consultation before subscribing. It does not oblige you to subscribe; afterwards you decide whether to continue.'],
            ['من يقدّم الاستشارات؟', 'Who provides the consultations?',
                'أطباء واستشاريون مرخّصون في الغدد الصماء والسمنة، عبر منصة الطب الاتصالي FTMS. يظهر اسم الطبيب وتخصصه وترخيصه بعد التحقق منها.',
                'Licensed doctors and consultants in endocrinology and obesity, through the FTMS telemedicine platform. The doctor\'s name, specialty and licence are shown once verified.'],
            ['هل سأحصل على دواء في الاستشارة الأولى؟', 'Will I get medication in the first consultation?',
                'ليس بالضرورة. القرار الطبي للطبيب وحده، ويخضع للتقييم الطبي بعد إكمال معلوماتك الصحية. لا تصدر المنصة وصفات آلية.',
                'Not necessarily. Medical decisions belong to the doctor alone and follow the medical assessment once your health information is complete. The platform issues no automatic prescriptions.'],
            ['كم تكلفة الاشتراك؟', 'How much is a subscription?',
                'باقتان: النصف سنوية 999 ريالًا لمدة 6 أشهر، والسنوية 1,499 ريالًا لمدة سنة. تظهر القيمة الإجمالية وما سيُخصم فعليًا قبل الدفع. الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك.',
                'Two packages: six months for SAR 999 and a year for SAR 1,499. The total and the amount actually charged are shown before payment. Medication and lab tests are not included unless stated otherwise.'],
            ['ما وسائل الدفع المتاحة؟', 'Which payment methods are available?',
                'Apple Pay ومدى. وتتاح تابي وتمارا بعد اعتماد حساب التاجر وقبول نوع الخدمة، ويعتمد التقسيط على موافقة مزود الدفع وأهلية العميل.',
                'Apple Pay and mada. Tabby and Tamara become available once the merchant account is approved and the service type accepted; instalments depend on the payment provider\'s approval and your eligibility.'],
            ['كيف أطلب الدواء وكم يستغرق التوصيل؟', 'How do I order medication and how long does delivery take?',
                'تطلب من حسابك، وتراجع الصيدلية التوفر والسعر وموعد التوصيل قبل أن تؤكد. يعتمد وقت التوصيل على المدينة وتوفر المنتج وتأكيد الصيدلية.',
                'You order from your account, and the pharmacy shows availability, price and delivery time before you confirm. Delivery time depends on the city, product availability and pharmacy confirmation.'],
            ['هل بياناتي الصحية آمنة؟', 'Is my health data safe?',
                'تتاح بياناتك للموظفين المخوّلين فقط بحسب أدوارهم، ولا نستخدمها لأغراض التسويق، وموافقاتك الطبية والتسويقية منفصلة.',
                'Your data is available only to authorised staff according to their roles, is never used for marketing, and your medical and marketing consents are separate.'],
            ['هل اتزان خدمة طوارئ؟', 'Is Itizan an emergency service?',
                'لا. في الحالات الطارئة اتصل بالهلال الأحمر على 997 أو توجه إلى أقرب طوارئ.',
                'No. In an emergency call the Saudi Red Crescent on 997 or go to the nearest emergency department.'],
        ];

        foreach ($faqs as $order => [$qAr, $qEn, $aAr, $aEn]) {
            Faq::firstOrCreate(['question_ar' => $qAr], [
                'question_en' => $qEn, 'answer_ar' => $aAr, 'answer_en' => $aEn,
                'sort_order' => $order + 1, 'is_published' => true,
            ]);
        }
    }
}
