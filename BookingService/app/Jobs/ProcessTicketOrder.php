<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessTicketOrder — Job yang dipublikasikan Service 2 ke queue RabbitMQ
 * `ticket_orders`, lalu dikonsumsi Service 3.
 *
 * !!! PENTING (lihat rencana §2.3) !!!
 * Class ini HARUS IDENTIK (namespace, nama class, nama & urutan properti publik,
 * serta konstruktor) dengan class bernama sama di Service 3. Sebab paket
 * laravel-queue-rabbitmq melakukan serialisasi nama class pada pesan, dan
 * Service 3 me-resolve-nya saat mengonsumsi. Bila berbeda → pesan gagal.
 *
 * Perbedaan satu-satunya: implementasi handle().
 *  - Service 2 (PUBLISHER): handle() no-op — Service 2 hanya men-DISPATCH,
 *    tidak mengkonsumsi. Pesan dikirim ke RabbitMQ oleh dispatch().
 *  - Service 3 (CONSUMER):  handle() berisi logika penerbitan tiket.
 *
 * Properti publik di bawah ini = payload pesan (rencana §8.4).
 */
class ProcessTicketOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $order_code;
    public int $concert_id;
    public string $user_id;
    public int $quantity;
    public int $amount;

    public int $tries = 3;

    /**
     * @param  string  $order_code  Kode unik pesanan (ORD-YYYYMMDD-XXXX).
     * @param  int     $concert_id  ID konser di katalog (Service 1).
     * @param  string  $user_id     Identitas pemesan.
     * @param  int     $quantity    Jumlah tiket yang dipesan.
     * @param  int     $amount      Total harga (quantity * price) dalam rupiah penuh.
     */
    public function __construct(
        string $order_code,
        int $concert_id,
        string $user_id,
        int $quantity,
        int $amount
    ) {
        $this->order_code = $order_code;
        $this->concert_id = $concert_id;
        $this->user_id = $user_id;
        $this->quantity = $quantity;
        $this->amount = $amount;

        // Set koneksi & queue via trait methods (menghindari konflik properti).
        $this->onConnection('rabbitmq');
        $this->onQueue('ticket_orders');
    }

    /**
     * Service 2 adalah PUBLISHER — pesan dikirim ke RabbitMQ saat dispatch(),
     * bukan saat handle() dijalankan. handle() di service ini tidak boleh
     * dipanggil dalam operasi normal. Dibiarkan kosong (no-op) demi kesamaan
     * kontrak class dengan Service 3.
     */
    public function handle(): void
    {
        Log::info('[S2] ProcessTicketOrder.handle() terpanggil (tidak diharapkan di publisher).', [
            'order_code' => $this->order_code,
        ]);
    }

    /**
     * Dipanggil bila pesan gagal diproses (hanya relevan di consumer).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[S2] ProcessTicketOrder gagal.', [
            'order_code' => $this->order_code,
            'error' => $exception->getMessage(),
        ]);
    }
}
