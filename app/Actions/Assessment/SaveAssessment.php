<?php

namespace App\Actions\Assessment;

use App\Actions\Consent\RecordConsent;
use App\Actions\Progress\RecordMeasurement;
use App\Enums\AssessmentStatus;
use App\Enums\ConsentType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\MedicalAssessment;
use App\Models\User;
use App\Support\Bmi;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveAssessment
{
    /** Columns the client may fill in. Status and review fields are never taken from input. */
    public const FIELDS = [
        'height_cm', 'weight_kg', 'waist_cm', 'target_weight_kg', 'pregnancy_status', 'is_breastfeeding',
        'medical_history', 'chronic_conditions', 'current_medications', 'allergies', 'past_surgeries',
        'previous_treatments', 'current_symptoms', 'available_labs', 'goals',
    ];

    public function __construct(
        private readonly RecordConsent $consents,
        private readonly RecordMeasurement $measurements,
    ) {}

    /** Saves a draft, or submits the assessment for the doctor's review when $submit is true. */
    public function handle(User $client, array $data, bool $submit = false): MedicalAssessment
    {
        return DB::transaction(function () use ($client, $data, $submit) {
            $assessment = MedicalAssessment::query()
                ->where('user_id', $client->id)
                ->whereIn('status', [AssessmentStatus::Draft->value, AssessmentStatus::Submitted->value, AssessmentStatus::NeedsInfo->value])
                ->lockForUpdate()
                ->first() ?? new MedicalAssessment(['user_id' => $client->id, 'status' => AssessmentStatus::Draft]);

            if (! $assessment->status->isEditableByClient()) {
                throw new BusinessRuleViolation(__('تقييمك قيد مراجعة الطبيب حاليًا ولا يمكن تعديله.'));
            }

            $assessment->fill(Arr::only($data, self::FIELDS));
            $this->applySafetyAnswers($assessment, $data['safety_answers'] ?? []);

            if ($assessment->height_cm && $assessment->weight_kg) {
                $assessment->bmi = Bmi::calculate($assessment->weight_kg, $assessment->height_cm);
            }

            if ($submit) {
                $this->consents->handle($client, ConsentType::MedicalData);
                $assessment->status = AssessmentStatus::Submitted;
                $assessment->submitted_at = now();
                $assessment->reviewer_notes = null;
            }

            $assessment->save();

            if ($submit && $assessment->weight_kg) {
                $this->measurements->handle($client, [
                    'measured_on' => today(),
                    'weight_kg' => $assessment->weight_kg,
                    'waist_cm' => $assessment->waist_cm,
                    'note' => __('من التقييم الطبي'),
                ]);
            }

            return $assessment;
        });
    }

    /** @param  array<string, string>  $answers question key => "yes" | "no" */
    private function applySafetyAnswers(MedicalAssessment $assessment, array $answers): void
    {
        $questions = config('itizan.safety_questions');
        $clean = [];
        $flagged = false;

        foreach ($questions as $key => $question) {
            $answer = ($answers[$key] ?? null) === 'yes' ? 'yes' : (($answers[$key] ?? null) === 'no' ? 'no' : null);
            $clean[$key] = $answer;
            $flagged = $flagged || ($answer === 'yes' && $question['red_flag']);
        }

        $assessment->safety_answers = $clean;
        $assessment->has_red_flags = $flagged;
    }
}
