<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Service 2 tidak memerlukan seed: data booking tercipta melalui API.
     */
    public function run(): void
    {
        // Tidak ada seeder — semua data tercipta saat request POST /api/bookings.
    }
}
