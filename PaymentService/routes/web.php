<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'S3 — Payment & Ticket Issuing',
        'status'  => 'running',
    ]);
});
