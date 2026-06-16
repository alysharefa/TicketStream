<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'order_code',
        'concert_id',
        'user_id',
        'ticket_code',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'concert_id' => 'integer',
        'issued_at'  => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
