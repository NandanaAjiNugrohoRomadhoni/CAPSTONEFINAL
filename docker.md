# Docker — Capstone

## Mode Penggunaan

Dua mode: **lokal** (semua jalan di container) dan **remote** (DB dari luar).

### Mode Lokal (DB + Adminer otomatis)

Menjalankan app, database MariaDB, dan Adminer:

```bash
docker compose --profile local up
```

| Service | URL |
|---|---|
| App | http://localhost:4400 |
| Adminer | http://localhost:9000 |
| DB (dari host) | localhost:4306 |

Data database tersimpan di volume `db-data`, aman walau container dihapus.

### Mode Remote (DB eksternal, tanpa Adminer)

App aja — konek ke database di luar Docker:

```bash
docker compose up
```

Sebelum jalan, set `DB_HOST` di `.env` ke alamat remote:

```dotenv
DB_HOST=192.168.1.100
DB_USER=root
DB_PASS=rahasia
```

## Ubah Port / Kredensial

Semua konfigurasi di **satu file**: `.env`

Bentrok port? Ganti nilai `APP_PORT`, `DB_PORT`, atau `ADMINER_PORT` di `.env`.

## Build Ulang (misal ganti port app)

```bash
docker compose build
```

## Hentikan Container

```bash
docker compose down
```

Hapus juga data database:

```bash
docker compose down -v
```

## Seeder di Dalam Container

Kalo app jalan di Docker, jalanin seeder pake `docker compose exec`:

| Perintah | Kegunaan |
|---|---|
| `docker compose exec app php spark db:seed TestSeeder` | Seed semua data awal (clean seed) |
| `docker compose exec app php spark db:seed StockTransactionSeeder` | Seed transaksi stok |
| `docker compose exec app php spark migrate` | Jalanin migration |
| `docker compose exec app php spark migrate:refresh` | Refresh migration (hapus + ulang) |

Contoh — seed semua data awal lewat container:

```bash
docker compose --profile local up -d          # jalanin container di background
docker compose exec app php spark db:seed TestSeeder   # seed data
```

> **Catatan:** `backend/.env` di dalam container **tidak dipakai** — semua konfigurasi lewat environment variable dari `docker-compose.yaml`. Kalo mau seed manual, pastikan `docker compose exec app` bukan `php spark` dari host.

## Seeder yang Tersedia

- `TestSeeder` — seed utama (panggil Role, User, ItemCategory, dll.) — aman dipanggil kapan aja
- `StockTransactionSeeder` — seed transaksi stok
