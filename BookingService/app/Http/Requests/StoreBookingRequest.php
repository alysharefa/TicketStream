<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi payload `POST /api/bookings`.
 *
 * Perhatian: kita TIDAK memvalidasi kuota di sini (rencana §2.4). Pengecekan
 * kuota atomik dilakukan di Service 3 saat mengonsumsi antrean, agar tidak
 * terjadi overselling akibat race condition.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'concert_id' => ['required', 'integer', 'min:1'],
            'user_id'    => ['required', 'string', 'max:100'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:10'],
            'amount'     => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
