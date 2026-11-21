<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Booking Notes for an affiliate (21 Nov 2026): Markdown a super admin writes on the
 * affiliate's admin page, shown under Check availability on their property pages. Kept per
 * language; a language without its own notes shows the English ones.
 */
final class BookingNotes
{
    public const MAX_LENGTH = 2000;

    public static function text(User $affiliate, string $lang = 'en'): ?string
    {
        $notes = (array) ($affiliate->affiliate_booking_notes ?? []);
        $text = trim((string) ($notes[$lang] ?? '')) ?: trim((string) ($notes['en'] ?? ''));

        return $text !== '' ? $text : null;
    }

    /** Safe HTML: raw HTML is removed and only safe links are kept. */
    public static function html(User $affiliate, string $lang = 'en'): ?string
    {
        $text = self::text($affiliate, $lang);

        return $text === null ? null : Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
