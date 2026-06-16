# Service 1 — Manajemen & Katalog Event

Microservice katalog untuk **Sistem Pemesanan Tiket Konser**. Menyimpan data master
(artis, konser, jadwal, harga, kuota) dan menyajikannya sebagai **API GraphQL instan**
menggunakan **Hasura GraphQL Engine** di atas **PostgreSQL**.

> Service ini **tanpa kode aplikasi / tanpa Node.js**. Hasura meng-*generate* seluruh API
> GraphQL (query, filter, sort, pagination, relasi) secara otomatis dari skema database.

---

## Teknologi
- Hasura GraphQL Engine (Docker)
- PostgreSQL 16 (Docker)
- Database: `db_event_catalog`

## Struktur Proyek
```
service-1-event-catalog/
├── docker-compose.yml          # Hasura + PostgreSQL
├── .env.example                # contoh konfigurasi (salin ke .env)
├── db/init/
│   ├── 01_schema.sql           # tabel artists & concerts (auto-run saat init)
│   └── 02_seed.sql             # data contoh (termasuk 1 konser kuota=2 utk uji sold out)
├── hasura/
│   ├── metadata.json           # definisi track tabel + relasi
│   └── apply-metadata.sh        # menerapkan metadata via Metadata API
├── graphql/
│   ├── queries.graphql         # contoh query
│   └── mutations.graphql       # contoh mutation (termasuk KurangiKuota utk Service 3)
└── postman/
    └── Service1-EventCatalog.postman_collection.json
```

## Prasyarat
- Docker & Docker Compose
- `curl` (dipakai skrip `apply-metadata.sh`; opsional bila memakai cara manual)

---

## Cara Menjalankan

1. (Opsional) Salin konfigurasi:
   ```bash
   cp .env.example .env
   ```
2. Jalankan container:
   ```bash
   docker compose up -d
   ```
   Postgres otomatis membuat tabel + mengisi seed (dari `db/init`), Hasura menyala di port 8080.
3. Terapkan metadata (melacak tabel + relasi):
   ```bash
   bash hasura/apply-metadata.sh
   ```
   **Alternatif manual:** buka Console → menu **Data** → klik **Track All** pada tabel
   `artists` & `concerts`, lalu *track* kedua relasi yang otomatis disarankan Hasura.

---

## Akses
- **Hasura Console:** http://localhost:8080  (admin secret: `myadminsecret`)
- **Endpoint GraphQL:** `POST http://localhost:8080/v1/graphql`
  - Header wajib: `x-hasura-admin-secret: myadminsecret`

## Coba Cepat (cURL)
```bash
curl -s http://localhost:8080/v1/graphql \
  -H 'Content-Type: application/json' \
  -H 'x-hasura-admin-secret: myadminsecret' \
  -d '{"query":"{ concerts(where:{available_quota:{_gt:0}}){ id name available_quota artist{ name } } }"}'
```
Contoh-contoh lengkap ada di `graphql/queries.graphql` & `graphql/mutations.graphql`,
atau impor `postman/Service1-EventCatalog.postman_collection.json` ke Postman.

---

## Data Seed
5 artis & 5 konser. **Konser "Pesta Rakyat Reunion" (Dewa 19) sengaja berkuota 2 (id = 5)**
untuk menguji skenario SOLD OUT / anti-overselling di Service 3.

## Peran dalam Sistem (integrasi)
- **Client** memanggil query di sini untuk menampilkan & memfilter daftar konser.
- **Service 3 (Worker)** memanggil mutation `KurangiKuota` setiap menerbitkan tiket.
  Syarat `available_quota > 0` pada mutation **plus** `CHECK (available_quota >= 0)` di DB
  menjamin kuota tidak pernah minus (no overselling).

Contoh panggilan yang dilakukan Service 3:
```bash
curl -s http://localhost:8080/v1/graphql \
  -H 'Content-Type: application/json' \
  -H 'x-hasura-admin-secret: myadminsecret' \
  -d '{"query":"mutation($id:Int!){ update_concerts(where:{id:{_eq:$id},available_quota:{_gt:0}}, _inc:{available_quota:-1}){ affected_rows } }","variables":{"id":5}}'
```
> Agar Service 3 (di container lain) bisa memanggil Hasura, jalankan keduanya pada jaringan
> Docker yang sama (`ticketing_net`) dan gunakan host `http://hasura:8080` dari dalam container.

---

## Mengulang Pengujian (reset kuota)
Pakai mutation `SetKuota` di `graphql/mutations.graphql`, atau mulai dari nol:
```bash
docker compose down -v   # -v menghapus volume DB; seed akan dibuat ulang saat 'up'
docker compose up -d
bash hasura/apply-metadata.sh
```

## Troubleshooting
- **Tabel belum muncul di GraphQL** → jalankan `bash hasura/apply-metadata.sh`, atau Track All di Console.
- **Seed tidak terisi** → seed hanya jalan saat volume DB masih kosong. `docker compose down -v` lalu `up` lagi.
- **Tag image Hasura error** → ganti `hasura/graphql-engine:latest` di `docker-compose.yml` dengan versi v2 yang tersedia di [Docker Hub](https://hub.docker.com/r/hasura/graphql-engine/tags).
- **Port 8080 sudah dipakai** → ubah `HASURA_PORT` di `.env`.

## Catatan Produksi
Untuk produksi: pin versi image Hasura (mis. `:v2.x.x`), ganti admin secret dengan nilai kuat,
matikan `HASURA_GRAPHQL_DEV_MODE` dan console publik, serta kelola kredensial via secret manager.
