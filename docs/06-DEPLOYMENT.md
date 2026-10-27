# DropDeck — Panduan Deployment Produksi

**Target:** Dokploy self-hosted di VM Proxmox

**Aplikasi:** DropDeck (Laravel 13 + Livewire + FrankenPHP)

**Status:** Panduan siap diuji; patch preflight bagian 5 sudah diterapkan di repo

**Terakhir diperbarui:** 2026-10-28

Dokumen ini adalah panduan operasional lengkap untuk men-deploy DropDeck ke produksi: persiapan Proxmox, instalasi Dokploy, konfigurasi Compose, environment, domain/TLS, backup, restore, upgrade, keamanan, observability, dan troubleshooting.

Dokumen terkait:

- [`02-ARCHITECTURE.md`](02-ARCHITECTURE.md) — keputusan arsitektur dan topologi.
- [`04-RULES.md`](04-RULES.md) — aturan container, keamanan, dan deployment.
- [`05-IMPLEMENTATION-PLAN.md`](05-IMPLEMENTATION-PLAN.md) — milestone M8.
- [`../compose.prod.yaml`](../compose.prod.yaml) — Compose produksi.
- [`../.env.example.snippet`](../.env.example.snippet) — contoh variabel environment.

> **Ruang lingkup:** deployment single-node untuk MVP. Panduan ini **bukan** untuk high availability, multi-node Swarm, zero-downtime deployment, atau auto-scaling. Untuk fase berikutnya, pertimbangkan database terpisah, object storage terdedikasi, Redis/Horizon, dan load balancer.

---

## 0. Ringkasan

```text
Internet
  │  DNS A: dropdeck.example.com
  ▼
Proxmox VM (Ubuntu 24.04 LTS)
  │  Docker + Dokploy
  ▼
Traefik :80/:443  (Dokploy)
  │  dokploy-network
  ▼
app :8000  (FrankenPHP, image prod)
  ├── mariadb :3306   (internal, volume)
  ├── rustfs  :9000   (internal, volume)
  ├── rustfs-init     (one-shot buat bucket)
  ├── queue           (worker, tanpa port)
  └── scheduler       (schedule:work, tanpa port)
```

Hasil akhir:

- Aplikasi diakses via `https://dropdeck.example.com`.
- Traefik menangani TLS Let's Encrypt.
- MariaDB dan RustFS tidak terbuka ke internet.
- Panel Dokploy (`:3000`) hanya diakses lewat VPN/SSH tunnel.
- Backup harian database + object storage, diuji restore.
- Log aplikasi masuk ke `stderr` dan terlihat di Dokploy.

Estimasi waktu:

| Tahap | Estimasi |
|:---|:---|
| Buat VM + instal Ubuntu | 20–40 menit |
| Instal Dokploy | 10–20 menit |
| Build image pertama | 5–15 menit |
| Konfigurasi + deploy | 20–40 menit |
| Verifikasi end-to-end | 15–30 menit |
| Backup + uji restore | 30–60 menit |

---

## 1. Arsitektur Deployment

### 1.1 Komponen

| Service | Image | Port | Data | Publik? | Peran |
|:---|:---|:---|:---|:---|:---|
| `app` | build `infra/Containerfile` target `prod` | `expose 8000` | — | Ya, via Traefik | FrankenPHP melayani Laravel |
| `queue` | image sama | — | — | Tidak | `queue:work` |
| `scheduler` | image sama | — | — | Tidak | `schedule:work` |
| `mariadb` | `docker.io/library/mariadb:11.4` | `3306` internal | `mariadb_data` | Tidak | Database utama |
| `rustfs` | `docker.io/rustfs/rustfs` | `9000`, `9001` internal | `rustfs_data` | Tidak | S3-compatible object storage |
| `rustfs-init` | `docker.io/amazon/aws-cli:2.15.30` | — | — | Tidak | Membuat bucket `dropdeck` |

Service yang **tidak ada** di produksi:

- `phpmyadmin` — akses DB lewat Dokploy Terminal atau tunnel sementara.
- `mailpit` — produksi wajib memakai SMTP nyata.
- Vite dev server — aset sudah di-build di image produksi.

### 1.2 Prinsip

1. **Hanya `app` yang menghadap internet**, lewat Traefik. Database, RustFS, queue, dan scheduler tetap di jaringan internal.
2. **Tidak ada `ports:` ke host** di `compose.prod.yaml`; gunakan `expose` + domain Dokploy.
3. **Satu image, tiga peran**: `app`, `queue`, `scheduler`.
4. **Hanya `app` yang menjalankan migrasi** (`RUN_MIGRATIONS=true`).
5. **Semua rahasia di Environment Dokploy**, bukan di repo.
6. **Volume bernama** untuk MariaDB dan RustFS.
7. **Backup sebelum perubahan berisiko**, dan uji restore.

### 1.3 Alasan memilih VM, bukan LXC

Dokploy menginstal Docker dan menginisialisasi Docker Swarm. Docker di dalam LXC membutuhkan nesting, `keyctl`, dan konfigurasi AppArmor/overlay yang rawan masalah. Untuk produksi, gunakan **VM Proxmox** dengan Ubuntu 24.04 LTS.

Jika tetap memakai LXC:

- Aktifkan `features: nesting=1,keyctl=1`.
- Gunakan storage yang mendukung overlayfs.
- Uji `docker info`, `docker swarm init`, dan Traefik sebelum deploy.
- Jangan gunakan LXC untuk data produksi tanpa pengujian restore.

---

## 2. Prasyarat

### 2.1 Infrastruktur

| Item | Minimum | Rekomendasi |
|:---|:---|:---|
| Proxmox | 8.x | 8.x terbaru |
| VM | 2 vCPU / 4 GB RAM / 40 GB disk | **4 vCPU / 8 GB RAM / 80 GB disk** |
| Swap | 2 GB | **4 GB** |
| Network | IPv4 publik atau NAT + port forward | IPv4 publik bridged |
| Backup storage | — | Proxmox Backup Server / storage terpisah |
| Domain | 1 domain | `dropdeck.example.com` |
| Akses | SSH key | SSH key + VPN untuk panel |

Catatan kapasitas:

- Build image membutuhkan RAM. Mesin 4 GB bisa gagal saat `npm run build` atau `composer install`; 8 GB lebih aman.
- Disk 80 GB memberi ruang untuk image, build cache, database, object storage, dan backup lokal.
- Object storage tumbuh sesuai file pengguna. Pantau `df -h` dan `docker system df`.
- Backup sebaiknya **tidak** disimpan di disk yang sama dengan data produksi.

### 2.2 Domain dan DNS

| Nama | Tipe | Nilai | TTL |
|:---|:---|:---|:---|
| `dropdeck.example.com` | A | IP publik VM | 300 |
| `www.dropdeck.example.com` (opsional) | CNAME | `dropdeck.example.com` | 300 |

Catatan Cloudflare:

- Untuk penerbitan sertifikat pertama, paling aman gunakan **DNS only** (awan abu-abu).
- Setelah sertifikat aktif, proxy Cloudflare boleh diaktifkan dengan SSL/TLS mode **Full (strict)**.
- Jangan gunakan mode **Flexible**; aplikasi memaksa HTTPS dan bisa terjadi redirect loop.
- Jika memakai proxy, pastikan port `80` dan `443` tetap sampai ke origin.

### 2.3 Firewall

| Port | Sumber | Tujuan |
|:---|:---|:---|
| `22/tcp` | IP admin/VPN | SSH |
| `80/tcp` | Internet | Traefik / Let's Encrypt HTTP-01 |
| `443/tcp` | Internet | Traefik HTTPS |
| `3000/tcp` | IP admin/VPN saja | Panel Dokploy |

Jangan membuka `3306` (MariaDB), `9000`, atau `9001` (RustFS) ke internet.

