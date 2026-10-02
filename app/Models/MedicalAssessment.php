<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\PregnancyStatus;
use App\Support\Bmi;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Free-text clinical answers are encrypted at rest; only the numbers needed for queries stay plain. */
#[Unguarded]
class MedicalAssessment extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'pregnancy_status' => PregnancyStatus::class,
            'is_breastfeeding' => 'boolean',
            'has_red_flags' => 'boolean',
            'height_cm' => 'float',
            'weight_kg' => 'float',
            'waist_cm' => 'float',
            'bmi' => 'float',
            'target_weight_kg' => 'float',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'medical_history' => 'encrypted',
            'chronic_conditions' => 'encrypted',
            'current_medications' => 'encrypted',
            'allergies' => 'encrypted',
            'past_surgeries' => 'encrypted',
            'previous_treatments' => 'encrypted',
            'current_symptoms' => 'encrypted',
            'available_labs' => 'encrypted',
            'goals' => 'encrypted',
            'safety_answers' => 'encrypted:array',
            'reviewer_notes' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function bmiCategory(): ?string
    {
        return $this->bmi !== null ? __(Bmi::category($this->bmi)) : null;
    }
}
