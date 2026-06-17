# TicketStream — Sistem Pemesanan Tiket Konser (Microservices)

> **Repository:** https://github.com/alysharefa/TicketStream

Sistem pemesanan tiket konser berbasis **3 microservice** yang dirancang untuk
menangani lonjakan trafik tinggi tanpa overselling. Pemesanan diproses secara
**asinkron** melalui antrean pesan (RabbitMQ), sementara kuota dikurangi secara
**atomik** di consumer untuk menjamin tidak ada tiket terjual ganda.

---

## Arsitektur

```
Client/Postman
     │
     ├──► Service 1: Hasura GraphQL ──► PostgreSQL (katalog konser)
     │
     ├──► Service 2: Laravel REST + Lighthouse GraphQL ──► MySQL (booking) ──► RabbitMQ (publish)
     │                                                                           │
     └──► Service 3: Laravel Lighthouse GraphQL ──► MySQL (tiket)            ◄───┘ (consume)
              │
              └──► mutation Hasura (kurangi kuota atomik)
```

| Service | Peran | Teknologi | Database | Port |
|---------|-------|-----------|----------|------|
| **S1** Katalog Event | Data master konser, API GraphQL auto-generate | Hasura + PostgreSQL | `db_event_catalog` | 8080 |
| **S2** Booking & Queue | Terima pesanan, antrekan ke RabbitMQ | Laravel 12 REST + Lighthouse GraphQL + MySQL | `db_ticket_booking` | 8001 |
| **S3** Payment & Issuing | Proses antrean, bayar, terbitkan tiket | Laravel 12 + Lighthouse GraphQL + MySQL | `db_ticket_issuing` | 8002 |
| **MQ** RabbitMQ | Broker pesan (queue: `ticket_orders`) | RabbitMQ 3 + Management UI | — | 5672, 15672 |

### Diagram Alur

```
1. Client         ──► query konser (GraphQL S1/Hasura)
2. Client         ──► POST /api/bookings (REST S2)
3. S2 Booking     ──► publish pesan ke RabbitMQ
4. S3 Worker      ──► consume pesan (berurutan, 1 per 1)
5. S3 Worker      ──► mutation kurangi kuota (GraphQL S1/Hasura)
6. S3 Worker      ──► terbitkan tiket (TKT-2026-XXXXX)
7. Client         ──► query tiket (GraphQL S3/Lighthouse)
8. Client         ──► query status booking (GraphQL S2/Lighthouse)
```

---

## Teknologi

- **Hasura GraphQL Engine** — GraphQL auto-generate dari skema PostgreSQL
- **Laravel 12** (PHP 8.3) — framework untuk S2 (REST) dan S3 (GraphQL)
- **Lighthouse** — paket GraphQL manual untuk Laravel (S2 & S3)
- **PostgreSQL 16** — database S1
- **MySQL 8** — database S2 dan S3 (terpisah, polyglot persistence)
- **RabbitMQ 3** — message broker antar service
- **Docker & Docker Compose** — containerization semua service

---

## Prasyarat

- **Docker** & **Docker Compose** v2
- **Git**
- **Postman** (opsional, untuk testing API)

---

## Cara Menjalankan

### 1. Clone & konfigurasi

```bash
git clone <repo-url> TicketStream
cd TicketStream
cp .env.example .env
```

### 2. Generate APP_KEY untuk S2 dan S3

Laravel membutuhkan `APP_KEY`. Jalankan di terminal:

```bash
# Untuk BookingService
cd BookingService && composer install && php artisan key:generate --show && cd ..
# Copy output-nya ke BOOKING_APP_KEY di .env root

# Untuk PaymentService
cd PaymentService && composer install && php artisan key:generate --show && cd ..
# Copy output-nya ke ISSUING_APP_KEY di .env root
```

> **Alternatif cepat:** biarkan `APP_KEY` kosong, service akan tetap berjalan
> (hanya warning). Untuk demo/testing ini tidak masalah.

### 3. Jalankan semua service

```bash
docker compose up --build -d
```

Tunggu hingga semua container healthy (sekitar 1-2 menit pertama kali).

### 4. Setup metadata Hasura (sekali saja)