> **Penting:** Docker memanipulasi iptables. Aturan UFW untuk port yang dipublikasikan Docker tidak selalu berlaku. Gunakan **Proxmox firewall**, firewall router/cloud, atau aturan `DOCKER-USER` untuk membatasi `3000`.

### 2.4 Akun dan secret yang harus disiapkan

- Domain produksi.
- Google OAuth Client (Web) untuk domain produksi.
- SMTP produksi (host, port, username, password, alamat pengirim).
- Password manager untuk menyimpan:
  - `APP_KEY`
  - `DB_ROOT_PASSWORD`, `DB_PASSWORD`
  - `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`
  - `GOOGLE_CLIENT_SECRET`
  - `MAIL_PASSWORD`
  - kredensial admin Dokploy
- Akses GitHub ke repo `Raka-coder/dropdeck-saas` (branch `main`).

### 2.5 Repositori

| Item | Nilai |
|:---|:---|
| Repo | `https://github.com/Raka-coder/dropdeck-saas.git` |
| Branch | `main` |
| Compose | `compose.prod.yaml` |
| Build context | root repo |
| Dockerfile | `infra/Containerfile` |
| Target | `prod` |

---

## 3. Menyiapkan VM di Proxmox

### 3.1 Buat VM

Di Proxmox UI:

1. **Create VM**.
2. Name: `dropdeck-prod`.
3. ISO: Ubuntu Server 24.04 LTS.
4. System: default, tambahkan **QEMU Guest Agent** jika tersedia.
5. Disk: **80 GB**, VirtIO SCSI, discard aktif.
6. CPU: **4 cores**, type `host`.
7. RAM: **8192 MB**; ballooning boleh aktif.
8. Network: bridge `vmbr0`, model VirtIO.
9. Mulai VM dan install Ubuntu Server.
10. Buat user admin (contoh `deploy`), pasang SSH key, aktifkan OpenSSH.

Jika VM berada di belakang NAT Proxmox:

- Berikan IP privat statis atau DHCP reservation.
- Forward TCP `80` dan `443` dari router/Proxmox ke IP VM.
- **Jangan** forward `3000`; akses panel lewat SSH tunnel/VPN.

### 3.2 Update dan hardening dasar

```bash
sudo apt update
sudo apt full-upgrade -y
sudo apt install -y qemu-guest-agent curl ca-certificates gnupg ufw fail2ban
sudo systemctl enable --now qemu-guest-agent
sudo timedatectl set-timezone Asia/Jakarta
```

Buat user deploy jika belum:

```bash
sudo adduser deploy
sudo usermod -aG sudo deploy
sudo mkdir -p /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
# tempel public key ke /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
sudo chown -R deploy:deploy /home/deploy/.ssh
```

Hardening SSH (`/etc/ssh/sshd_config.d/99-dropdeck.conf`):

```conf
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```

Lalu:

```bash
sudo sshd -t
sudo systemctl reload ssh
```

### 3.3 Swap

```bash
swapon --show
free -h
```

Jika belum ada swap 4 GB:

```bash
sudo fallocate -l 4G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 3.4 Firewall VM

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
# Batasi 3000 hanya dari IP admin/VPN. Ganti IP contoh.
sudo ufw allow from 203.0.113.10 to any port 3000 proto tcp
sudo ufw enable
sudo ufw status verbose
```

Tetap batasi `3000` di Proxmox/router karena Docker dapat melewati UFW.

### 3.5 Verifikasi VM

```bash
ip -4 addr show
ip route
ping -c 3 1.1.1.1
curl -4 -s https://ifconfig.me
```

IP yang muncul harus sesuai dengan DNS A record.

---

## 4. Instalasi Dokploy

### 4.1 Cek port

Dokploy membutuhkan `80`, `443`, dan `3000` kosong.

```bash
sudo ss -tulpn | grep -E ':80|:443|:3000' || true
```

Jika ada Nginx/Apache/Caddy yang berjalan, hentikan dan disable dulu:

```bash
sudo systemctl disable --now nginx 2>/dev/null || true
sudo systemctl disable --now apache2 2>/dev/null || true
sudo systemctl disable --now caddy 2>/dev/null || true
```

Jangan menjalankan installer di Proxmox host. Jalankan **di dalam VM**.

### 4.2 Install Dokploy

Jalankan sebagai root di VM, setelah port `80`, `443`, dan `3000` dipastikan kosong:

```bash
curl -sSL https://dokploy.com/install.sh | sh
```

Script installer resmi melakukan:

- Pemeriksaan root, Linux non-container, dan port `80`/`443`/`3000`.
- Instalasi Docker lewat `get.docker.com` jika Docker belum ada.
- `docker swarm leave --force` (jika perlu), lalu `docker swarm init --advertise-addr ...`.
- Pembuatan `dokploy-network` sebagai overlay network yang attachable.
- Pembuatan secret Postgres untuk panel Dokploy.
- Menjalankan Postgres, Dokploy, dan Traefik; Traefik dihubungkan ke `dokploy-network`.
- Installer `dokploy.com/install.sh` memilih alamat advertise secara otomatis (preferensi RFC1918, fallback IP publik).

Jika server memiliki beberapa interface dan auto-detect salah, set `ADVERTISE_ADDR` inline:

```bash
curl -sSL https://dokploy.com/install.sh | sudo ADVERTISE_ADDR=192.168.1.100 sh
```

> **Peringatan:** Jika VM ini sudah menjadi bagian dari Docker Swarm lain, installer dapat menjalankan `docker swarm leave --force`. Jangan lakukan pada server yang masih melayani service Swarm lain. Gunakan VM baru/khusus.

Catatan Proxmox LXC: installer mendeteksi LXC dan menambahkan `--endpoint-mode dnsrr` untuk kompatibilitas. Meski didukung, **VM tetap direkomendasikan** untuk produksi karena Docker Swarm di LXC lebih rawan masalah networking/storage.

Untuk versi tertentu, gunakan asset `install.sh` dari release, bukan script `latest`:

```bash
curl -sL https://github.com/Dokploy/dokploy/releases/download/v0.15.0/install.sh | sh
```

Dokumentasi resmi:

- <https://docs.dokploy.com/docs/core/installation>
- <https://docs.dokploy.com/docs/core/manual-installation>

### 4.3 Verifikasi instalasi

```bash
sudo docker version
sudo docker service ls
sudo docker ps
sudo curl -I http://127.0.0.1:3000
```

Yang diharapkan:

- `docker service ls` menampilkan service Dokploy dan Postgres.
- `docker ps` menampilkan container `dokploy-traefik` (Traefik berjalan sebagai container, bukan service Swarm).
- `curl` ke `127.0.0.1:3000` mengembalikan HTTP response (biasanya `200` atau `302`).

Jangan menjalankan `docker swarm leave` setelah Dokploy aktif.

### 4.4 Akses panel pertama kali

Jangan buka `3000` ke publik. Gunakan SSH tunnel dari komputer admin:

```bash
ssh -L 3000:127.0.0.1:3000 deploy@IP-VM
```

Lalu buka:

```text
http://localhost:3000
```

Buat akun admin pertama dengan password kuat. Simpan kredensial di password manager.

Setelah login:

- Periksa **Servers** / **Settings** dan pastikan server terdeteksi.
- Jangan mengubah network Traefik secara manual.
- Jika panel akan diakses rutin, gunakan VPN; jika memakai domain panel, aktifkan HTTPS dan batasi akses.

### 4.5 Hardening host setelah Dokploy

Docker log rotation:

```bash
sudo mkdir -p /etc/docker
sudo tee /etc/docker/daemon.json >/dev/null <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
JSON
sudo systemctl restart docker
```

> Restart Docker akan me-restart container. Lakukan sebelum deploy aplikasi atau saat maintenance window.

Aktifkan unattended upgrades untuk patch keamanan OS:

