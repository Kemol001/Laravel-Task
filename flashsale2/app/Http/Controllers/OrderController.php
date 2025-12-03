<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'hold_id' => 'required|uuid|exists:holds,id',
        ]);

        $order = DB::transaction(function () use ($request) {
            $hold = Hold::lockForUpdate()->find($request->hold_id);

            // 1. Hold must be valid (active, not expired, not used)
            if (!$hold || !$hold->isValid()) {
                throw ValidationException::withMessages([
                    'hold_id' => 'Hold is invalid, expired, or already used'
                ]);
            }

            // 2. Prevent creating a second order from the same hold
            if (Order::where('hold_id', $hold->id)->exists()) {
                throw ValidationException::withMessages([
                    'hold_id' => 'Order already exists for this hold'
                ]);
            }

            // 3. Mark hold as used
            $hold->markAsUsed();

            // 4. Create and return the order
            return Order::create([
                'hold_id' => $hold->id,
            ]);
        });

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
        ]);
    }
}