<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enquiry extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'enquiries';

    protected $fillable = [
        'guest_name', 'guest_email', 'guest_phone', 'check_in', 'check_out',
        'adults', 'children', 'message', 'lang', 'consent_given_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'consent_given_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    /** The reference people quote, from the saved id: E-000042 (20 Nov 2026). */
    public function reference(): string
    {
        return 'E-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** The id in a reference someone types: "E-000042", "e42" or "E 42". Null when it is not one. */
    public static function idFromReference(string $text): ?int
    {
        return preg_match('/^\s*E\s*-?\s*0*(\d{1,12})\s*$/i', $text, $m) ? (int) $m[1] : null;
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
