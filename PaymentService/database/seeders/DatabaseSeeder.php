<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Service 3 tidak memerlukan seed: data tickets/payments tercipta saat
     * worker mengonsumsi antrean dari Service 2.
     */
    public function run(): void
    {
        // Tidak ada seeder — data tercipta saat worker memproses Job.
    }
}
