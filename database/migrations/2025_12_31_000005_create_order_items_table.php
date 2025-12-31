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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id('order_items_id');
            $table->foreignId('orders_id')->constrained('orders', 'orders_id')->onDelete('cascade');
            $table->foreignId('obats_id')->constrained('obats', 'obats_id')->onDelete('cascade');
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->integer('subtotal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
