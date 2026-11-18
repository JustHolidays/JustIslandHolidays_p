<?php

namespace App\Models\Concerns;

/**
 * For short text stored as JSON keyed by language, e.g. {"en":"Menorca","es":"Menorca"}.
 * Falls back to English when the requested language is missing.
 */
trait HasJsonTranslations
{
    public function translate(string $attribute, ?string $lang = null): ?string
    {
        $values = $this->{$attribute};

        if (! is_array($values)) {
            return null;
        }

        $lang ??= app()->getLocale();

        return $values[$lang] ?? $values['en'] ?? null;
    }
}
