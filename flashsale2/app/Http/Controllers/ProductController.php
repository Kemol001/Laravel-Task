<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function show($id)
    {
        $product = Product::findOrFail($id);

        // Count active holds that have not expired
        $activeHolds = DB::table('holds')
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->sum('qty');

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'total_stock' => $product->stock,
            'available_stock' => $product->stock - $activeHolds,
        ]);
    }
}