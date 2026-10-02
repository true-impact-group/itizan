<?php

namespace App\Services\Payments;

final readonly class ChargeResult
{
    public function __construct(
        public string $reference,
        public string $checkoutUrl,
    ) {}
}
