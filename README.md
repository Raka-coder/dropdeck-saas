# DropDeck — Paket Dokumen dan Infrastruktur

Paket ini berisi dokumen perencanaan (PRD, Architecture, Schema, Rules) dan file infrastruktur untuk menjalankan DropDeck dengan **Podman** secara lokal dan **Dokploy** di produksi.

## Isi Paket

```
dropdeck-docs/
├── README.md                     ← file ini
├── compose.yaml                  ← compose LOKAL (Podman/Docker)
├── compose.prod.yaml             ← compose PRODUKSI (Dokploy, service tipe Compose)
├── .dockerignore
├── .env.example.snippet          ← blok variabel untuk .env / .env.example
├── infra/
│   ├── Containerfile             ← multi-stage: base, dev, vendor, assets, prod
│   ├── Caddyfile                 ← FrankenPHP (mode klasik)
│   ├── php.ini                   ← batas upload, opcache
│   └── docker-entrypoint.sh      ← tunggu DB, migrate (opsional), optimize
├── dokploy/
│   ├── docker-compose.yml        ← DRAFT template (berbasis image registry)
│   └── template.toml             ← DRAFT variabel template
└── docs/
    ├── 01-PRD.md
    ├── 02-ARCHITECTURE.md
    ├── 03-DATABASE-SCHEMA.md
    └── 04-RULES.md
```

## Cara Memakai di Proyek Laravel 13 Anda

Salin ke **root proyek** yang sudah dibuat dari Livewire starter kit:
`compose.yaml`, `compose.prod.yaml`, `.dockerignore`, folder `infra/`, `dokploy/`, dan `docs/`.
Isi `.env.example.snippet` digabungkan ke `.env.example` dan `.env`.

## Setup Lokal (Podman)

Prasyarat: Podman 4.x+ dan `podman compose` (provider `podman-compose` atau `docker-compose`).

```bash
# 1) Siapkan .env (gabungkan isi .env.example.snippet)
cp .env.example .env        # lalu tambahkan blok dari .env.example.snippet

# 2) Build image dev
podman compose build

# 3) Pasang dependensi PHP (vendor/ belum ada saat pertama kali)
podman compose run --rm app composer install

# 4) APP_KEY
podman compose run --rm app php artisan key:generate

# 5) Jalankan semua layanan
podman compose up -d

# 6) Cek status dan log
podman compose ps
podman compose logs -f app
```

Perintah harian:

```bash
podman compose exec app php artisan migrate
podman compose exec app php artisan test            # Pest
podman compose exec app vendor/bin/pint
podman compose exec app composer require <paket>
podman compose down            # hentikan (data tetap di volume)
podman compose down -v         # hentikan + HAPUS data (DB, RustFS, node_modules)
```

### Alamat Lokal

| Layanan | URL | Catatan |
|:---|:---|:---|
| Aplikasi | http://localhost:8000 | |
| Vite (HMR) | http://localhost:5173 | |
| phpMyAdmin | http://localhost:8080 | Login otomatis memakai `DB_USERNAME`/`DB_PASSWORD` |
| RustFS S3 API | http://localhost:9000 | |
| RustFS Console | http://localhost:9001 | Login `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` |
| Mailpit (email dev) | http://localhost:8025 | Menangkap email verifikasi/reset |

> **Mailpit** tidak ada di daftar awal permintaan, tetapi ditambahkan karena verifikasi email dan reset password butuh SMTP saat pengembangan.

### Troubleshooting

#### SELinux Permission Denied (Fedora/RHEL)

**Gejala:** `podman compose ps` menunjukkan container running, tapi:
- `curl localhost:8000` → **403 Forbidden**
- Log app: `stat .: permission denied`, `Could not open input file: artisan`, `cd: can't cd to /app`
- Log queue/scheduler: `Permission denied` saat baca `/app/config`, `/app/composer.json`

**Penyebab:** Provider `podman compose` (docker-compose CLI plugin) **tidak menerapkan label SELinux `:Z`** pada bind mount. Di sistem SELinux Enforcing (Fedora/RHEL), container diblokir akses ke files host.

**Verifikasi:**
```bash
# Cek SELinux status
getenforce   # kalau "Enforcing", ini penyebabnya

# Cek mount options — kalau :Z hilang, konfirmasi
podman inspect dropdeck-app-1 --format '{{json .Mounts}}'
#   "Options":["rbind"]"  ← tanpa :Z bermasalah
#   "Options":["rbind",":Z"]"  ← benar
```

**Fix — pilih salah satu:**