```bash
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure --priority=low unattended-upgrades
```

Update Dokploy dan Docker tidak otomatis; jadwalkan maintenance terpisah.

---

## 5. Preflight DropDeck (Wajib Sebelum Deploy)

Bagian ini menutup gap sebelum deploy pertama. Patch konfigurasi 5.1, 5.3, 5.4, dan 5.5 sudah diterapkan di repo; 5.2 dan 5.6 tetap wajib diset saat deploy.

### 5.1 Trusted proxy di `bootstrap/app.php`

**Status:** sudah diterapkan di `bootstrap/app.php`. Tanpa ini Laravel bisa salah mendeteksi HTTPS, cookie `secure` tidak terkirim, URL absolut salah, dan IP klien tercatat sebagai IP Traefik.

**Implementasi saat ini:**

```php
use Illuminate\Http\Request;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(
        at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'fd00::/8',
        ],
        headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO,
    );

    $middleware->append(SecurityHeaders::class);
})
```

Catatan:

- Gunakan CIDR internal, bukan `*`, sesuai `docs/04-RULES.md`.
- `fd00::/8` hanya perlu jika Docker/Traefik memakai IPv6.
- Setelah perubahan, jalankan test dan build ulang.

### 5.2 Cookie secure

Tambahkan di Environment Dokploy:

```dotenv
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true
```

`config/session.php` membaca `SESSION_SECURE_COOKIE`; tanpa ini cookie bisa dikirim lewat HTTP dan login bisa berperilaku aneh di balik Traefik.

### 5.3 Network Traefik untuk `app`

**Status:** sudah diterapkan di `compose.prod.yaml`. Domain Dokploy membutuhkan Traefik dapat menjangkau `app:8000`.

Konfigurasi saat ini:

```yaml
services:
  app:
    networks:
      - default
      - dokploy-network

networks:
  default:
  dokploy-network:
    external: true
```

Catatan:

- `app` tetap berada di network default agar bisa menghubungi `mariadb` dan `rustfs`.
- `app` juga berada di `dokploy-network` agar Traefik bisa menjangkau port `8000`.
- Jangan mengaktifkan **Isolated Deployments** untuk stack multi-service ini sampai benar-benar diuji; jika aktif, pastikan semua service yang saling membutuhkan berada di network yang sama.
- Dokploy umumnya menambahkan label Traefik otomatis saat domain dibuat; jangan menambahkan label manual kecuali diperlukan.

### 5.4 Healthcheck worker

**Status:** sudah diterapkan di `compose.prod.yaml`. Image `prod` memiliki `HEALTHCHECK /up`; service `queue` dan `scheduler` mewarisinya padahal tidak membuka port HTTP, sehingga bisa ditandai `unhealthy`.

Konfigurasi saat ini:

```yaml
  queue:
    <<: *php
    healthcheck:
      disable: true
    # ...

  scheduler:
    <<: *php
    healthcheck:
      disable: true
    # ...
```

Ini hanya menonaktifkan healthcheck HTTP untuk worker; `app` tetap memakai healthcheck `/up`.

### 5.5 Pin image RustFS

`compose.prod.yaml` sudah memakai tag versi eksplisit:

```yaml
image: docker.io/rustfs/rustfs:1.0.4
```

Jangan memakai `latest` di produksi. Saat upgrade, uji tag baru lalu perbarui compose dan changelog.

### 5.6 Batasi kredensial RustFS

Saat ini `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` dipakai untuk root RustFS **dan** aplikasi. Artinya kompromi aplikasi = akses penuh object storage.

Untuk MVP:

- Bucket tetap privat.
- RustFS hanya di network internal.
- Console `9001` tidak diekspos.
- Ganti kredensial default (`rustfsadmin`) dengan nilai acak kuat.
- Jika RustFS mendukung service account/IAM terbatas, buat kredensial aplikasi terpisah dan gunakan untuk `AWS_*`.

### 5.7 Validasi build lokal

```bash
npm run build
podman build -f infra/Containerfile --target prod -t dropdeck-prod:test .
podman run --rm --entrypoint php dropdeck-prod:test -v
podman run --rm --entrypoint php dropdeck-prod:test artisan --version
```

Yang diharapkan:

- `npm run build` selesai tanpa memerlukan akses internet; font Instrument Sans disimpan lokal di `resources/fonts/instrument-sans`.
- Build image selesai tanpa error.
- PHP `8.5.12`.
- Laravel bisa boot.

Jika build gagal karena `imagick`, lihat `[VERIFIKASI]` di `infra/Containerfile`; fallback adalah GD dengan `THUMBNAIL_PDF_ENABLED=false`.

### 5.8 Push repo

```bash
git status
git add .github/workflows/tests.yml compose.prod.yaml bootstrap/app.php .env.example.snippet docs/ vite.config.js resources/fonts/
git commit -m "docs(deploy): add complete Dokploy production guide"
git push origin main
```

Jangan commit `.env`, `vendor/`, `node_modules/`, atau file backup.

### 5.9 Checklist preflight

- [x] `trustProxies` sudah ditambahkan.
- [ ] `SESSION_SECURE_COOKIE=true` sudah ada di Environment Dokploy.
- [x] `dokploy-network` sudah ditambahkan ke `app`.
- [x] Healthcheck `queue`/`scheduler` dinonaktifkan.
- [x] Tag RustFS sudah dipin (`1.0.4`).
- [ ] Kredensial RustFS default sudah diganti.
- [x] Build prod lokal sukses.
- [x] Repo sudah di-push ke `main`.
- [ ] `APP_KEY` sudah dibuat dan disimpan.
- [ ] Google OAuth redirect produksi sudah didaftarkan.
- [ ] SMTP produksi sudah diverifikasi.

---

## 6. Menyiapkan Aplikasi di Dokploy

### 6.1 Buat project

1. Buka panel Dokploy.
2. **Create Project**.
3. Name: `DropDeck`.
4. Simpan.

### 6.2 Buat service Compose

Di project:

1. **Create Service** → **Compose**.
2. Name: `dropdeck`.
3. Source:
   - Provider: **GitHub**.
   - Repo: `Raka-coder/dropdeck-saas`.
   - Branch: `main`.
   - Jika repo privat, hubungkan GitHub App/deploy key.
4. Compose:
   - Compose file: `compose.prod.yaml`.
   - Build path/context: root repo (`.`).
5. Auto Deploy:
   - Aktifkan hanya jika Anda siap setiap push ke `main` memicu deploy + migrasi.
   - Jika aktif, lindungi branch `main` dengan review/CI.
6. Simpan.

### 6.3 Isi Environment

Buka tab **Environment**. Tempel blok dari bagian 7, ganti semua placeholder.

Catatan penting:

- Dokploy menulis environment ke file `.env` di direktori Compose; `compose.prod.yaml` memakai `env_file: .env`.
- Context7/Dokploy docs mengonfirmasi `env_file` didukung. Tetap gunakan **Preview Compose** untuk memastikan variabel masuk.
- Compose juga memakai interpolasi `${DB_DATABASE}`, `${DB_USERNAME}`, `${DB_PASSWORD}`, `${DB_ROOT_PASSWORD}` untuk service `mariadb`; nilai-nilai itu harus ada di `.env`.
- Jangan menaruh nilai rahasia di `compose.prod.yaml`.
- Hindari karakter `$` pada secret; Compose dapat melakukan interpolasi. Gunakan hex/base64 atau escape dengan benar.
- Jika mengubah environment, lakukan **Redeploy** agar container dibuat ulang.

### 6.4 Domain dan TLS

Buka tab **Domains**:

| Field | Nilai |
|:---|:---|
| Domain | `dropdeck.example.com` |
| Service | `app` |
| Port | `8000` |
| HTTPS | Aktif |
| Certificate | Let's Encrypt |

Klik **Create**.

Yang diharapkan:

