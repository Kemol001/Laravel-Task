<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $request->validate([
            'idempotency_key' => 'required|string',
            'order_id' => 'required|uuid|exists:orders,id',
            'status' => 'required|in:success,failure',
            'payload' => 'sometimes|array',
        ]);

        DB::transaction(function () use ($request) {
            // Check for duplicate webhook
            $exists = WebhookLog::where('idempotency_key', $request->idempotency_key)->first();
            if ($exists) return;

            $order = Order::lockForUpdate()->find($request->order_id);
            if (!$order) return;

            if ($request->status === 'success') {
                $order->markAsPaid();
            } else {
                $order->cancel();
            }

            WebhookLog::create([
                'idempotency_key' => $request->idempotency_key,
                'order_id' => $order->id,
                'payload' => $request->payload ?? [],
                'processed_at' => now(),
            ]);
        });

        return response()->json(['message' => 'webhook processed']);
    }
}