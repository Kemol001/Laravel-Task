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

            if (!$hold || !$hold->isValid()) {
                throw ValidationException::withMessages([
                    'hold_id' => 'Hold is invalid, expired, or already used'
                ]);
            }

            $hold->markAsUsed();

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