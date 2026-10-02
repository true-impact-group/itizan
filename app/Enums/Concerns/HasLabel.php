<?php

namespace App\Enums\Concerns;

/**
 * Gives a backed enum a translated label and helper lists for forms/validation.
 * Labels are Arabic source strings resolved through lang/en.json for English.
 */
trait HasLabel
{
    /** @return array<string, string> value => Arabic label */
    abstract public static function labels(): array;

    public function label(): string
    {
        return __(static::labels()[$this->value] ?? $this->value);
    }

    /** @return array<string, string> value => translated label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
