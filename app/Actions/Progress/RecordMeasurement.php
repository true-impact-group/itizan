<?php

namespace App\Actions\Progress;

use App\Models\Measurement;
use App\Models\User;

class RecordMeasurement
{
    /** @param  array{measured_on: mixed, weight_kg: float|string, waist_cm?: float|string|null, note?: ?string}  $data */
    public function handle(User $client, array $data, string $source = 'client'): Measurement
    {
        return Measurement::create([
            'user_id' => $client->id,
            'measured_on' => $data['measured_on'],
            'weight_kg' => $data['weight_kg'],
            'waist_cm' => $data['waist_cm'] ?? null,
            'note' => $data['note'] ?? null,
            'source' => $source,
        ]);
    }
}
