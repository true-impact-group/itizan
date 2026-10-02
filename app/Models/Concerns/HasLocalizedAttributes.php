<?php

namespace App\Models\Concerns;

/**
 * Reads "{attribute}_ar" / "{attribute}_en" columns for the current locale,
 * falling back to Arabic (the primary language) when the translation is empty.
 */
trait HasLocalizedAttributes
{
    public function localized(string $attribute): ?string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return $this->getAttribute("{$attribute}_{$locale}") ?: $this->getAttribute("{$attribute}_ar");
    }
}