```bash
# Opsi A (REKOMENDASI): Install podman-compose native — apply :Z dengan benar
pip install podman-compose
podman compose down
podman-compose build
podman-compose up -d

# Opsi B: Set SELinux context manual
sudo chcon -Rt container_file_t /home/rakarestu/Documents/dropdeck
podman compose down
podman compose up -d

# Opsi C (SUDAH DITERAPKAN): compose.yaml sudah termasuk security_opt: label:disable
# untuk semua service PHP dan Vite. Cukup restart container:
podman compose down
podman compose up -d
```

> **Catatan:** `security_opt: label:disable` sudah ditambahkan di `compose.yaml` untuk semua service PHP dan Vite. Ini aman untuk development lokal. Untuk produksi (Dokploy), `compose.prod.yaml` tidak menggunakan bind mount sehingga SELinux tidak menjadi masalah.

#### CSS/Tailwind Tidak Terender (Halaman Register/Login tanpa style)

**Gejala:** HTML load tapi tanpa CSS — tampilan seperti HTML mentah.

**Penyebab:** Vite container crash-loop dengan error `ENOTEMPTY` (node_modules corrupted) atau port mismatch (assets load dari port salah).

**Verifikasi:**
```bash
# Cek Vite container
podman compose ps vite          # harus Up, bukan Exited/crash-loop
podman compose logs --tail=20 vite

# Cek port — harus 5173, bukan 5174+
curl -s http://localhost:8000/login | grep -o 'localhost:[0-9]*/'

# Cak assets bisa diakcess
curl -s -o /dev/null -w "%{http_code}" http://localhost:5173/resources/css/app.css
```

**Fix:**
```bash
# 1) Pastikan vite.config.js punya host 0.0.0.0 dan port 5173
#    (sudah diterapkan di repo ini)

# 2) Clean node_modules volume dan restart Vite
podman compose down vite
podman volume rm dropdeck_node_modules
podman compose up -d vite

# 3) Tunggu npm install selesai (~15 detik), lalu verifikasi
podman compose logs --tail=10 vite
curl -s http://localhost:8000/login | grep -o 'localhost:5173'
```

#### Google OAuth "Missing required parameter: client_id"

**Gejala:** Klik tombol Google login → error 400 "Missing required parameter: client_id".

**Penyebab:** Compose `env_file: .env` hanya dibaca saat container **dibuat**. Variabel yang ditambahkan setelah container running tidak ter-load.

**Fix:**
```bash
podman compose up -d --force-recreate app queue scheduler
```

Setelah recreate, verifikasi:
```bash
podman compose exec app php artisan tinker --execute='echo config("services.google.client_id");'
```

## Langkah Setelah Container Jalan (M0–M1)

Lakukan di dalam container (`podman compose exec app ...`).

### 1. Paket yang dipasang
```bash
composer require laravel/socialite league/flysystem-aws-s3-v3 "^3.0" intervention/image
composer require --dev larastan/larastan   # Pest/Pint biasanya sudah ada di starter kit
```

### 2. Disk `vault` — `config/filesystems.php`
```php
'vault' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'bucket' => env('AWS_BUCKET'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
    'visibility' => 'private',
    'throw' => true,
    'report' => true,
],
```

### 3. Proxy tepercaya (produksi di belakang Traefik) — `bootstrap/app.php`
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*'); // aman karena container hanya dijangkau Traefik
})
```

### 4. Vite di dalam container — `vite.config.js`
Config sudah diterapkan di repo ini:
```js
server: {
    host: '0.0.0.0',
    port: 5173,
    hmr: { host: 'localhost', port: 5173 },
},
```
Pastikan `node_modules` volume bersih saat pertama kali start. Jika Vite crash-loop dengan error `ENOTEMPTY`, jalankan:
```bash
podman compose down vite
podman volume rm dropdeck_node_modules
podman compose up -d vite
```

### 5. Google login
- Tambahkan di `config/services.php`:
  ```php
  'google' => [
      'client_id' => env('GOOGLE_CLIENT_ID'),
      'client_secret' => env('GOOGLE_CLIENT_SECRET'),
      'redirect' => env('GOOGLE_REDIRECT_URI'),
  ],
  ```
- Google Cloud Console → OAuth Client (Web). Authorized redirect URI lokal: `http://localhost:8000/auth/google/callback`; produksi: `https://<domain>/auth/google/callback`.

### 6. `config/dropdeck.php`
Buat file config berisi semua batas bisnis (PRD bagian 7) yang membaca variabel `DROPDECK_*`.

### 7. Tes cepat storage
```bash
podman compose exec app php artisan tinker
>>> Storage::disk('vault')->put('healthcheck.txt', 'ok');
>>> Storage::disk('vault')->get('healthcheck.txt');
>>> Storage::disk('vault')->delete('healthcheck.txt');
```
Jika gagal, periksa log `rustfs` dan `rustfs-init`, serta kredensial di `.env`.

## Deploy ke Dokploy di Proxmox Self-Hosted

