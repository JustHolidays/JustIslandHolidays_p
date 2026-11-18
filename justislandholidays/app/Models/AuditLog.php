<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only. Entries are never edited or deleted from the admin screens. */
class AuditLog extends Model
{
    protected $table = 'audit_log';

    const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'entity', 'entity_id', 'detail'];

    protected function casts(): array
    {
        return ['detail' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
