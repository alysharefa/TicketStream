# Service 3 — Payment & Ticket Issuing

Microservice **pemrosesan antrean & penerbitan tiket** untuk Sistem Pemesanan Tiket Konser.
Dibangun dengan **Laravel 12 + Lighthouse (GraphQL manual)** + **MySQL** + **RabbitMQ sebagai Consumer/Worker**.

> Peran: mengonsumsi pesan dari antrean RabbitMQ `ticket_orders` satu per satu,
> mensimulasikan pembayaran, mengurangi kuota di Hasura secara atomik (anti-overselling),
> lalu menerbitkan tiket. Hasilnya bisa dicek via GraphQL endpoint (`POST /graphql`).

---

## Teknologi
- Laravel 12 (PHP 8.3)
- **Lighthouse** — GraphQL server manual (skema & resolver ditulis sendiri)
- MySQL 8 — database `db_ticket_issuing`
- RabbitMQ — broker pesan (Consumer/Worker)
- Paket: `nuwave/lighthouse`, `vladimir-yuldashev/laravel-queue-rabbitmq`

## Struktur Kunci
```
app/
├── Jobs/ProcessTicketOrder.php          # Consumer job (IDENTIK class dgn S2, beda handle())
├── Models/Ticket.php, Payment.php
├── Services/HasuraService.php           # panggil mutation kuota ke S1 (anti-overselling)
└── GraphQL/ (opsional resolver custom)
graphql/schema.graphql                   # SDL Lighthouse (type, query, resolver directives)
config/lighthouse.php                    # konfigurasi Lighthouse
```

---

## Endpoint GraphQL

Semua query via `POST http://localhost:8002/graphql` (Content-Type: application/json).

### Skema Query
```graphql
type Query {
  ticketByOrder(order_code: String!): Ticket
  ticket(ticket_code: String!): Ticket
  myTickets(user_id: String!): [Ticket!]!
}

type Ticket {
  id: ID!
  order_code: String!
  ticket_code: String        # TKT-2026-XYZ99 (null bila gagal)
  concert_id: Int!
  user_id: String!
  status: String!            # SUCCESS | FAILED | SOLD_OUT
  issued_at: DateTime
  created_at: DateTime
}
```

### Contoh
```bash
# Cek tiket berdasarkan order_code
curl -s http://localhost:8002/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ ticketByOrder(order_code: \"ORD-20260615-AB12\") { id order_code ticket_code status issued_at } }"}'

# Cek tiket berdasarkan kode tiket
curl -s http://localhost:8002/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ ticket(ticket_code: \"TKT-2026-XYZ99\") { id order_code status } }"}'

# Semua tiket milik user
curl -s http://localhost:8002/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ myTickets(user_id: \"andi\") { order_code ticket_code status } }"}'
```

---

## Alur Pemrosesan (handle Job)

1. **Simulasi pembayaran** → catat Payment (PAID).
2. **Kurangi kuota atomik** di Hasura via `HasuraService::decrementQuota()`:
   - `affected_rows = 1` → kuota tersedia, lanjut terbitkan tiket.
   - `affected_rows = 0` → **SOLD OUT**, tiket ditandai `SOLD_OUT`.
3. **Terbitkan tiket** (jika sukses) → `TKT-YYYY-XXXXX`, simpan ke tabel `tickets`.
4. **Callback opsional** ke Service 2 → `PATCH /api/bookings/{order_code}/status`.

> **Anti-overselling (rencana §2.4):** pengurangan kuota dilakukan di CONSUMER
> secara berurutan (satu per satu per iterasi), bukan di Publisher. Mutation Hasura
> menggunakan syarat `where available_quota > 0` sehingga mustahil minus.

---

## Menjalankan

### Via Docker (lihat `docker-compose.yml` di root repo)
```bash
# dari root repo:
docker compose up -d --build
```
- `issuing_service` → GraphQL server di **host port 8002**.
- `issuing_worker` → queue worker (tidak expose port, berjalan di background).

### Lokal (tanpa Docker)
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8002 &         # GraphQL server
php artisan queue:work rabbitmq --queue=ticket_orders --tries=3   # Worker
```

---

## Catatan Teknis
- **Class Job IDENTIK** dengan Service 2 — namespace, nama, properti, konstruktor sama.
- Host Hasura dari container = `http://hasura:8080` (nama container di jaringan ticketing_net).
- Worker dijalankan: `php artisan queue:work rabbitmq --queue=ticket_orders --tries=3`.
- `--tries=3` → pesan gagal di-retry 3× sebelum masuk failed_jobs.
- Format kode tiket: `TKT-YYYY-XXXXX` (5 karakter random uppercase).

## Troubleshooting
- **Worker tidak memproses pesan** → cek `QUEUE_CONNECTION=rabbitmq`, cek host
  `rabbitmq` benar, buka Management UI `http://localhost:15672`.
- **Tiket SOLD_OUT padahal kuota masih ada** → cek `HASURA_URL` & `HASURA_ADMIN_SECRET`
  di `.env`, pastikan Hasura bisa dijangkau.
- **GraphQL 500 / error** → jalankan `php artisan lighthouse:cache` atau cek
  `graphql/schema.graphql` syntax-nya.
- **`composer install` gagal** (offline) → install dependensi lokal, lalu mount
  folder `vendor/` ke container (lihat catatan build di README root).
