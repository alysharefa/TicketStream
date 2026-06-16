<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel tickets — Service 3 (Payment & Ticket Issuing).
 * Menyimpan hasil akhir pemrosesan antrean: SUCCESS (tiket terbit) atau
 * SOLD_OUT/FAILED (tiket gagal). Kolom order_code mengikat tiket ke booking
 * di Service 2 (referensi logis lintas-database).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_code', 40);                   // ikat ke booking di S2
            $table->integer('concert_id');                      // referensi logis ke katalog S1
            $table->string('user_id', 100);
            $table->string('ticket_code', 40)->nullable()->unique(); // TKT-2026-XYZ99 (null bila gagal)
            $table->enum('status', ['SUCCESS', 'FAILED', 'SOLD_OUT']);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index('order_code');
            $table->index('user_id');
            $table->index('concert_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
