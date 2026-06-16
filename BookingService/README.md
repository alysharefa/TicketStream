# Service 2 — Booking & Queue

Microservice **pintu masuk pemesanan tiket** untuk Sistem Pemesanan Tiket Konser.
Dibangun dengan **Laravel 12 (REST API)** + **MySQL** + **RabbitMQ sebagai Publisher**.

> Peran: menerima pesanan, mencatatnya dengan status `PENDING` secepat mungkin,
> lalu **mempublikasikan pesan ke antrean RabbitMQ** `ticket_orders`. Pemrosesan
> nyata (validasi kuota & penerbitan tiket) dilakukan oleh Service 3 (Consumer).

---

## Teknologi
- Laravel 12 (PHP 8.3)
- MySQL 8 — database `db_ticket_booking`
- RabbitMQ — broker pesan (Publisher)
- Paket: `vladimir-yuldashev/laravel-queue-rabbitmq`

## Struktur Kunci
```
app/
├── Http/
│   ├── Controllers/BookingController.php   # endpoint REST
│   └── Requests/StoreBookingRequest.php    # validasi payload
├── Jobs/ProcessTicketOrder.php             # Job → pesan RabbitMQ (IDENTIK dgn S3)
└── Models/Booking.php
database/migrations/*_create_bookings_table.php
routes/api.php                              # POST/GET/PATCH /api/bookings
config/queue.php                            # koneksi rabbitmq
```

---

## Endpoint REST

| Method | Endpoint | Body / Param | Respon |
|--------|----------|--------------|--------|
| `POST` | `/api/bookings` | `{ "concert_id": 5, "user_id": "andi", "quantity": 2, "amount": 1700000 }` | `202` `{ "message": "...", "order_code": "ORD-..." }` |
| `GET` | `/api/bookings/{order_code}` | – | `200` `{ "order_code": "...", "status": "PENDING", ... }` |
| `PATCH` | `/api/bookings/{order_code}/status` | `{ "status": "PROCESSED" }` | `200` (callback opsional dari S3) |

### Contoh
```bash
# Buat pesanan
curl -s -X POST http://localhost:8001/api/bookings \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"concert_id":5,"user_id":"andi","quantity":2,"amount":1700000}'

# Cek status
curl -s http://localhost:8001/api/bookings/ORD-20260615-AB12
```

## GraphQL API

Selain REST, service ini juga menyediakan **GraphQL API** via Lighthouse.

**Endpoint:** `POST http://localhost:8001/graphql`

### Queries

| Query | Argumen | Keterangan |
|-------|---------|------------|
| `bookingByOrder` | `order_code: String!` | Cari booking berdasarkan order code |
| `myBookings` | `user_id: String!` | Semua booking milik user tertentu |
| `bookingsByConcert` | `concert_id: Int!` | Semua booking untuk konser tertentu |
| `allBookings` | — | Daftar semua booking |

### Contoh Query

```graphql
{
  bookingByOrder(order_code: "ORD-20260615-AB12") {
    id
    order_code
    concert_id
    user_id
    quantity
    amount
    status
    created_at
  }
}
```

## Format Pesan RabbitMQ (queue: `ticket_orders`)
Payload yang dikirim ke antrean (direncanakan §8.4), dibawa oleh properti Job:
```json
{
  "order_code": "ORD-20260615-AB12",
  "concert_id": 5,
  "user_id": "andi",
  "quantity": 2,
  "amount": 1700000
}
```
> Class Job `App\Jobs\ProcessTicketOrder` **harus identik** dengan yang ada di Service 3.

---

## Menjalankan

### Via Docker (lihat `docker-compose.yml` di root repo)
```bash
# dari root repo:
docker compose up -d --build
```
Container `booking_service` ter-expose di **host port 8001**.

### Lokal (tanpa Docker)
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8001
```
Pastikan MySQL & RabbitMQ dapat dijangkau, sesuaikan `DB_HOST`/`RABBITMQ_HOST` di `.env`.

---

## Catatan Teknis
- **Kuota TIDAK dikurangi di sini** (rencana §2.4) — pengurangan atomik dilakukan
  Service 3 lewat mutation Hasura, agar tidak overselling.
- Host RabbitMQ dari dalam container = `rabbitmq` (nama service di compose),
  **bukan** `localhost`.
- `QUEUE_CONNECTION=rabbitmq` agar `dispatch()` mengirim ke RabbitMQ.

## Troubleshooting
- **Pesan tidak muncul di RabbitMQ** → cek `QUEUE_CONNECTION=rabbitmq` & host
  RabbitMQ benar (`rabbitmq`); buka Management UI `http://localhost:15672`.
- **422 saat POST** → field `concert_id`/`user_id`/`quantity` wajib & sesuai tipe.
- **`composer install` gagal** (offline) → install dependensi lokal, lalu mount
  folder `vendor/` ke container (lihat catatan build di README root).