```bash
cd KatalogService
bash hasura/apply-metadata.sh
cd ..
```

Atau buka **Hasura Console** di http://localhost:8080, masuk ke tab **Data**,
lalu **Track All** pada tabel `artists` dan `concerts`.

### 5. Verifikasi

| Service | URL | Test |
|---------|-----|------|
| Hasura Console | http://localhost:8080 | Buka console, lihat tabel |
| Booking REST | http://localhost:8001/api/bookings | POST pesanan |
| Booking GraphQL | http://localhost:8001/graphql | Query status booking |
| GraphQL S3 | http://localhost:8002/graphql | Query tiket |
| RabbitMQ UI | http://localhost:15672 | Login guest/guest |

---

## Alur End-to-End

### Langkah 1: Browse konser (GraphQL S1)

```bash
curl -s http://localhost:8080/v1/graphql \
  -H 'Content-Type: application/json' \
  -H 'x-hasura-admin-secret: myadminsecret' \
  -d '{"query":"{ concerts(where:{available_quota:{_gt:0}}){ id name price available_quota artist{ name } } }"}'
```

### Langkah 2: Booking tiket (REST S2)

```bash
curl -s -X POST http://localhost:8001/api/bookings \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"concert_id":5,"user_id":"andi","quantity":2,"amount":1700000}'
```

Respon: `202 Accepted` + `order_code` (mis. `ORD-20260615-AB12`).
Pesanan sudah diantrekan, worker akan memproses secara otomatis.

### Langkah 3: Cek status booking (GraphQL S2)

```bash
curl -s http://localhost:8001/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ bookingByOrder(order_code: \"ORD-20260615-AB12\") { order_code status quantity amount } }"}'
```

### Langkah 4: Cek status tiket (GraphQL S3)

```bash
curl -s http://localhost:8002/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ ticketByOrder(order_code: \"ORD-20260615-AB12\") { order_code ticket_code status issued_at } }"}'
```

Tunggu beberapa detik hingga worker memproses. Status yang mungkin:
- `SUCCESS` — tiket terbit, `ticket_code` terisi (mis. `TKT-2026-XYZ99`)
- `SOLD_OUT` — kuota habis
- `FAILED` — pembayaran/pemrosesan gagal

---

## Koleksi Postman

Impor file berikut ke Postman untuk testing interaktif:

| Service | File |
|---------|------|
| **Semua Service (Gabungan)** | `postman/TicketStream-AllServices.postman_collection.json` |
| S1 Katalog Event | `KatalogService/postman/Service1-EventCatalog.postman_collection.json` |
| S2 Booking & Queue | `BookingService/postman/Service2-Booking.postman_collection.json` |
| S3 Payment & Issuing | `PaymentService/postman/Service3-Payment.postman_collection.json` |

---

## Skenario Pengujian

### Happy Path
1. Browse konser via Hasura (kuota > 0)
2. POST booking → dapat `202` + `order_code`
3. Cek RabbitMQ Management UI → pesan muncul di queue `ticket_orders`
4. Tunggu worker memproses → cek GraphQL S3 → tiket `SUCCESS`
5. Cek kuota di Hasura → berkurang 1

### Sold Out (Anti-Overselling)
1. Reset kuota konser id=5 menjadi 2 (via mutation SetKuota di S1)
2. Kirim 5 booking berurutan cepat ke S2
3. Semua mendapat `202` (S2 tetap responsif)
4. Cek GraphQL S3: **2 tiket `SUCCESS`**, **3 tiket `SOLD_OUT`**
5. Cek kuota di Hasura: `available_quota = 0` (**tidak minus**)

> Ini membuktikan anti-overselling: walau 5 pesanan masuk hampir bersamaan,
> worker memproses satu per satu dan syarat `available_quota > 0` pada mutation
> menjamin kuota tidak pernah negatif.

---

## Menghentikan & Reset

```bash
# Hentikan semua container (data tetap tersimpan di volume)
docker compose down

# Hentikan + hapus volume (data dihapus, seed akan diulang saat 'up' berikutnya)
docker compose down -v

# Rebuild setelah perubahan kode
docker compose up --build -d
```

