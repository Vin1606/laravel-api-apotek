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
        Schema::create('orders', function (Blueprint $table) {
            $table->id('orders_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('total_price');
            $table->string('shipping_address');
            $table->string('payment_method');
            $table->dateTime('paid_at');
            $table->unsignedBigInteger('confirmation_by');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->foreign('user_id')->references('users_id')->on('users')->onDelete('cascade');
            $table->foreign('confirmation_by')->references('users_id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