- Dokploy menambahkan label Traefik secara otomatis.
- Traefik menerbitkan sertifikat Let's Encrypt.
- `https://dropdeck.example.com/up` mengembalikan `200`.

Jangan menambahkan `ports:` ke `app`; gunakan `expose: 8000` + domain Dokploy.

### 6.5 Deploy pertama

1. Klik **Deploy**.
2. Buka build logs.
3. Tunggu build `prod` selesai.
4. Pastikan service `mariadb` healthy.
5. Pastikan `rustfs-init` selesai dengan exit `0`.
6. Pastikan `app` menjalankan `migrate --force`.
7. Pastikan `queue` dan `scheduler` berjalan.

Urutan startup yang diharapkan:

```text
mariadb healthy
  → app start (wait DB, migrate, optimize)
  → queue start (wait DB)
  → scheduler start (wait DB)
rustfs start
  → rustfs-init create bucket → exit 0
```

Jika `app` restart terus, lihat log `app` dan `mariadb`.

### 6.6 Verifikasi container

Di Dokploy Terminal atau SSH host:

```bash
docker ps
docker compose ls
docker logs --tail=100 <nama-container-app>
docker logs --tail=100 <nama-container-queue>
docker logs --tail=100 <nama-container-scheduler>
docker logs --tail=100 <nama-container-rustfs>
```

Cari nama container:

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Image}}'
```

Yang diharapkan:

- `app` healthy.
- `queue` dan `scheduler` running (healthcheck disabled).
- `mariadb` healthy.
- `rustfs` running.
- `rustfs-init` exited `0`.

---

## 7. Environment Produksi

### 7.1 Template lengkap

Tempel ke **Dokploy → Environment**. Ganti semua nilai `<...>`.

```dotenv
# ============================================================================
# DropDeck — produksi (Dokploy)
# Jangan commit file .env ini.
# ============================================================================

APP_NAME=DropDeck
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dropdeck.example.com
APP_KEY=base64:<32-byte-base64-key>
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stderr
LOG_LEVEL=warning

# --- Database ---------------------------------------------------------------
DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=dropdeck
DB_USERNAME=dropdeck
DB_PASSWORD=<password-app-kuat>
DB_ROOT_PASSWORD=<password-root-kuat>

# --- Session / cache / queue ------------------------------------------------
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true
SESSION_DOMAIN=
CACHE_STORE=database
QUEUE_CONNECTION=database

# --- Storage (RustFS / S3-compatible) ---------------------------------------
FILESYSTEM_DISK=vault
AWS_ACCESS_KEY_ID=<access-key-rustfs>
AWS_SECRET_ACCESS_KEY=<secret-key-rustfs>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=dropdeck
AWS_ENDPOINT=http://rustfs:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

# --- Google OAuth -----------------------------------------------------------
GOOGLE_CLIENT_ID=<google-client-id>
GOOGLE_CLIENT_SECRET=<google-client-secret>
GOOGLE_REDIRECT_URI=https://dropdeck.example.com/auth/google/callback

# --- SMTP produksi (jangan pakai Mailpit) -----------------------------------
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=<smtp-username>
MAIL_PASSWORD=<smtp-password>
MAIL_SCHEME=
MAIL_FROM_ADDRESS=noreply@dropdeck.example.com
MAIL_FROM_NAME=DropDeck

# --- Batas bisnis DropDeck --------------------------------------------------
DROPDECK_QUOTA_MB=2048
DROPDECK_MAX_FILE_MB=100
DROPDECK_MAX_FILES_PER_CARD=20
DROPDECK_MAX_BOARDS=20
DROPDECK_MAX_COLUMNS=15
DROPDECK_MAX_CARDS_PER_BOARD=500
DROPDECK_MAX_LABELS=20
DROPDECK_MAX_CHECKLIST=50
DROPDECK_MAX_ACTIVE_LINKS=100
DROPDECK_LINK_MAX_DAYS=30
DROPDECK_LINK_MAX_DL=100
DROPDECK_LOG_RETENTION_DAYS=90
DROPDECK_LINK_RETENTION_DAYS=30
DROPDECK_UPLOAD_PER_MINUTE=30
DROPDECK_LINK_CREATE_PER_MINUTE=30
THUMBNAIL_PDF_ENABLED=false
```

### 7.2 Generate secret

`APP_KEY`:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Atau, di repo lokal:

```bash
php artisan key:generate --show
```

Password database dan RustFS:

```bash
openssl rand -hex 24
openssl rand -hex 32
```

Gunakan nilai berbeda untuk:

- `DB_ROOT_PASSWORD`
- `DB_PASSWORD`
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- admin Dokploy
- SMTP

Simpan semua di password manager.

### 7.3 Invarian yang tidak boleh dilanggar

| Invarian | Konsekuensi jika dilanggar |
|:---|:---|
| `APP_KEY` dibuat sekali dan tidak diganti | Sesi dan data terenkripsi menjadi tidak valid |
| `DB_PASSWORD` hanya berlaku saat volume MariaDB pertama kali dibuat | Mengubah env setelah volume ada **tidak** mengubah password user di DB |
| `DB_ROOT_PASSWORD` sama | Mengubah env setelah init tidak mengubah password root |
| `AWS_*` sama dengan kredensial RustFS | Ganti kredensial harus dilakukan di RustFS dan env secara bersamaan |
| `APP_URL` harus HTTPS dan sama dengan domain | OAuth redirect, cookie, dan asset URL bisa salah |
| `RUN_MIGRATIONS=true` hanya di `app` | Migrasi ganda/race jika dijalankan di worker |
| Jangan scale `app` > 1 selama migrasi di entrypoint | Migrasi bersamaan dapat gagal |

### 7.4 Google OAuth

Di Google Cloud Console:

1. Buat **OAuth client ID** tipe **Web application**.
2. Authorized JavaScript origins:
   - `https://dropdeck.example.com`
3. Authorized redirect URIs:
   - `https://dropdeck.example.com/auth/google/callback`
4. Salin Client ID dan Client Secret ke Environment.
5. Pastikan `GOOGLE_REDIRECT_URI` **persis** sama.
6. Jika domain berubah, perbarui Google Console dan environment, lalu redeploy.

### 7.5 SMTP

Gunakan provider SMTP produksi (Brevo, Mailgun, Postmark, Amazon SES, Resend SMTP, dan sebagainya).

- Port `587` umumnya STARTTLS; biarkan `MAIL_SCHEME=` kosong.
- Port `465` umumnya implicit TLS; set `MAIL_SCHEME=smtps`.
- Pastikan domain pengirim memiliki SPF dan DKIM.
- `MAIL_FROM_ADDRESS` harus alamat yang diizinkan provider.
- Jangan gunakan `MAIL_MAILER=log` di produksi; email verifikasi tidak akan terkirim.

### 7.6 RustFS

- `AWS_BUCKET=dropdeck`.
- `AWS_ENDPOINT=http://rustfs:9000` (internal).
- `AWS_USE_PATH_STYLE_ENDPOINT=true`.
- `AWS_DEFAULT_REGION=us-east-1` (nilai bebas, harus konsisten).
- Bucket dibuat otomatis oleh `rustfs-init`.
- Bucket harus **private**.
- Console `9001` tidak diekspos ke publik.
- Ganti kredensial default; jangan gunakan `rustfsadmin` di produksi.

Jika ingin menonaktifkan console RustFS setelah setup, ubah `RUSTFS_CONSOLE_ENABLE` menjadi `"false"` di `compose.prod.yaml` (setelah bucket dan kredensial final). Ini mengurangi permukaan serangan.

---

## 8. Deploy Pertama (End-to-End)

### 8.1 Sebelum klik Deploy

