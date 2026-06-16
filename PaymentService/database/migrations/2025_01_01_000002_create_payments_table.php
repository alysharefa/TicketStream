<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel payments — Service 3.
 * Mencatat simulasi pembayaran tiap pesanan yang diproses worker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_code', 40);
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['PAID', 'FAILED']);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
