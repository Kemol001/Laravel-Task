<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    use HasUuids;

    protected $fillable = ['hold_id', 'status'];

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
            $updated = DB::table('orders')
                ->where('id', $this->id)
                ->where('status', 'pending')
                ->update(['status' => 'paid']);

            if ($updated) {
                $this->status = 'paid';
                
                // Load hold with product
                $hold = $this->hold()->with('product')->first();
                
                // Decrement actual stock now that payment is confirmed
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
            $updated = DB::table('orders')
                ->where('id', $this->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            if ($updated) {
                $this->status = 'cancelled';
                
                // Release the hold
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