- [ ] VM lulus verifikasi.
- [ ] Dokploy aktif dan panel bisa diakses via tunnel.
- [ ] DNS mengarah ke IP VM.
- [ ] Port 80/443 terbuka; 3000 tertutup dari publik.
- [ ] Environment sudah diisi.
- [ ] `APP_KEY` sudah disimpan.
- [x] Preflight patch sudah diterapkan.
- [x] Build lokal sukses.
- [x] Repo sudah di-push.

### 8.2 Proses deploy

1. Dokploy → service `dropdeck` → **Deploy**.
2. Pantau build log.
3. Tunggu `mariadb` healthy.
4. Tunggu `app` migrate + optimize.
5. Tunggu `queue`/`scheduler` start.
6. Tunggu `rustfs-init` exit `0`.
7. Buka domain dan cek `/up`.

Jika build berhasil tetapi domain 502, lihat bagian Troubleshooting.

### 8.3 Verifikasi database dan bucket

Gunakan Dokploy Terminal pada container `app`:

```bash
php artisan migrate:status
php artisan about
```

Cek bucket dari container `app`:

```bash
php artisan tinker --execute="Storage::disk('vault')->put('healthcheck.txt', 'ok');"
php artisan tinker --execute="echo Storage::disk('vault')->get('healthcheck.txt');"
php artisan tinker --execute="Storage::disk('vault')->delete('healthcheck.txt');"
```

Jika gagal:

- Cek log `rustfs`.
- Cek log `rustfs-init`.
- Pastikan kredensial `AWS_*` sama dengan `RUSTFS_*`.
- Pastikan `AWS_ENDPOINT` dapat dijangkau dari `app`.

### 8.4 Buat akun pertama

1. Buka `https://dropdeck.example.com/register`.
2. Daftar dengan email nyata.
3. Cek email verifikasi.
4. Login.
5. Jika email verifikasi tidak masuk, cek SMTP dan log `app`/`queue`.

Tidak ada akun admin khusus; akun pertama adalah user biasa. Jangan menjalankan `DemoSeeder` di produksi.

### 8.5 Smoke test wajib

- [ ] `https://dropdeck.example.com/up` → `200`.
- [ ] Halaman login/register tampil dengan CSS.
- [ ] Register + verifikasi email.
- [ ] Login email/password.
- [ ] Login Google.
- [ ] Buat papan, kolom, kartu.
- [ ] Upload file kecil.
- [ ] Thumbnail gambar muncul.
- [ ] Buat DropLink untuk file.
- [ ] Buka DropLink di incognito.
- [ ] Unduh sebagai guest.
- [ ] Buat DropLink dengan password; uji password salah/benar.
- [ ] Revoke DropLink; pastikan tidak bisa diakses.
- [ ] Cek dashboard dan storage meter.
- [ ] Cek `failed_jobs` kosong.
- [ ] Cek log `queue` dan `scheduler`.
- [ ] Cek cookie `Secure`, `HttpOnly`, `SameSite=Lax`.
- [ ] Cek `/.env` mengembalikan `404`.
- [ ] Cek `https://dropdeck.example.com/storage/...` tidak mengekspos file.
- [ ] Cek tidak ada stack trace saat error.

### 8.6 Cek keamanan respons

```bash
curl -I https://dropdeck.example.com/up
curl -I https://dropdeck.example.com/.env
curl -I https://dropdeck.example.com/storage/
```

Yang diharapkan:

- `/up` → `200`.
- `/.env` → `404`.
- `/storage/` → `404`.
- Header `X-Content-Type-Options: nosniff`.
- Header `X-Frame-Options: DENY`.
- Tidak ada `X-Powered-By`.
- Cookie sesi `Secure; HttpOnly; SameSite=Lax`.

Cek sertifikat:

```bash
echo | openssl s_client -connect dropdeck.example.com:443 -servername dropdeck.example.com 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates
```

---

## 9. Operasi Harian

### 9.1 Log

Semua log aplikasi harus ke `stderr`:

```dotenv
LOG_CHANNEL=stderr
```

Lihat log:

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}'
docker logs --tail=200 <container-app>
docker logs -f <container-queue>
docker logs -f <container-scheduler>
```

Di Dokploy, gunakan tab **Logs** pada service.

### 9.2 Queue

Cek failed jobs:

```bash
docker exec -it <container-app> php artisan queue:failed
```

Retry:

```bash
docker exec -it <container-app> php artisan queue:retry all
```

Setelah deploy kode baru, worker harus restart. Dokploy biasanya membuat ulang container; jika tidak:

```bash
docker exec -it <container-app> php artisan queue:restart
```

Jangan menjalankan `queue:flush` tanpa memahami bahwa semua failed jobs akan dihapus.

### 9.3 Scheduler

Cek daftar jadwal:

```bash
docker exec -it <container-app> php artisan schedule:list
```

Log scheduler:

```bash
docker logs --tail=200 <container-scheduler>
```

Jadwal berjalan mengikuti timezone aplikasi. `config/app.php` saat ini `timezone => 'UTC'`. Artinya job harian berjalan pada tengah malam UTC, bukan WIB. Jika bisnis membutuhkan WIB, ubah konfigurasi timezone aplikasi secara sadar (dan uji ulang), atau sesuaikan jadwal.

Job terjadwal:

- `PruneExpiredDropLinks` — harian.
- `PruneAccessLogs` — harian.
- `ReconcileStorageUsage` — harian.
- `queue:prune-failed` — mingguan.

### 9.4 Kesehatan

```bash
curl -fsS https://dropdeck.example.com/up
docker exec -it <container-app> php artisan about
docker stats --no-stream
```

`/up` hanya memeriksa aplikasi bisa boot, bukan database/RustFS/queue. Pantau komponen lain secara terpisah.

### 9.5 Disk dan image

```bash
df -h
docker system df
docker image ls
docker builder du
```

Bersihkan yang aman:

```bash
docker image prune
docker builder prune
```

Jangan menghapus volume `mariadb_data` atau `rustfs_data`.

### 9.6 Update rutin

- **Aplikasi:** push ke `main` → deploy → smoke test.
- **Dokploy:** backup → baca release notes → update → verifikasi panel/Traefik.
- **OS:** `apt update && apt full-upgrade`; reboot pada maintenance window.
- **Image dasar:** pin versi; uji build; update berkala.
- **Dependensi:** `composer audit`, `npm audit`; rencanakan patch.

---

## 10. Backup dan Restore

### 10.1 Data yang wajib dibackup

| Data | Metode | Frekuensi | Retensi |
|:---|:---|:---|:---|
| MariaDB `dropdeck` | `mariadb-dump` + gzip | Harian | 14–30 hari |
| RustFS bucket `dropdeck` | `aws s3 sync` ke backup / volume snapshot | Harian | 14–30 hari |
| Environment/secret | Ekspor dari Dokploy + password manager | Setiap perubahan | Permanen |
| `APP_KEY` | Password manager | Sekali | Permanen |
| Compose/repo | Git | Setiap perubahan | Permanen |
| VM | Proxmox Backup Server / `vzdump` | Mingguan | 4–12 minggu |
| Dokploy config | Backup data Dokploy | Mingguan | 4 minggu |

> Backup di disk yang sama **bukan** backup. Simpan salinan di storage/NAS/offsite yang terpisah.

### 10.2 Backup MariaDB

Buat direktori dan file environment backup:

```bash
sudo mkdir -p /var/backups/dropdeck /etc/dropdeck
sudo chmod 700 /var/backups/dropdeck /etc/dropdeck
sudo tee /etc/dropdeck/backup.env >/dev/null <<'EOF'
DB_ROOT_PASSWORD=ganti-dengan-password-root
DB_DATABASE=dropdeck
EOF
sudo chmod 600 /etc/dropdeck/backup.env
```

Script `/usr/local/bin/dropdeck-backup-db.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
umask 077

source /etc/dropdeck/backup.env

