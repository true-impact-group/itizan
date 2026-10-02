<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
class WeightPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'start_weight_kg' => 'float',
            'target_weight_kg' => 'float',
            'start_waist_cm' => 'float',
            'target_waist_cm' => 'float',
            'next_steps' => 'array',
            'starts_on' => 'date',
            'review_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function dietitian(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'dietitian_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(MedicalAssessment::class, 'assessment_id');
    }

    /**
     * Progress towards the goal. Direction-agnostic so a plan that aims to gain weight works too.
     *
     * @return array{change: float, remaining: float, percent: int}
     */
    public function progressAt(float $currentWeightKg): array
    {
        $total = $this->target_weight_kg - $this->start_weight_kg;
        $done = $currentWeightKg - $this->start_weight_kg;
        $percent = $total == 0.0 ? 100 : (int) round(max(0, min(1, $done / $total)) * 100);

        // Distance still to go in the direction of the goal; never negative once the goal is reached.
        $direction = $total <=> 0.0;
        $remaining = $direction === 0 ? 0.0 : max(0.0, ($this->target_weight_kg - $currentWeightKg) * $direction);

        return [
            'change' => round($done, 1),
            'remaining' => round($remaining, 1),
            'percent' => $percent,
        ];
    }
}
