<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; 

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->insert([
            'name' => 'Flash Sale Item',
            'stock' => 50,
            'price' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
