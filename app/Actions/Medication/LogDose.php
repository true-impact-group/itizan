<?php

namespace App\Actions\Medication;

use App\Actions\Support\RequestContact;
use App\Enums\ContactTarget;
use App\Enums\DoseStatus;
use App\Enums\PrescriptionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\DoseLog;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lets the client record a dose as taken or missed. It never changes the dose itself:
 * the dose, interval and questions are set by the doctor only.
 */
class LogDose
{
    /** The client may record a dose this many hours before its scheduled time. */
    public const EARLY_WINDOW_HOURS = 2;

    public function __construct(private readonly RequestContact $contacts) {}

    /** @param  array<int, string>  $answers answers to the pre-dose questions, "yes" | "no", by question index */
    public function handle(User $client, Prescription $prescription, DoseStatus $status, array $answers = [], ?string $note = null): DoseLogResult
    {
        if ($prescription->user_id !== $client->id || ! $prescription->isActive() || $prescription->next_dose_at === null) {
            throw new BusinessRuleViolation(__('لا توجد جرعة مجدولة لتسجيلها.'));
        }

        if (now()->lt($prescription->next_dose_at->copy()->subHours(self::EARLY_WINDOW_HOURS))) {
            throw new BusinessRuleViolation(__('لم يحن موعد الجرعة بعد.'));
        }

        if ($status === DoseStatus::Taken && $this->hasAlertingAnswer($prescription, $answers)) {
            $this->contacts->handle(
                $client,
                ContactTarget::Doctor,
                __('إجابة في الاستبيان قبل الجرعة تحتاج إلى مراجعة الطبيب قبل أخذ الجرعة.'),
                urgent: true,
                source: 'pre_dose',
            );

            return new DoseLogResult(log: null, heldForDoctor: true);
        }

        try {
            $log = DB::transaction(function () use ($prescription, $status, $answers, $note, $client) {
                $locked = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
                $scheduled = $locked->next_dose_at;

                if ($scheduled === null || ! $locked->isActive()) {
                    throw new BusinessRuleViolation(__('لا توجد جرعة مجدولة لتسجيلها.'));
                }

                $log = DoseLog::create([
                    'prescription_id' => $locked->id,
                    'user_id' => $client->id,
                    'scheduled_for' => $scheduled,
                    'status' => $status,
                    'logged_at' => now(),
                    'pre_dose_answers' => $answers ?: null,
                    'note' => $note,
                ]);

                $this->advanceSchedule($locked, $scheduled);

                return $log;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation(__('سبق تسجيل هذه الجرعة.'));
        }

        return new DoseLogResult($log);
    }

    /** Moves to the next scheduled dose, or completes the prescription when its end date has passed. */
    private function advanceSchedule(Prescription $prescription, Carbon $scheduled): void
    {
        $next = $scheduled->copy()->addDays(max($prescription->interval_days, 1));

        if ($prescription->ends_on && $next->toDateString() > $prescription->ends_on->toDateString()) {
            $prescription->update(['status' => PrescriptionStatus::Completed, 'next_dose_at' => null]);

            return;
        }

        $prescription->update(['next_dose_at' => $next]);
    }

    private function hasAlertingAnswer(Prescription $prescription, array $answers): bool
    {
        foreach ($prescription->preDoseQuestions() as $index => $question) {
            if (($answers[$index] ?? null) === ($question['alert_on'] ?? 'yes')) {
                return true;
            }
        }

        return false;
    }
}