STAMP=$(date +%F-%H%M)
OUT="/var/backups/dropdeck/dropdeck-${STAMP}.sql.gz"

CID=$(docker ps -q \
  -f label=com.docker.compose.project=dropdeck \
  -f label=com.docker.compose.service=mariadb)

if [ -z "$CID" ]; then
  echo "container mariadb tidak ditemukan" >&2
  exit 1
fi

docker exec -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$CID" \
  mariadb-dump -u root \
  --single-transaction --quick --routines --triggers --events \
  "$DB_DATABASE" | gzip -9 > "$OUT"

gzip -t "$OUT"
echo "OK: $OUT"
find /var/backups/dropdeck -type f -name 'dropdeck-*.sql.gz' -mtime +30 -delete
```

Aktifkan:

```bash
sudo chmod 700 /usr/local/bin/dropdeck-backup-db.sh
sudo /usr/local/bin/dropdeck-backup-db.sh
```

Cron:

```bash
sudo crontab -e
```

```cron
15 2 * * * /usr/local/bin/dropdeck-backup-db.sh >> /var/log/dropdeck-backup.log 2>&1
```

### 10.3 Backup RustFS

Opsi A — **S3 sync** (disarankan untuk konsistensi objek):

Cari network RustFS:

```bash
RUSTFS_CID=$(docker ps -q \
  -f label=com.docker.compose.project=dropdeck \
  -f label=com.docker.compose.service=rustfs)

NETWORK=$(docker inspect -f \
  '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' \
  "$RUSTFS_CID" | head -n1)

echo "$NETWORK"
```

Script `/usr/local/bin/dropdeck-backup-rustfs.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
umask 077

source /etc/dropdeck/backup.env

STAMP=$(date +%F)
DEST="/var/backups/dropdeck/rustfs-${STAMP}"

RUSTFS_CID=$(docker ps -q \
  -f label=com.docker.compose.project=dropdeck \
  -f label=com.docker.compose.service=rustfs)

NETWORK=$(docker inspect -f \
  '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' \
  "$RUSTFS_CID" | head -n1)

mkdir -p "$DEST"

docker run --rm \
  --network "$NETWORK" \
  -e AWS_ACCESS_KEY_ID="$AWS_ACCESS_KEY_ID" \
  -e AWS_SECRET_ACCESS_KEY="$AWS_SECRET_ACCESS_KEY" \
  -e AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION:-us-east-1}" \
  -v "$DEST:/backup" \
  docker.io/amazon/aws-cli:2.15.30 \
  s3 sync "s3://${AWS_BUCKET:-dropdeck}" /backup \
  --endpoint-url http://rustfs:9000

echo "OK: $DEST"
```

Tambahkan `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET` ke `/etc/dropdeck/backup.env`.

Opsi B — **volume tar** (hentikan RustFS dulu agar konsisten):

```bash
docker stop <container-rustfs>
sudo tar czf /var/backups/dropdeck/rustfs-volume-$(date +%F).tar.gz \
  -C /var/lib/docker/volumes/dropdeck_rustfs_data/_data .
docker start <container-rustfs>
```

Jangan jalankan tar volume saat RustFS aktif untuk data produksi; hasil bisa tidak konsisten.

Opsi C — **Proxmox VM snapshot**:

- Snapshot bukan backup jangka panjang.
- Gunakan Proxmox Backup Server/`vzdump` untuk backup penuh VM.
- Uji restore VM di lingkungan terpisah.

### 10.4 Backup konfigurasi dan secret

- Ekspor environment Dokploy (tanpa menaruhnya di Git).
- Simpan `APP_KEY`, password DB, kredensial RustFS, SMTP, Google secret di password manager.
- Backup konfigurasi/data Dokploy sesuai dokumentasi versi Anda (biasanya di `/etc/dokploy`; verifikasi).
- Jangan mengandalkan snapshot VM sebagai satu-satunya backup.

### 10.5 Restore MariaDB

1. Hentikan `app`, `queue`, `scheduler` agar tidak ada write.
2. Pilih backup terbaru yang valid.
3. Restore ke database yang ada.
4. Jalankan migrasi jika perlu.
5. Nyalakan service.
6. Smoke test.

Contoh:

```bash
# Hentikan service dari Dokploy UI atau docker stop container terkait.
CID=$(docker ps -q \
  -f label=com.docker.compose.project=dropdeck \
  -f label=com.docker.compose.service=mariadb)

gunzip -c /var/backups/dropdeck/dropdeck-2026-10-27-0215.sql.gz \
  | docker exec -i -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$CID" \
      mariadb -u root "$DB_DATABASE"
```

Jika restore ke server baru:

- Deploy stack dengan environment yang sama.
- Tunggu MariaDB init selesai.
- Restore dump.
- Pastikan user `dropdeck` dan password sesuai environment.

### 10.6 Restore RustFS

1. Hentikan `app`, `queue`, `scheduler`.
2. Pastikan RustFS berjalan dan bucket `dropdeck` sudah dibuat.
3. Sync balik dari backup.
4. Nyalakan service.
5. Uji unduh file lama.

Contoh:

```bash
RUSTFS_CID=$(docker ps -q \
  -f label=com.docker.compose.project=dropdeck \
  -f label=com.docker.compose.service=rustfs)

NETWORK=$(docker inspect -f \
  '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' \
  "$RUSTFS_CID" | head -n1)

docker run --rm \
  --network "$NETWORK" \
  -e AWS_ACCESS_KEY_ID="$AWS_ACCESS_KEY_ID" \
  -e AWS_SECRET_ACCESS_KEY="$AWS_SECRET_ACCESS_KEY" \
  -e AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION:-us-east-1}" \
  -v /var/backups/dropdeck/rustfs-2026-10-27:/backup \
  docker.io/amazon/aws-cli:2.15.30 \
  s3 sync /backup "s3://${AWS_BUCKET:-dropdeck}" --delete \
  --endpoint-url http://rustfs:9000
