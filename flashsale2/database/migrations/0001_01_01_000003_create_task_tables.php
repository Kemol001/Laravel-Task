<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('stock');                 
            $table->integer('price');                 
            $table->timestamps();
        });

            Schema::create('holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('qty');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->enum('status', ['active', 'expired', 'used'])->default('active');
            $table->timestamps();
        });

            Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hold_id');
            $table->foreign('hold_id')->references('id')->on('holds')->cascadeOnDelete();
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
        });

            Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->json('payload');
            $table->uuid('order_id')->nullable();
            $table->enum('status', ['success', 'failure'])->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('holds');
        Schema::dropIfExists('products');
    }
};