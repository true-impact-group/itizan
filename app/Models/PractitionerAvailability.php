<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class PractitionerAvailability extends Model
{
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }
}
