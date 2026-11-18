<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Idempotency log for PayPal webhooks: the id is PayPal's event id. */
class PaypalEvent extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'event_type', 'received_at'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }
}
