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

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
