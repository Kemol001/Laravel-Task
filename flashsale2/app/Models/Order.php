<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'hold_id', 'status'];
    protected $attributes = [
        'status' => 'pending',
    ];

   protected static function booted()
    {
    static::created(function (Order $order) {
        $pendingWebhooks = WebhookLog::where('order_id', $order->id)
            ->whereNull('processed_at')
            ->get();

        foreach ($pendingWebhooks as $webhook) {
            if ($webhook->status === 'success') {
                $order->markAsPaid();
            } elseif ($webhook->status === 'failure') {
                $order->cancel();
            }

            $webhook->update(['processed_at' => now()]);
        }
    });
    }

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }

    public function webhookLogs()
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function markAsPaid(): bool
    {
        return DB::transaction(function () {
            if ($this->status !== 'pending') return false;

            $updated = DB::table('orders')
                ->where('id', $this->id)
                ->where('status', 'pending')
                ->update(['status' => 'paid']);

            if ($updated) {
                $this->status = 'paid';

                $hold = $this->hold()->with('product')->first();
                if ($hold && $hold->product) {
                    $hold->product->decrementStock($hold->qty);
                }

                Log::info("Order marked as paid", ['order_id' => $this->id]);
                return true;
            }

            return false;
        });
    }

    public function cancel(): bool
    {
        return DB::transaction(function () {
            if ($this->status !== 'pending') return false;

            $updated = DB::table('orders')
                ->where('id', $this->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            if ($updated) {
                $this->status = 'cancelled';

                $hold = $this->hold;
                if ($hold) {
                    $hold->release();
                }

                Log::info("Order cancelled", ['order_id' => $this->id]);
                return true;
            }

            return false;
        });
    }
}