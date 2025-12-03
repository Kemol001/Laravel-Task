<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'idempotency_key',
        'payload',
        'order_id',
        'processed_at',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'status' => 'string', 
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}