> **Catatan:** `docker compose down -v` akan menghapus semua data database.
> Seed data KatalogService akan dibuat ulang otomatis saat `up` berikutnya.

---

## Struktur Repo

```
TicketStream/
├── docker-compose.yml              # All-in-one compose (semua service)
├── .env.example                    # Contoh konfigurasi root
├── KatalogService/                 # Service 1 — Hasura + PostgreSQL
│   ├── docker-compose.yml          #   compose standalone S1
│   ├── db/init/                    #   SQL schema + seed
│   ├── hasura/                     #   metadata + apply script
│   ├── graphql/                    #   contoh query & mutation
│   ├── postman/                    #   koleksi Postman S1
│   └── README.md
├── BookingService/                 # Service 2 — Laravel REST + GraphQL + MySQL
│   ├── Dockerfile                  #   image PHP 8.3
│   ├── app/Http/Controllers/       #   BookingController
│   ├── app/Jobs/                   #   ProcessTicketOrder (publisher)
│   ├── graphql/schema.graphql      #   SDL Lighthouse
│   ├── routes/api.php              #   REST endpoints
│   ├── postman/                    #   koleksi Postman S2
│   └── README.md
├── PaymentService/                 # Service 3 — Lighthouse + MySQL
│   ├── Dockerfile                  #   image PHP 8.3
│   ├── app/Jobs/                   #   ProcessTicketOrder (consumer)
│   ├── app/Services/               #   HasuraService (mutation kuota)
│   ├── app/Models/                 #   Ticket, Payment
│   ├── graphql/schema.graphql      #   SDL Lighthouse
│   ├── postman/                    #   koleksi Postman S3
│   └── README.md
└── README.md                       # (file ini)
```

---

## Troubleshooting

### Container gagal start
```bash
# Cek log container tertentu
docker compose logs booking_service
docker compose logs issuing_worker
```

### DB connection error
Pastikan MySQL/PostgreSQL sudah healthy sebelum Laravel start:
```bash
docker compose ps  # cek kolom STATUS = "healthy"
```

### Pesan tidak muncul di RabbitMQ
- Cek `QUEUE_CONNECTION=rabbitmq` di environment booking_service
- Buka RabbitMQ Management: http://localhost:15672 (guest/guest)
- Pastikan queue `ticket_orders` ada dan consumer terhubung

### Worker tidak memproses pesan
- Cek log: `docker compose logs issuing_worker`
- Pastikan `HASURA_URL` dan `HASURA_ADMIN_SECRET` benar
- Pastikan Hasura metadata sudah diterapkan (`apply-metadata.sh`)

### Tiket SOLD_OUT padahal kuota masih ada
- Cek apakah Hasura bisa diakses dari container S3:
  ```bash
  docker compose exec issuing_service curl http://hasura:8080/v1/graphql \
    -H 'Content-Type: application/json' \
    -H 'x-hasura-admin-secret: myadminsecret' \
    -d '{"query":"{ concerts { id available_quota } }"}'
  ```

### Hasura: tabel belum muncul di GraphQL
Jalankan `bash KatalogService/hasura/apply-metadata.sh` atau Track All di Console.

### Port sudah terpakai
Ubah port di `.env` root:
- `HASURA_PORT=8080` → port lain
- `BOOKING_PORT=8001` → port lain
- `ISSUING_PORT=8002` → port lain

---

## Keputusan Arsitektur

1. **Database per service** — tidak ada tabel yang dibagi antar service (polyglot persistence).
2. **Kuota dikurangi di consumer** (bukan publisher) — memproses berurutan mencegah race condition.
3. **Mutation Hasura dengan syarat `available_quota > 0`** — atomic decrement, mustahil oversell.
4. **Class Job identik** di S2 & S3 — agar serialisasi RabbitMQ bisa di-resolve kedua sisi.
5. **Tiga penerapan GraphQL** — Hasura (auto-generate, S1) + Lighthouse (manual, S2 & S3) — mendemonstrasikan
   dua pendekatan GraphQL yang berbeda dalam satu sistem. Semua 3 service memiliki GraphQL endpoint.

---

## Lisensi

Proyek tugas besar / akademik. Bebas digunakan untuk pembelajaran.
