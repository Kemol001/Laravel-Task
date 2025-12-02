<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Hold extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'qty', 'expires_at', 'status', 'used_at'];

    protected $casts = [
        'qty' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || $this->status !== 'active';
    }

    public function isValid(): bool
    {
        return $this->status === 'active' && 
               $this->expires_at->isFuture() && 
               is_null($this->used_at);
    }

    public function markAsUsed(): bool
    {
        return DB::transaction(function () {
            $updated = DB::table('holds')
                ->where('id', $this->id)
                ->where('status', 'active')
                ->whereNull('used_at')
                ->update([
                    'status' => 'used',
                    'used_at' => now(),
                ]);

            if ($updated) {
                $this->status = 'used';
                $this->used_at = now();
                $this->product->invalidateStockCache();
                Log::info("Hold marked as used", ['hold_id' => $this->id]);
                return true;
            }

            return false;
        });
    }

    public function release(): bool
    {
        return DB::transaction(function () {
            $updated = DB::table('holds')
                ->where('id', $this->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            if ($updated) {
                $this->status = 'expired';
                $this->product->invalidateStockCache();
                Log::info("Hold released", ['hold_id' => $this->id, 'qty' => $this->qty]);
                return true;
            }

            return false;
        });
    }
}