```

Catatan:

- `s3 sync` tidak membackup bucket policy/versioning; dokumentasikan konfigurasi bucket.
- Jika bucket memakai versioning, restore perlu strategi tambahan.
- Setelah restore, cek `storage_used_bytes` user; job `ReconcileStorageUsage` akan mengoreksi harian.

### 10.7 Urutan disaster recovery

1. Siapkan VM baru + Dokploy.
2. Deploy stack dengan environment yang sama (termasuk `APP_KEY`).
3. Hentikan `app`, `queue`, `scheduler`.
4. Restore MariaDB.
5. Restore RustFS.
6. Nyalakan service.
7. Arahkan DNS ke server baru.
8. Smoke test.
9. Catat waktu restore (RTO) dan data terakhir (RPO).

Target contoh:

- **RPO:** 24 jam (backup harian).
- **RTO:** 2–4 jam.

Uji restore minimal sekali sebelum demo/rilis, lalu setiap kuartal.

---

## 11. Upgrade dan Rollback

### 11.1 Deploy versi aplikasi baru

1. Backup database + RustFS.
2. Pastikan CI/test lulus.
3. Push ke `main`.
4. Deploy di Dokploy.
5. Pantau migrasi dan log.
6. Smoke test.
7. Jika gagal, rollback.

Karena `app` menjalankan migrasi saat start, jangan scale `app` lebih dari satu selama model ini dipakai.

### 11.2 Rollback aplikasi

- Kembali ke commit sebelumnya di Git, lalu deploy ulang.
- Jika migrasi baru **kompatibel**, rollback kode aman.
- Jika migrasi destruktif/tidak kompatibel, restore database dari backup.
- Jangan mengedit migrasi yang sudah pernah dijalankan di produksi; buat migrasi baru.

### 11.3 Upgrade Dokploy

1. Backup VM/volume dan environment.
2. Baca release notes.
3. Jadwalkan maintenance.
4. Update Dokploy:
   ```bash
   curl -sSL https://dokploy.com/install.sh | sh -s update
   ```
5. Traefik **tidak** ikut ter-update otomatis oleh Dokploy. Jika perlu versi Traefik baru, ikuti prosedur manual resmi dan cek breaking changes.
6. Verifikasi Traefik, panel, dan aplikasi.
7. Jika gagal, restore snapshot VM.

### 11.4 Upgrade image dasar

- FrankenPHP/PHP dipin di `infra/Containerfile`.
- RustFS dipin setelah pengujian.
- MariaDB dipin ke `11.4`.
- Node/Composer hanya dipakai saat build; tetap pin jika reproducibility penting.
- Uji build + smoke test di staging sebelum produksi.

---

## 12. Keamanan Produksi

### 12.1 Checklist

- [ ] SSH hanya key; root login dimatikan.
- [ ] Firewall hanya 22/80/443; 3000 dibatasi.
- [ ] Panel Dokploy tidak publik.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `APP_KEY` kuat dan disimpan.
- [ ] `SESSION_SECURE_COOKIE=true`.
- [ ] Trusted proxy dikonfigurasi ke CIDR internal.
- [ ] HTTPS aktif; HTTP redirect ke HTTPS.
- [ ] Cookie `HttpOnly`, `SameSite=Lax`, `Secure`.
- [ ] Header keamanan aktif.
- [ ] CSP dipantau (saat ini Report-Only).
- [ ] Rate limit upload/DropLink aktif.
- [ ] Bucket RustFS privat.
- [ ] MariaDB/RustFS tidak terbuka ke internet.
- [ ] Console RustFS tidak publik.
- [ ] Kredensial default RustFS diganti.
- [ ] Backup terenkripsi dan diuji restore.
- [ ] Dependensi diaudit.
- [ ] Log tidak memuat token/password.
- [ ] `failed_jobs` dipantau.
- [ ] Update OS/Dokploy terjadwal.

### 12.2 Backlog hardening yang diketahui

| Item | Status | Tindakan |
|:---|:---|:---|
| `trustProxies` | Fixed | Terpasang di `bootstrap/app.php` (bagian 5.1) |
| `SESSION_SECURE_COOKIE` belum diset | Gap | Set `true` di Environment |
| Worker mewarisi healthcheck `/up` | Fixed | Dinonaktifkan di `compose.prod.yaml` |
| RustFS `latest` | Fixed | Dipin ke `1.0.4` |
| Kredensial RustFS root dipakai app | Risiko | Ganti acak; pertimbangkan service account |
| CSP masih Report-Only + localhost | Hardening | Hapus origin dev, uji, lalu enforce |
| Livewire `4.4.7` advisory `GHSA-mhf7-xp6r-6qrc` | Fixed | Upgrade ke `4.4.8` |
| PHPUnit `13.4.2` advisory `GHSA-9vw2-f4vp-x7gf` | Fixed | Upgrade ke `13.4.5` via Pest `5.3.4` |
| `npm audit` 7 vuln di build tooling (`vite-plus`/`vitest`/`tinypool`) | Risiko build-time | Tidak masuk runtime image; upgrade `vite-plus@1.4.0` terpisah karena breaking |

### 12.3 Yang tidak boleh dilakukan

- Jangan mengekspos `3306`, `9000`, `9001`, atau `3000` ke internet.
- Jangan menaruh `.env` atau secret di Git.
- Jangan memakai `APP_DEBUG=true` di produksi.
- Jangan memakai `MAIL_MAILER=log` di produksi.
- Jangan menjalankan `DemoSeeder` di produksi.
- Jangan menghapus volume `mariadb_data`/`rustfs_data` untuk “membersihkan” server.
- Jangan mengganti `APP_KEY` tanpa rencana.
- Jangan mengedit migrasi yang sudah berjalan di produksi.

---

## 13. Monitoring dan Alerting

### 13.1 Yang dipantau

| Komponen | Metrik/Gejala | Alat |
|:---|:---|:---|
| Aplikasi | `/up` HTTP 200 | Uptime Kuma/Dokploy |
| Traefik | 502/504, certificate expiry | Logs + uptime |
| MariaDB | container healthy, disk, koneksi | Docker/Proxmox |
| RustFS | container running, bucket bisa diakses | Smoke test + logs |
| Queue | `failed_jobs`, backlog | `queue:failed`, logs |
| Scheduler | job harian berjalan | logs |
| Disk | `df -h`, `docker system df` | Proxmox alert |
| Backup | file terbentuk, `gzip -t` | cron + alert |
| TLS | expiry < 14 hari | monitoring/calendar |
| Keamanan | login gagal, rate limit | logs |

### 13.2 Perintah monitoring

```bash
curl -fsS https://dropdeck.example.com/up
docker ps --format 'table {{.Names}}\t{{.Status}}'
docker stats --no-stream
df -h
docker system df
docker exec -it <container-app> php artisan queue:failed
docker exec -it <container-app> php artisan schedule:list
```

Log:

```bash
docker logs --tail=200 <container-app>
docker logs --tail=200 <container-queue>
docker logs --tail=200 <container-scheduler>
```

### 13.3 Ambang peringatan yang disarankan

- Disk > 80% → warning; > 90% → critical.
- `failed_jobs` > 0 → investigasi.
- `/up` gagal 2x berturut-turut → alert.
- Sertifikat < 14 hari → perpanjang/cek Traefik.
- Backup gagal 1x → alert.
- Container restart berulang → investigasi.

---

## 14. Troubleshooting

| Gejala | Kemungkinan penyebab | Tindakan |
|:---|:---|:---|
| Domain 502/504 | `app` unhealthy, port salah, Traefik tidak satu network | Cek `docker ps`, log `app`, pastikan domain service `app` port `8000`, pastikan `dokploy-network` |
| Domain 404 dari Traefik | Domain belum dibuat / service/port salah | Cek tab Domains; service `app`; port `8000` |
| `no such file or directory: .env` | Environment Dokploy belum tersimpan / `env_file` tidak ada | Simpan Environment, Preview Compose, redeploy |
| Build gagal OOM | RAM/swap kurang | Tambah RAM/swap, `docker builder prune`, build ulang |
| `SQLSTATE[HY000] [2002] Connection refused` | MariaDB belum healthy / host salah | Cek `DB_HOST=mariadb`, log MariaDB, healthcheck |
| `Access denied for user 'dropdeck'` | Password berubah setelah volume dibuat | Kembalikan password lama atau ubah user di DB |
| `rustfs-init` gagal | Kredensial/endpoint/bucket salah | Cek log init, `AWS_*`, `AWS_BUCKET`, `rustfs` |
| Upload gagal / 500 | RustFS tidak bisa diakses | Cek log `app`, `rustfs`; tes tinker storage |
| Queue tidak memproses | Worker mati / `QUEUE_CONNECTION` salah | Cek log queue, `QUEUE_CONNECTION=database`, `jobs` |
| Thumbnail tidak muncul | Imagick/queue/PDF flag | Cek `queue:failed`, Imagick, `THUMBNAIL_PDF_ENABLED` |
| Scheduler tidak jalan | Container mati / timezone | Cek log scheduler, `schedule:list`, timezone UTC |
| Google OAuth redirect mismatch | `APP_URL`/redirect URI tidak sama | Samakan Google Console + `GOOGLE_REDIRECT_URI` + HTTPS |
| Email tidak terkirim | SMTP salah / SPF/DKIM | Cek log, kredensial, port/scheme, DNS |
| Upload 413 | Batas PHP/Livewire | Cek `upload_max_filesize`, `post_max_size`, `DROPDECK_MAX_FILE_MB` |
| Redirect loop / cookie tidak masuk | `SESSION_SECURE_COOKIE`/trusted proxy | Set `true`, patch `trustProxies`, cek `APP_URL` |
| Mixed content | `APP_URL` HTTP / asset URL | Set `APP_URL=https://...`, rebuild aset |
| Disk penuh | Image/build cache/backup | `docker system df`, prune yang aman, pindahkan backup |
| Sertifikat gagal | DNS/port 80/Cloudflare | Cek DNS, port 80, mode Cloudflare, log Traefik |
| Panel 3000 tidak bisa diakses | Firewall/SSH tunnel | Gunakan tunnel, cek Proxmox firewall |
| `APP_KEY` missing | Environment belum diisi | Generate dan isi `APP_KEY`, redeploy |
| `storage_used` tidak akurat | Job rekonsiliasi belum jalan | Tunggu job harian / jalankan `ReconcileStorageUsage` |
| RustFS console tidak bisa dibuka | Memang tidak diekspos | Gunakan tunnel/override sementara, jangan publish permanen |
| Worker `unhealthy` | Mewarisi healthcheck `/up` | Disable healthcheck queue/scheduler |