### Prasyarat
- VM/LXC di Proxmox dengan Ubuntu 24.04 LTS (4 vCPU, 8 GB RAM recommended)
- Docker + Compose plugin terinstall
- Dokploy sudah di-install ([install guide](https://docs.dokploy.com/getting-started/installation))
- Domain sudah pointing ke IP server

### Langkah Deploy

1. **Build image produksi secara lokal** (untuk memastikan Containerfile valid):
   ```bash
   podman build -f infra/Containerfile -t dropdeck-prod .
   ```

2. **Push repositori** (tanpa `.env`, `node_modules/`, `vendor/`).

3. **Dokploy → Create Service → Compose**:
   - Source: repositori
   - Compose file: `compose.prod.yaml`
   - Build context: root proyek

4. **Tab Environment** — isi dari `.env.example.snippet`:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://domain-kamu.com`
   - `APP_KEY` (generate: `php artisan key:generate --show`)
   - `DB_*` (host: `mariadb`, port: `3306`)
   - `AWS_*` (RustFS/S3)
   - `GOOGLE_*` (OAuth)
   - `MAIL_*` (Mailpit/SMTP)
   - `DROPDECK_*`

5. **Tab Domains**:
   - Domain: `domain-kamu.com`
   - Service: `app`
   - Port: `8000`
   - HTTPS: aktifkan (Let's Encrypt via Traefik)

6. **Deploy** — service `app` otomatis jalankan `migrate --force` + `optimize`.

7. **Verifikasi**:
   - `https://domain-kamu.com/up` → harus return 200
   - Daftar akun, login
   - Upload file, buat DropLink
   - Unduh sebagai guest
   - Kirim email verifikasi (cek Mailpit)

### Catatan Produksi

- `compose.prod.yaml` memakai image build dari Containerfile (bukan bind mount), jadi SELinux tidak jadi masalah.
- Volume `mariadb_data` dan `rustfs_data` persist di server Proxmox.
- Backup: `podman compose -f compose.prod.yaml exec mariadb mariadb-dump -u root -p<password> dropdeck > backup.sql`
- Log: `podman compose -f compose.prod.yaml logs -f app`

Detail arsitektur, backup, dan pemulihan: lihat `docs/02-ARCHITECTURE.md` bagian 10.

## Daftar [VERIFIKASI]

Hal-hal ini bergantung pada versi pihak ketiga dan **belum diuji** di lingkungan Anda. Periksa sebelum mengandalkannya:

| # | Hal | Di mana | Cara cek |
|:--|:---|:---|:---|
| 1 | Tag image `dunglas/frankenphp:1-php8.5` tersedia | `infra/Containerfile` | Docker Hub; ubah `ARG PHP_VERSION` bila perlu |
| 2 | Ekstensi `imagick` tersedia untuk PHP 8.5 | `infra/Containerfile` | Build berhasil; jika gagal, hapus `imagick` (PDF thumbnail dimatikan) |
| 3 | Path `policy.xml` ImageMagick untuk izin PDF | `infra/Containerfile` | `identify -list policy` di container |
| 4 | Nama variabel environment dan user proses RustFS | `compose*.yaml` | Dokumentasi RustFS; bila ada "permission denied", tambah `user: "0:0"` (dev) |
| 5 | Pin tag RustFS (hindari `latest` di produksi) | `compose.prod.yaml` | Pilih versi yang sudah Anda uji |
| 6 | `depends_on.condition` didukung provider compose Anda | `compose*.yaml` | `podman compose version`; entrypoint tetap menunggu DB |
| 7 | Perilaku `env_file: .env` pada Dokploy | `compose.prod.yaml` | Dokumentasi Dokploy untuk Compose service |
| 8 | Sintaks `template.toml` terbaru | `dokploy/` | Repositori Dokploy Templates |
| 9 | Dukungan drag-drop bawaan (Alpine Sort / `wire:sort`) di versi Livewire starter kit | Kanban | Dokumentasi Livewire versi Anda; bila belum ada, `npm i @alpinejs/sort` atau `sortablejs` |
| 10 | Nama tabel/kolom bawaan starter kit Laravel 13 (users, two-factor) | `docs/03` | Baca migrasi starter kit sebelum menambah migrasi |
| 11 | Dukungan server-side encryption RustFS | `docs/02` bagian 4.3 | Hanya jika enkripsi at-rest dibutuhkan |

## Urutan Pengerjaan yang Disarankan

Ikuti milestone di `docs/01-PRD.md` bagian 13: **M0 infrastruktur → M1 auth → M2 skema → M3 Kanban → M4 Vault → M5 DropLink → M6 Dashboard → M7 Hardening → M8 Deploy**.

Untuk coding dengan asisten AI, berikan `docs/01–04` sebagai konteks; aturan khusus asisten ada di `docs/04-RULES.md` bagian 15.
