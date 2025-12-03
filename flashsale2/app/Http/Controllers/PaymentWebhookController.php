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
        // Validate the webhook, but do NOT require order to exist yet
        $request->validate([
            'idempotency_key' => 'required|string',
            'order_id' => 'required|uuid',
            'status' => 'required|in:success,failure',
            'payload' => 'sometimes|array',
        ]);

        DB::transaction(function () use ($request) {
            // Skip duplicate webhook
            $exists = WebhookLog::where('idempotency_key', $request->idempotency_key)->first();
            if ($exists) return;

            // Try to find the order
            $order = Order::lockForUpdate()->find($request->order_id);

            if ($order) {
                // Apply immediately if order exists
                if ($request->status === 'success') {
                    $order->markAsPaid();
                } else {
                    $order->cancel();
                }
            }

            // Log webhook anyway (even if order doesn't exist yet)
            WebhookLog::create([
                'idempotency_key' => $request->idempotency_key,
                'order_id' => $request->order_id,
                'status' => $request->status, // store status for later processing
                'payload' => $request->payload ?? [],
                'processed_at' => $order ? now() : null, // mark processed if applied
            ]);
        });

        return response()->json(['message' => 'webhook processed']);
    }
}