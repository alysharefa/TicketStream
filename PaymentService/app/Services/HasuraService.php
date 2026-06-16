<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HasuraService — jembatan Service 3 ke Service 1 (Hasura GraphQL).
 *
 * Dipakai worker untuk mengurangi kuota konser secara ATOMIK. Mutation
 * Hasura `update_concerts` dengan syarat `available_quota > 0` menjamin
 * kuota tidak pernah menjadi minus (no overselling). Bila `affected_rows = 0`,
 * berarti kuota sudah habis → tiket ditolak (SOLD_OUT).
 *
 * Rencana §2.4: pengurangan kuota DILAKUKAN DI CONSUMER (Service 3), bukan
 * di publisher (Service 2), agar proses berurutan & terhindar dari race condition.
 */
class HasuraService
{
    private string $url;
    private string $adminSecret;

    public function __construct()
    {
        $this->url = config('services.hasura.url', env('HASURA_URL'));
        $this->adminSecret = config('services.hasura.admin_secret', env('HASURA_ADMIN_SECRET'));
    }

    /**
     * Kurangi kuota konser secara atomik.
     *
     * @param  int  $concertId  ID konser di katalog (Service 1).
     * @return bool true bila kuota berhasil dikurangi (affected_rows = 1),
     *              false bila kuota habis (affected_rows = 0) ATAU terjadi error
     *              jaringan/Hasura. Pada error, false memicu retry job (--tries=3).
     */
    public function decrementQuota(int $concertId): bool
    {
        $query = <<<'GRAPHQL'
mutation KurangiKuota($id: Int!) {
  update_concerts(
    where: { id: { _eq: $id }, available_quota: { _gt: 0 } }
    _inc: { available_quota: -1 }
  ) {
    affected_rows
  }
}
GRAPHQL;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-hasura-admin-secret' => $this->adminSecret,
            ])
                ->timeout(10)
                ->post($this->url, [
                    'query' => $query,
                    'variables' => ['id' => $concertId],
                ]);

            if (! $response->successful()) {
                Log::error('[S3] Hasura HTTP gagal', [
                    'concert_id' => $concertId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();

            // Bila ada error GraphQL (mis. skema salah), anggap gagal.
            if (isset($data['errors'])) {
                Log::error('[S3] Hasura GraphQL error', [
                    'concert_id' => $concertId,
                    'errors' => $data['errors'],
                ]);
                return false;
            }

            $affected = $data['data']['update_concerts']['affected_rows'] ?? 0;

            return $affected === 1;
        } catch (\Throwable $e) {
            // Error koneksi/timeout: kembalikan false agar job di-retry (--tries=3).
            Log::error('[S3] Exception panggil Hasura', [
                'concert_id' => $concertId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
