<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Site-wide settings with a draft and publish flow (Appearance page). */
class SiteSetting extends Model
{
    protected $primaryKey = 'setting_key';

    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = null;

    protected $fillable = ['setting_key', 'value', 'draft_value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array', 'draft_value' => 'array'];
    }
}
