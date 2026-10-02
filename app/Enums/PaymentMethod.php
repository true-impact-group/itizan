<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PaymentMethod: string
{
    use HasLabel;

    case ApplePay = 'apple_pay';
    case Mada = 'mada';
    case Tabby = 'tabby';
    case Tamara = 'tamara';

    public static function labels(): array
    {
        return ['apple_pay' => 'Apple Pay', 'mada' => 'مدى', 'tabby' => 'تابي', 'tamara' => 'تمارا'];
    }

    public function isInstallment(): bool
    {
        return in_array($this, [self::Tabby, self::Tamara], true);
    }

    public function isEnabled(): bool
    {
        return (bool) config('itizan.payments.methods.'.$this->value, false);
    }

    /** @return list<self> */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method) => $method->isEnabled()));
    }
}
