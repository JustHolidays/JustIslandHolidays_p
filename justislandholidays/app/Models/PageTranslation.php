<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Composite key (page_id, lang): write with updateOrInsert(), not save(). */
class PageTranslation extends Model
{
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['page_id', 'lang', 'title', 'body_md', 'meta_title', 'meta_description'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
