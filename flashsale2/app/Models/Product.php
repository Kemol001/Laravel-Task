<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $fillable = ['name', 'price', 'stock'];

    protected $casts = [
        'price' => 'integer',
        'stock' => 'integer',
    ];

    public function holds()
    {
        return $this->hasMany(Hold::class);
    }

    public function getAvailableStock(): int
    {
        return Cache::remember(
            "product:{$this->id}:available_stock",
            30, // 30 seconds cache
            fn() => $this->calculateAvailableStock()
        );
    }

    public function calculateAvailableStock(): int
    {
        $activeHolds = $this->holds()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->sum('qty');

        return max(0, $this->stock - $activeHolds);
    }

    public function invalidateStockCache(): void
    {
        Cache::forget("product:{$this->id}:available_stock");
    }

    public function decrementStock(int $qty): bool
    {
        $updated = DB::table('products')
            ->where('id', $this->id)
            ->where('stock', '>=', $qty)
            ->update(['stock' => DB::raw("stock - {$qty}")]);

        if ($updated) {
            $this->refresh();
            $this->invalidateStockCache();
            return true;
        }

        return false;
    }
}