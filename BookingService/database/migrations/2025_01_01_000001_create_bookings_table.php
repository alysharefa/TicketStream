<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel bookings — Service 2 (Booking & Queue).
 * Mencatat tiap pesanan tiket yang masuk. Status awal PENDING; diubah menjadi
 * PROCESSED/FAILED oleh callback opsional dari Service 3 setelah antrean diproses.
 *
 * Catatan: kolom concert_id adalah REFERENSI LOGIS ke tabel concerts di Service 1
 * (PostgreSQL/Hasura), bukan foreign key lintas-database — sesuai pola
 * database-per-service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_code', 40)->unique();     // mis. ORD-20260615-AB12
            $table->integer('concert_id');                  // referensi logis ke katalog
            $table->string('user_id', 100);
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('amount')->default(0); // total harga (quantity * price)
            $table->enum('status', ['PENDING', 'PROCESSED', 'FAILED'])->default('PENDING');
            $table->timestamps();

            $table->index('concert_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
