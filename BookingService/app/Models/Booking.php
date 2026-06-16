<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'order_code',
        'concert_id',
        'user_id',
        'quantity',
        'amount',
        'status',
    ];

    protected $casts = [
        'concert_id' => 'integer',
        'quantity'   => 'integer',
        'amount'     => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Cari booking berdasarkan order_code (pencarian paling sering di API ini).
     */
    public function scopeForOrder($query, string $orderCode)
    {
        return $query->where('order_code', $orderCode);
    }
}
