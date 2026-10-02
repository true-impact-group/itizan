<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\AssessmentStatus;
use App\Enums\PlanStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\MedicalAssessment;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WeightPlan;

/**
 * Works out where a client is in the ten-step journey and what the single most useful next step is.
 * Pure read logic: it never changes data.
 */
class ClientJourney
{
    /** @var array<string, mixed> per-instance cache of the facts below */
    private array $facts = [];

    /**
     * @return list<array{key: string, label: string, done: bool}>
     */
    public function steps(User $user): array
    {
        $f = $this->facts($user);

        $done = [
            'discover' => true,
            'free_consultation' => $f['freeIntro'] !== null,
            'details' => true,
            'attend' => $f['freeIntro']?->status === AppointmentStatus::Completed,
            'assessment' => $f['assessment'] !== null && ! in_array($f['assessment']->status, [AssessmentStatus::Draft, AssessmentStatus::NeedsInfo], true),
            'subscribe' => $f['everPaid'],
            'plan' => $f['plan'] !== null,
            'treatment' => $f['hasPrescription'],
            'order' => $f['hasOrder'],
            'manage' => $f['currentSubscription'] !== null,
        ];

        $labels = [
            'discover' => 'التعرف على اتزان',
            'free_consultation' => 'اختيار الاستشارة المجانية',
            'details' => 'تسجيل البيانات الأولية',
            'attend' => 'حضور الاستشارة',
            'assessment' => 'إكمال التقييم الطبي',
            'subscribe' => 'اختيار الاشتراك',
            'plan' => 'متابعة الخطة',
            'treatment' => 'متابعة العلاج',
            'order' => 'طلب الدواء أو المكمل',
            'manage' => 'إدارة الاشتراك',
        ];

        return array_map(
            fn (string $key) => ['key' => $key, 'label' => $labels[$key], 'done' => $done[$key]],
            array_keys($labels),
        );
    }

    /** @return array{title: string, body: string, cta: string, url: string} */
    public function nextStep(User $user): array
    {
        $f = $this->facts($user);
        $assessment = $f['assessment'];

        if ($f['dueDose'] !== null) {
            return $this->step('لديك جرعة مستحقة', 'سجّل جرعتك بعد أخذها أو سجّلها كجرعة منسية ليطّلع فريقك على التزامك.', 'تسجيل الجرعة', route('account.medications.show', $f['dueDose']));
        }

        if ($assessment?->status === AssessmentStatus::NeedsInfo) {
            return $this->step('يحتاج الطبيب إلى معلومات إضافية', 'راجع ملاحظة الطبيب في تقييمك وأضف المعلومات المطلوبة.', 'فتح التقييم', route('account.assessment.show'));
        }

        if ($f['upcoming'] !== null) {
            return $this->step('موعدك القادم', __('استشارتك في :time.', ['time' => $f['upcoming']->scheduled_at->translatedFormat('l j F، h:i A')]), 'تفاصيل الموعد', route('account.appointments.show', $f['upcoming']));
        }

        if ($f['freeIntro'] === null && ! $f['everPaid']) {
            return $this->step('ابدأ باستشارتك المجانية', 'استشارة مجانية واحدة للتعرف على البرنامج والخطوة المناسبة لك قبل اختيار اشتراكك.', 'احجز الاستشارة المجانية', route('account.appointments.create', ['type' => AppointmentType::FreeIntro->value]));
        }

        if ($f['currentSubscription'] === null) {
            return $this->step('اختر الباقة المناسبة', 'بعد الاستشارة، اختر الباقة التي تناسبك لمتابعة برنامجك بإشراف طبي.', 'عرض الباقات', route('account.subscription.show'));
        }

        if ($assessment === null || $assessment->status === AssessmentStatus::Draft) {
            return $this->step('أكمل تقييمك الطبي', 'يحتاج الطبيب إلى معلوماتك الصحية وأهدافك ليحدد الخطوات المناسبة لك.', 'إكمال التقييم', route('account.assessment.show'));
        }

        if ($assessment->status === AssessmentStatus::Submitted) {
            return $this->step('تقييمك عند الطبيب', 'سيراجع الطبيب معلوماتك ويخبرك بالخطوة التالية. سنرسل لك تنبيهًا عند اكتمال المراجعة.', 'عرض التقييم', route('account.assessment.show'));
        }

        if ($f['plan'] === null) {
            return $this->step('فريقك يُعدّ خطتك', 'سنخبرك فور جاهزية خطة إدارة الوزن الخاصة بك.', 'عرض التقييم', route('account.assessment.show'));
        }

        return $this->step('سجّل قياساتك', 'كل خطوة في المتابعة تساعد فريقك على فهم تقدمك وتقديم دعم أنسب لك.', 'إضافة قياس', route('account.progress.index'));
    }

    /** @return array{title: string, body: string, cta: string, url: string} */
    private function step(string $title, string $body, string $cta, string $url): array
    {
        return ['title' => __($title), 'body' => __($body), 'cta' => __($cta), 'url' => $url];
    }

    /** @return array<string, mixed> */
    private function facts(User $user): array
    {
        return $this->facts[$user->id] ??= [
            'freeIntro' => Appointment::query()
                ->where('user_id', $user->id)
                ->where('type', AppointmentType::FreeIntro->value)
                ->where('status', '!=', AppointmentStatus::Cancelled->value)
                ->latest('scheduled_at')->first(),
            'upcoming' => Appointment::query()->where('user_id', $user->id)->upcoming()->orderBy('scheduled_at')->first(),
            'currentSubscription' => Subscription::query()->where('user_id', $user->id)->current()->latest('ends_at')->first(),
            'everPaid' => Subscription::query()->where('user_id', $user->id)
                ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Expired->value])->exists(),
            'assessment' => MedicalAssessment::query()->where('user_id', $user->id)->latest('id')->first(),
            'plan' => WeightPlan::query()->where('user_id', $user->id)->where('status', PlanStatus::Active->value)->first(),
            'hasPrescription' => Prescription::query()->where('user_id', $user->id)->exists(),
            'hasOrder' => Order::query()->where('user_id', $user->id)->exists(),
            'dueDose' => Prescription::query()->where('user_id', $user->id)->active()
                ->where('next_dose_at', '<=', now()->addHours(2))->orderBy('next_dose_at')->first(),
        ];
    }
}
