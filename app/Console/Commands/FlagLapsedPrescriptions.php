<?php

namespace App\Console\Commands;

use App\Actions\Notify\NotifyStaff;
use App\Enums\Role;
use App\Models\Prescription;
use App\Models\Subscription;
use App\Notifications\GenericNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('itizan:lapsed-prescriptions')]
#[Description('Tell the client, the prescribing doctor and the care team once when an active prescription outlives its subscription by the grace period')]
class FlagLapsedPrescriptions extends Command
{
    public function handle(NotifyStaff $notifyStaff): int
    {
        $flagged = 0;

        // Nothing is stopped here: stopping treatment is the doctor's decision. This only makes sure everyone knows.
        Prescription::query()->lapsed()->with(['user', 'practitioner.user'])->chunkById(100, function ($prescriptions) use (&$flagged) {
            foreach ($prescriptions as $prescription) {
                $lastEnd = Subscription::query()->where('user_id', $prescription->user_id)
                    ->whereIn('status', ['active', 'expired'])->max('ends_at');

                // Once per lapse: a later renewal and a new lapse are notified again.
                if ($prescription->lapse_notified_at !== null && $lastEnd !== null && $prescription->lapse_notified_at->gt($lastEnd)) {
                    continue;
                }

                $prescription->user->notify(new GenericNotification(
                    'انتهى اشتراكك',
                    'جدّد اشتراكك لمتابعة طلب دوائك. سيراجع طبيبك خطتك العلاجية.',
                    route('account.subscription.show'),
                ));
                $prescription->practitioner->user->notify(new GenericNotification(
                    'وصفة تحتاج مراجعة بعد انتهاء اشتراك العميل',
                    'انتهى اشتراك أحد عملائك وما زالت وصفته فعّالة. افتح لوحتك لمراجعتها.',
                    route('practitioner.dashboard'),
                ));

                $prescription->update(['lapse_notified_at' => now()]);
                $flagged++;
            }
        });

        if ($flagged > 0) {
            $notifyStaff->handle(Role::CareTeam, 'وصفات تحتاج مراجعة بعد انتهاء الاشتراك', 'يوجد عملاء انتهى اشتراكهم وما زالت وصفاتهم فعّالة.', route('care.dashboard'));
        }

        $this->info("Lapsed prescriptions flagged: {$flagged}");

        return self::SUCCESS;
    }
}
