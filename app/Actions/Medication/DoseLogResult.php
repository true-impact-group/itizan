<?php

namespace App\Actions\Medication;

use App\Models\DoseLog;

final readonly class DoseLogResult
{
    public function __construct(
        public ?DoseLog $log,
        /** True when a pre-dose answer asked the client to stop and talk to the doctor first. */
        public bool $heldForDoctor = false,
    ) {}
}
