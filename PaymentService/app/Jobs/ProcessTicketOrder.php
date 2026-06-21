<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Ticket;
use App\Services\HasuraService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ProcessTicketOrder — Job yang dipublikasikan Service 2 ke queue RabbitMQ
 * `ticket_orders`, lalu dikonsumsi Service 3.
 *
 * !!! PENTING (lihat rencana §2.3) !!!
 * Class ini HARUS IDENTIK (namespace, nama class, nama & urutan properti publik,
 * serta konstruktor) dengan class bernama sama di Service 2. Sebab paket
 * laravel-queue-rabbitmq melakukan serialisasi nama class pada pesan, dan
 * Service 3 me-resolve-nya saat mengonsumsi. Bila berbeda → pesan gagal.
 *
 * Perbedaan satu-satunya: implementasi handle().
 *  - Service 2 (PUBLISHER): handle() no-op.
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
    public int $backoff = 5; // detik antar retry

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
     * Logika utama Service 3 (CONSUMER):
     * 1. Simulasi pembayaran.
     * 2. Kurangi kuota di Hasura secara atomik (rencana §2.4).
     * 3. Jika kuota tersedia → terbitkan tiket (SUCCESS).
     * 4. Jika kuota habis → tandai SOLD_OUT.
     * 5. Callback opsional ke Service 2 update status booking.
     */
    public function handle(HasuraService $hasura): void
    {
        Log::info('[S3] Memproses pesanan', [
            'order_code' => $this->order_code,
            'concert_id' => $this->concert_id,
            'quantity'   => $this->quantity,
        ]);

        // ── Langkah 1: Simulasi pembayaran ──────────────────────────────
        $payment = $this->simulatePayment();

        if (! $payment || $payment->status !== 'PAID') {
            $this->recordFailure('FAILED', 'Pembayaran gagal (simulasi).');
            return;
        }

        // ── Langkah 2: Kurangi kuota atomik di Hasura (satu per satu) ──
        // Loop per-tiket agar setiap kuota dikurangi & dicek secara individual.
        $issuedCount = 0;
        for ($i = 0; $i < $this->quantity; $i++) {
            $quotaOk = $hasura->decrementQuota($this->concert_id);

            if ($quotaOk) {
                $ticketCode = $this->generateTicketCode();
                Ticket::create([
                    'order_code'  => $this->order_code,
                    'concert_id'  => $this->concert_id,
                    'user_id'     => $this->user_id,
                    'ticket_code' => $ticketCode,
                    'status'      => 'SUCCESS',
                    'issued_at'   => now(),
                ]);
                $issuedCount++;
            } else {
                // Kuota habis — sisanya gagal.
                Ticket::create([
                    'order_code'  => $this->order_code,
                    'concert_id'  => $this->concert_id,
                    'user_id'     => $this->user_id,
                    'ticket_code' => null,
                    'status'      => 'SOLD_OUT',
                    'issued_at'   => null,
                ]);
                break; // Tidak perlu lanjut loop — kuota sudah habis.
            }
        }

        Log::info('[S3] Pesanan diproses', [
            'order_code'   => $this->order_code,
            'issued_count' => $issuedCount,
            'requested'    => $this->quantity,
        ]);

        // ── Langkah 5: Callback opsional ke Service 2 ─────────────────
        $this->callbackBookingStatus($issuedCount > 0 ? 'PROCESSED' : 'FAILED');
    }

    /**
     * Simulasi pembayaran sederhana (tidak ada gateway sungguhan).
     * Selalu berhasil — cukup untuk demonstrasi arsitektur.
     */
    private function simulatePayment(): ?Payment
    {
        return Payment::create([
            'order_code' => $this->order_code,
            'amount'     => $this->amount,
            'status'     => 'PAID',
            'paid_at'    => now(),
        ]);
    }

    /**
     * Membuat kode tiket unik format TKT-YYYY-XXXXX.
     * Loop menjamin keunikan (retry bila collision).
     */
    private function generateTicketCode(): string
    {
        do {
            $code = 'TKT-' . date('Y') . '-' . strtoupper(Str::random(5));
        } while (Ticket::where('ticket_code', $code)->exists());

        return $code;
    }

    /**
     * Catat kegagalan tanpa terbitkan tiket (pembayaran gagal, dsb).
     */
    private function recordFailure(string $status, string $reason): void
    {
        Ticket::create([
            'order_code'  => $this->order_code,
            'concert_id'  => $this->concert_id,
            'user_id'     => $this->user_id,
            'ticket_code' => null,
            'status'      => $status,
            'issued_at'   => null,
        ]);

        Log::warning("[S3] Pesanan gagal: {$reason}", [
            'order_code' => $this->order_code,
            'status'     => $status,
        ]);
    }

    /**
     * Callback opsional ke Service 2 — update status booking.
     * Fail-tolerant: gagal HTTP tidak menghentikan job.
     */
    private function callbackBookingStatus(string $status): void
    {
        $bookingUrl = config('services.booking_service.url');

        if (! $bookingUrl) {
            return;
        }

        try {
            Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(5)
                ->patch("{$bookingUrl}/{$this->order_code}/status", [
                    'status' => $status,
                ]);
        } catch (\Throwable $e) {
            // Callback gagal tidak kritis — job tetap berhasil.
            Log::warning('[S3] Callback ke Service 2 gagal', [
                'order_code' => $this->order_code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dipanggil bila pesan gagal setelah semua percobaan (--tries=3).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[S3] ProcessTicketOrder gagal setelah semua percobaan.', [
            'order_code' => $this->order_code,
            'error' => $exception->getMessage(),
        ]);

        // Catat tiket gagal di database agar bisa dicek via GraphQL.
        Ticket::firstOrCreate(
            ['order_code' => $this->order_code, 'concert_id' => $this->concert_id],
            [
                'user_id'     => $this->user_id,
                'ticket_code' => null,
                'status'      => 'FAILED',
            ]
        );

        $this->callbackBookingStatus('FAILED');
    }
}
