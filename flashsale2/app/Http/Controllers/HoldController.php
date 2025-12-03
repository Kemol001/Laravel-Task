<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Product;
use App\Jobs\HoldExpiryJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class HoldController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        $hold = DB::transaction(function () use ($request) {
            $product = Product::lockForUpdate()->find($request->product_id);
            $available = $product->getAvailableStock();

            if ($request->qty > $available) {
                throw ValidationException::withMessages([
                    'qty' => 'Not enough stock available'
                ]);
            }

            $hold = Hold::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'product_id' => $product->id,
                'qty' => $request->qty,
                'expires_at' => now()->addMinutes(2),
            ]);

            // Invalidate cached stock
            $product->invalidateStockCache();

            return $hold;
        });

        // Dispatch expiry job
        HoldExpiryJob::dispatch($hold)->delay($hold->expires_at);

        return response()->json([
            'hold_id' => $hold->id,
            'expires_at' => $hold->expires_at,
        ]);
    }
}