### 14.1 Debug cepat

```bash
# Status container
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Image}}'

# Log app
docker logs --tail=200 <container-app>

# Cek koneksi DB dari app
docker exec -it <container-app> php artisan migrate:status

# Cek storage
docker exec -it <container-app> php artisan tinker --execute="echo config('filesystems.disks.vault.bucket');"

# Cek health
curl -I https://dropdeck.example.com/up

# Cek DNS
dig +short dropdeck.example.com

# Cek port
ss -tulpn | grep -E ':80|:443|:3000'
```

---

## 15. Checklist

### 15.1 Pre-deploy

- [ ] VM 4 vCPU / 8 GB / 80 GB, swap 4 GB.
- [ ] OS update, timezone, SSH key, firewall.
- [ ] DNS A record mengarah ke IP VM.
- [ ] Port 80/443 terbuka; 3000 dibatasi.
- [ ] Dokploy terpasang dan panel bisa diakses via tunnel.
- [x] `trustProxies` sudah dipatch.
- [ ] `SESSION_SECURE_COOKIE=true`.
- [x] `dokploy-network` ditambahkan.
- [x] Healthcheck worker dinonaktifkan.
- [x] RustFS tag dipin (`1.0.4`).
- [x] Build prod lokal sukses.
- [ ] Secret dibuat dan disimpan.
- [ ] Google OAuth redirect produksi terdaftar.
- [ ] SMTP produksi terverifikasi.
- [ ] Backup awal (jika migrasi dari server lama).

### 15.2 Deploy

- [ ] Environment diisi lengkap.
- [ ] Preview Compose tidak error.
- [ ] Domain `app:8000` + HTTPS.
- [ ] Deploy sukses.
- [ ] Migrasi selesai.
- [ ] `rustfs-init` exit `0`.
- [ ] `app` healthy; queue/scheduler running.
- [ ] `/up` 200.

### 15.3 Post-deploy

- [ ] Register + verifikasi email.
- [ ] Login email/password.
- [ ] Login Google.
- [ ] Buat papan/kolom/kartu.
- [ ] Upload file.
- [ ] Thumbnail.
- [ ] DropLink file.
- [ ] DropLink kartu.
- [ ] Password DropLink.
- [ ] Revoke DropLink.
- [ ] Download guest.
- [ ] Dashboard/storage meter.
- [ ] `failed_jobs` kosong.
- [ ] Log tidak ada error.
- [ ] Cookie/header aman.
- [ ] `/.env` 404.
- [ ] Backup manual berhasil.
- [ ] Restore diuji (minimal sebelum rilis).

### 15.4 Mingguan/bulanan

- [ ] Cek disk dan `docker system df`.
- [ ] Cek `failed_jobs`.
- [ ] Cek sertifikat TLS.
- [ ] Cek backup terbaru + `gzip -t`.
- [ ] Cek restore test.
- [ ] Update OS.
- [ ] Review `composer audit`/`npm audit`.
- [ ] Review log keamanan.

---

## 16. Referensi Perintah

### 16.1 Host/Docker

```bash
docker ps
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Image}}'
docker compose ls
docker logs --tail=200 <container>
docker logs -f <container>
docker exec -it <container> sh
docker stats --no-stream
docker system df
docker image prune
docker builder prune
```

### 16.2 Laravel/Artisan

```bash
php artisan about
php artisan migrate:status
php artisan optimize
php artisan queue:failed
php artisan queue:retry all
php artisan queue:restart
php artisan schedule:list
php artisan down
php artisan up
```

Jalankan di container `app`:

```bash
docker exec -it <container-app> php artisan about
```

### 16.3 Backup

```bash
sudo /usr/local/bin/dropdeck-backup-db.sh
sudo /usr/local/bin/dropdeck-backup-rustfs.sh
gzip -t /var/backups/dropdeck/*.sql.gz
```

### 16.4 Jaringan/TLS

```bash
dig +short dropdeck.example.com
curl -I https://dropdeck.example.com/up
ss -tulpn | grep -E ':80|:443|:3000'
echo | openssl s_client -connect dropdeck.example.com:443 -servername dropdeck.example.com 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates
```

---

## 17. Referensi

Dokploy:

- Manual installation — <https://docs.dokploy.com/docs/core/manual-installation>
- Docker Compose — <https://docs.dokploy.com/docs/core/docker-compose>
- Domains — <https://docs.dokploy.com/docs/core/docker-compose/domains>
- Backup API — <https://docs.dokploy.com/docs/api/backup>
- Installation — <https://docs.dokploy.com/docs/core/installation>

Laravel:

- Trusted proxies — <https://laravel.com/docs/13.x/requests#configuring-trusted-proxies>
- Session configuration — <https://laravel.com/docs/13.x/session#configuration>
- Mail — <https://laravel.com/docs/13.x/mail>
- Deployment — <https://laravel.com/docs/13.x/deployment>

Proxmox:

- Proxmox VE documentation — <https://pve.proxmox.com/pve-docs/>
- Proxmox Backup Server — <https://pbs.proxmox.com/docs/>

Internal:

- [`02-ARCHITECTURE.md`](02-ARCHITECTURE.md)
- [`04-RULES.md`](04-RULES.md)
- [`05-IMPLEMENTATION-PLAN.md`](05-IMPLEMENTATION-PLAN.md)
- [`../compose.prod.yaml`](../compose.prod.yaml)
- [`../infra/Containerfile`](../infra/Containerfile)
- [`../infra/docker-entrypoint.sh`](../infra/docker-entrypoint.sh)
- [`../infra/Caddyfile`](../infra/Caddyfile)
- [`../.env.example.snippet`](../.env.example.snippet)

---

## 18. Catatan Verifikasi

Item yang bergantung pada versi pihak ketiga dan harus dicek saat deployment:

- [ ] Perilaku `env_file: .env` pada versi Dokploy yang dipakai (Context7 mendukung, tetap uji Preview Compose).
- [ ] Format/versi template Dokploy (`dokploy/template.toml`) jika ingin memakai template.
- [ ] Tag RustFS yang tersedia dan sudah diuji.
- [ ] `imagick` tersedia untuk PHP `8.5.12`; jika tidak, gunakan GD dan `THUMBNAIL_PDF_ENABLED=false`.
- [ ] Dukungan `healthcheck: disable: true` pada Compose provider/Dokploy.
- [ ] Perilaku Cloudflare + Let's Encrypt sesuai konfigurasi DNS Anda.
- [ ] Path data Dokploy untuk backup konfigurasi.
- [ ] Ketersediaan `mariadb-dump` dan `aws-cli` di image yang dipakai.
- [ ] Dukungan service account/credential terbatas di RustFS.
- [x] Advisory Composer (Livewire `4.4.8`, PHPUnit `13.4.5`) sudah diperbaiki.
- [x] Advisory npm build tooling (`vite-plus 0.3.0`) dicatat sebagai risiko build-time; tidak masuk runtime image.
