<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starter policy texts. They are drafts: legal, medical and commercial owners must review and
 * complete them (especially refund windows and cancellation terms) in the admin panel before launch.
 * Existing pages are never overwritten, so edits made by the team survive re-seeding.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $order => $page) {
            Page::firstOrCreate(['slug' => $page['slug']], [...$page, 'group' => 'policy', 'sort_order' => $order, 'is_published' => true]);
        }
    }

    /** @return list<array{slug: string, title_ar: string, title_en: string, body_ar: string, body_en: string}> */
    private function pages(): array
    {
        return [
            [
                'slug' => 'privacy',
                'title_ar' => 'سياسة الخصوصية',
                'title_en' => 'Privacy policy',
                'body_ar' => <<<'MD'
## ما الذي نجمعه

نجمع بيانات الحساب (الاسم ورقم الجوال والمدينة)، وبياناتك الصحية التي تقدمها في التقييم الطبي والمتابعة (القياسات والتاريخ المرضي والأدوية والأعراض والتحاليل)، وبيانات الاشتراك والمدفوعات والطلبات.

## كيف نستخدمها

نستخدم بياناتك لتقديم الاستشارات والخطة والمتابعة وطلبات الدواء، ولإدارة اشتراكك وخدمتك. **لا نستخدم بياناتك الصحية لأغراض التسويق.**

## من يطّلع عليها

تتاح بياناتك الصحية للموظفين المخوّلين فقط بحسب أدوارهم: الطبيب المعالج، وأخصائي التغذية المسند إليك، وفريق الرعاية. تطّلع الصيدلية على الوصفة اللازمة لتنفيذ طلبك، ويطّلع شريك التوصيل على بيانات التوصيل فقط. لا يطّلع فريق الإدارة والدعم على المحتوى الطبي. يُسجَّل الاطلاع على البيانات الحساسة في سجل تدقيق.

## موافقاتك

موافقاتك الطبية والتسويقية وموافقة التجديد التلقائي منفصلة، ويمكنك تغيير الموافقة التسويقية في أي وقت من ملفك الشخصي.

## الحماية والاحتفاظ

نحمي البيانات بالتشفير أثناء النقل، ونشفّر النصوص الصحية الحساسة عند التخزين. نحتفظ بالسجلات الطبية للمدة التي تفرضها الأنظمة المعمول بها في المملكة العربية السعودية.

## حقوقك

يمكنك طلب الاطلاع على بياناتك أو تصحيحها أو الاستفسار عن معالجتها عبر الدعم من حسابك.
MD,
                'body_en' => <<<'MD'
## What we collect

Account details (name, mobile number, city); the health information you give in the medical assessment and follow-up (measurements, medical history, medications, symptoms, lab results); and subscription, payment and order details.

## How we use it

We use your data to provide consultations, your plan, follow-up and medication orders, and to manage your subscription and service. **We do not use your health data for marketing.**

## Who can see it

Health data is available only to authorised staff according to their role: your treating doctor, the dietitian assigned to you, and the care team. The pharmacy sees the prescription needed to fulfil your order, and the delivery partner sees delivery details only. Administration and support staff cannot see medical content. Access to sensitive data is recorded in an audit log.

## Your consents

Medical, marketing and automatic-renewal consents are separate, and you can change your marketing consent at any time from your profile.

## Protection and retention

We protect data with encryption in transit and encrypt sensitive health text at rest. Medical records are kept for the period required by the regulations in force in the Kingdom of Saudi Arabia.

## Your rights

You can ask to access or correct your data, or ask how it is processed, through support in your account.
MD,
            ],
            [
                'slug' => 'terms',
                'title_ar' => 'الشروط والأحكام',
                'title_en' => 'Terms and conditions',
                'body_ar' => <<<'MD'
## الخدمة

اتزان منصة رقمية لإدارة الوزن بإشراف طبي. تُقدَّم الاستشارات عبر منصة الطب الاتصالي FTMS من أطباء واستشاريين مرخّصين.

## القرار الطبي

القرار الطبي للطبيب وحده؛ فهو يحدد ملاءمة العلاج والوصفة والجرعة. لا تصدر المنصة وصفات آلية ولا ترفع الجرعات تلقائيًا، ولا يجوز للعميل تعديل جرعته بنفسه.

## مسؤولية العميل

تلتزم بتقديم معلومات صحية صحيحة وكاملة، وبالتواصل مع فريقك عند أي عَرَض مقلق. المنصة ليست خدمة طوارئ؛ في الحالات الطارئة اتصل بالهلال الأحمر على 997.

## الاستشارة المجانية

يحصل العميل الجديد على استشارة واحدة مجانية، لا تلزمه بالاشتراك، ولا تعني بالضرورة صرف علاج.

## الاشتراك والدفع

تتوفر باقتان مدفوعتان. تظهر القيمة الإجمالية وما سيُخصم فعليًا قبل الدفع. الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك. انظر سياسة الاشتراك والتجديد والإلغاء والاسترداد.

## تعديل الشروط

قد نحدّث هذه الشروط، وسنوضح تاريخ آخر تحديث في أعلى الصفحة.
MD,
                'body_en' => <<<'MD'
## The service

Itizan is a digital platform for medically supervised weight management. Consultations are delivered through the FTMS telemedicine platform by licensed doctors and consultants.

## Medical decisions

Medical decisions belong to the doctor alone: the doctor decides whether a treatment, prescription and dose are suitable. The platform never issues automatic prescriptions or raises doses automatically, and clients must not change their own dose.

## Your responsibilities

You agree to provide accurate and complete health information and to contact your team about any worrying symptom. The platform is not an emergency service; in an emergency call the Saudi Red Crescent on 997.

## The free consultation

A new client receives one free consultation. It does not oblige you to subscribe and does not necessarily mean a treatment will be prescribed.

## Subscription and payment

Two paid packages are available. The total and the amount actually charged are shown before payment. Medication and lab tests are not included unless stated otherwise. See the subscription, renewal, cancellation and refund policy.

## Changes to these terms

We may update these terms; the date of the latest update is shown at the top of the page.
MD,
            ],
            [
                'slug' => 'subscription-renewal-cancellation-refund',
                'title_ar' => 'سياسة الاشتراك والتجديد والإلغاء والاسترداد',
                'title_en' => 'Subscription, renewal, cancellation and refund policy',
                'body_ar' => <<<'MD'
## الاشتراك

تتوفر باقتان مدفوعتان: نصف سنوية (6 أشهر) وسنوية (12 شهرًا). يبدأ الاشتراك عند نجاح الدفع، وتظهر مدته وعدد استشارات المتابعة المشمولة في حسابك.

## ما الذي يُخصم

قبل الدفع تظهر القيمة الإجمالية وما سيُخصم فعليًا مع توضيح شمول ضريبة القيمة المضافة. الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك.

## التجديد

التجديد التلقائي اختياري ويتطلب موافقة مستقلة منك. يمكنك إلغاء التجديد التلقائي في أي وقت من حسابك، ويستمر اشتراكك حتى نهاية مدته. عند الدفع عبر تابي أو تمارا لا يدعم الاشتراك التجديد التلقائي، ويكون التجديد بطلب جديد منك قبل انتهاء الاشتراك.

## الإلغاء

إلغاء التجديد لا يوقف الخدمة قبل نهاية المدة المدفوعة. لأي طلب إنهاء مبكر تواصل مع الدعم من حسابك.

## الاسترداد

تُراجَع طلبات الاسترداد وفق شروط الاسترداد المعتمدة وطبيعة الخدمة التي قُدّمت (مثل الاستشارات المستخدمة). يُردّ المبلغ المستحق إلى وسيلة الدفع نفسها وفق مدة معالجة مزود الدفع. تُضاف هنا مدد وشروط الاسترداد المعتمدة من الإدارة.
MD,
                'body_en' => <<<'MD'
## Subscription

Two paid packages are available: six months and twelve months. A subscription starts when payment succeeds, and its duration and included follow-up consultations are shown in your account.

## What is charged

Before payment you see the total and the amount actually charged, including whether VAT is included. Medication and lab tests are not included unless stated otherwise.

## Renewal

Automatic renewal is optional and needs your separate consent. You can turn it off at any time from your account and your subscription continues until the end of its term. When paying with Tabby or Tamara, automatic renewal is not supported; you renew with a new purchase before the subscription ends.

## Cancellation

Turning off renewal does not stop the service before the paid term ends. For any early-termination request, contact support from your account.

## Refunds

Refund requests are reviewed under the approved refund conditions and the service already provided (such as consultations used). Amounts due are returned to the original payment method within the payment provider's processing time. Approved refund periods and conditions are added here by management.
MD,
            ],
            [
                'slug' => 'delivery',
                'title_ar' => 'سياسة التوصيل',
                'title_en' => 'Delivery policy',
                'body_ar' => <<<'MD'
## كيف يعمل الطلب

تطلب الدواء أو المكمل من حسابك. تُرسل الوصفة إلى صيدلية مرخّصة عند الحاجة. تعرض الصيدلية توفر المنتج وسعره وموعد التوصيل المتوقع، ولا يبدأ التجهيز إلا بعد موافقتك على السعر.

## الصرف

يُصرف الدواء وفق الوصفة والمتطلبات ذات الصلة.

## مدة التوصيل

يعتمد وقت التوصيل على المدينة وتوفر المنتج وتأكيد الصيدلية.

## التوصيل المجاني

التوصيل مجاني حيثما انطبق، وفق التغطية وتوفر المنتج. تظهر رسوم التوصيل (إن وجدت) قبل موافقتك.

## متابعة الطلب

تتابع تحديثات الطلب من حسابك حتى التسليم.
MD,
                'body_en' => <<<'MD'
## How an order works

You order a medication or supplement from your account. The prescription is sent to a licensed pharmacy when needed. The pharmacy shows availability, price and the expected delivery time, and preparation starts only after you approve the price.

## Dispensing

Medication is dispensed according to the prescription and related requirements.

## Delivery time

Delivery time depends on the city, product availability and pharmacy confirmation.

## Free delivery

Delivery is free where applicable, subject to coverage and product availability. Any delivery fee is shown before you approve.

## Tracking

You follow order updates in your account until delivery.
MD,
            ],
            [
                'slug' => 'patient-rights',
                'title_ar' => 'حقوق المريض',
                'title_en' => 'Patient rights',
                'body_ar' => <<<'MD'
لك الحق في:

- الحصول على رعاية محترمة دون لوم أو تمييز.
- معرفة اسم الطبيب وتخصصه وترخيصه بعد التحقق منها.
- فهم حالتك والخطة والخيارات المتاحة بلغة واضحة.
- الموافقة على العلاج أو رفضه، وطلب رأي آخر.
- سرية بياناتك الصحية وعدم استخدامها للتسويق.
- الاطلاع على بياناتك وطلب تصحيحها.
- تقديم شكوى ومتابعتها حتى الحل.
- التوجيه إلى الرعاية الحضورية أو الطوارئ عند الحاجة.
MD,
                'body_en' => <<<'MD'
You have the right to:

- Respectful care without blame or discrimination.
- Know your doctor's name, specialty and licence once verified.
- Understand your condition, your plan and your options in clear language.
- Consent to or refuse treatment, and ask for a second opinion.
- Confidentiality of your health data, which is never used for marketing.
- Access your data and ask for corrections.
- Raise a complaint and follow it until it is resolved.
- Be directed to in-person care or emergency services when needed.
MD,
            ],
            [
                'slug' => 'complaints',
                'title_ar' => 'سياسة الشكاوى',
                'title_en' => 'Complaints policy',
                'body_ar' => <<<'MD'
## كيف تقدّم شكوى

من حسابك اختر «الدعم والشكاوى» ثم «تقديم شكوى»، أو تواصل معنا عبر واتساب وسنحوّلك إلى الحساب لتسجيلها. لا ترسل معلوماتك الصحية الحساسة عبر واتساب.

## ماذا يحدث بعد ذلك

نستلم شكواك، ويراجعها فريق الدعم، ونرد عليك داخل حسابك وتتابع حالتها حتى الحل. إن كانت الشكوى تتعلق بالجانب الطبي فقد تُحال إلى الطبيب أو المسؤول الطبي المختص.

## الطوارئ

الشكاوى ليست مسارًا للحالات الطارئة؛ اتصل بالهلال الأحمر على 997.
MD,
                'body_en' => <<<'MD'
## How to file a complaint

From your account choose "Support and complaints", then "File a complaint", or contact us on WhatsApp and we will direct you to your account to record it. Do not send sensitive health details over WhatsApp.

## What happens next

We receive your complaint, the support team reviews it, and we reply inside your account where you can follow its status until it is resolved. Complaints about clinical matters may be referred to the responsible doctor or medical lead.

## Emergencies

Complaints are not a route for emergencies; call the Saudi Red Crescent on 997.
MD,
            ],
            [
                'slug' => 'medical-disclaimer',
                'title_ar' => 'الإخلاء الطبي',
                'title_en' => 'Medical disclaimer',
                'body_ar' => <<<'MD'
- المحتوى والمعلومات في الموقع للتوعية ولا تغني عن الاستشارة الطبية.
- لا يوجد وعد بنتائج محددة أو سريعة؛ تختلف الاستجابة من شخص لآخر.
- القرار الطبي بشأن العلاج والوصفة والجرعة للطبيب وحده بعد تقييم حالتك.
- لا تغيّر جرعتك ولا توقف دواءك دون الرجوع إلى طبيبك.
- اتزان ليست خدمة طوارئ. في الحالات الطارئة اتصل بالهلال الأحمر على 997 أو توجه إلى أقرب طوارئ.
MD,
                'body_en' => <<<'MD'
- Content on this site is for awareness and does not replace medical consultation.
- No specific or rapid results are promised; response differs from person to person.
- Medical decisions about treatment, prescription and dose belong to the doctor alone after assessing your condition.
- Do not change your dose or stop your medication without consulting your doctor.
- Itizan is not an emergency service. In an emergency call the Saudi Red Crescent on 997 or go to the nearest emergency department.
MD,
            ],
        ];
    }
}
