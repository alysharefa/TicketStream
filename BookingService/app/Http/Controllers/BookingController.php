<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Jobs\ProcessTicketOrder;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * POST /api/bookings
     *
     * Mencatat pesanan (status PENDING), membuat order_code, lalu
     * mempublikasikan Job ProcessTicketOrder ke RabbitMQ. Langsung membalas
     * 202 tanpa menunggu pembayaran/pemrosesan — inilah yang membuat sistem
     * tetap responsif saat lonjakan trafik.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $orderCode = $this->generateOrderCode();

        $booking = Booking::create([
            'order_code' => $orderCode,
            'concert_id' => $data['concert_id'],
            'user_id'    => $data['user_id'],
            'quantity'   => $data['quantity'],
            'amount'     => $data['amount'] ?? 0,
            'status'     => 'PENDING',
        ]);

        // Publish pesan ke RabbitMQ (queue: ticket_orders).
        // Properti Job = payload pesan (rencana §8.4). Class identik dengan S3.
        ProcessTicketOrder::dispatch(
            $booking->order_code,
            $booking->concert_id,
            $booking->user_id,
            $booking->quantity,
            $booking->amount,
        );

        return response()->json([
            'message'    => 'Pesanan Anda sedang diantrekan',
            'order_code' => $booking->order_code,
        ], 202);
    }

    /**
     * GET /api/bookings/{order_code}
     *
     * Mengecek status booking di sisi Service 2. Untuk status tiket final
     * (termasuk ticket_code), klien memanggil GraphQL Service 3.
     */
    public function show(string $orderCode): JsonResponse
    {
        $booking = Booking::forOrder($orderCode)->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'order_code' => $booking->order_code,
            'concert_id' => $booking->concert_id,
            'user_id'    => $booking->user_id,
            'quantity'   => $booking->quantity,
            'amount'     => $booking->amount,
            'status'     => $booking->status,
            'created_at' => $booking->created_at,
        ]);
    }

    /**
     * PATCH /api/bookings/{order_code}/status  (opsional — callback Service 3)
     *
     * Dipanggil Service 3 setelah memproses antrean agar status booking
     * terbaru di sisi Service 2.
     */
    public function updateStatus(string $orderCode, Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:PENDING,PROCESSED,FAILED'],
        ]);

        $booking = Booking::forOrder($orderCode)->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking tidak ditemukan.',
            ], 404);
        }

        $booking->update(['status' => $request->input('status')]);

        return response()->json([
            'order_code' => $booking->order_code,
            'status'     => $booking->status,
        ]);
    }

    /**
     * Membuat order_code unik format ORD-YYYYMMDD-XXXX.
     * XXXX = kombinasi alfabet numerik (Str::random, uppercase). Loop menjamin
     * keunikan terhadap tabel.
     */
    private function generateOrderCode(): string
    {
        do {
            $code = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (Booking::where('order_code', $code)->exists());

        return $code;
    }
}
