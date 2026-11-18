<?php

namespace App\Support;

/** Public colours as CSS custom properties, from the published Appearance settings (or the draft in preview). */
final class Theme
{
    public static function colours(): array
    {
        return Appearance::colours();
    }

    public static function cssVariables(): string
    {
        return collect(self::colours())
            ->map(fn ($hex, $name) => "--colour-{$name}: {$hex};")
            ->implode(' ');
    }
}
