<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

/*
|----------------------------------------------------------------------
| Routes API — Service 2 (Booking & Queue)
| Prefix otomatis: /api  (lihat registrasi di bootstrap/app.php)
|----------------------------------------------------------------------
*/

Route::prefix('bookings')->group(function () {
    Route::post('/', [BookingController::class, 'store']);
    Route::get('/{order_code}', [BookingController::class, 'show'])->where('order_code', '[A-Za-z0-9\-]+');
    Route::patch('/{order_code}/status', [BookingController::class, 'updateStatus'])->where('order_code', '[A-Za-z0-9\-]+');
});
