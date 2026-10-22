Instruksi build ulang dari nol
⚠️ Perintah down -v menghapus database, volume, dan file RustFS lama. Pastikan tidak ada data yang dibutuhkan.
1. Hapus container/volume/image lama
cd ~/Documents/dropdeck

podman compose down -v --remove-orphans

# sisa container
podman ps -a --format '{{.Names}}' | grep '^dropdeck' | xargs -r podman rm -f

# sisa volume (biasanya sudah dihapus oleh down -v)
podman volume ls --format '{{.Name}}' | grep '^dropdeck_' | xargs -r podman volume rm -f

# image dev lama
podman rmi -f localhost/dropdeck-dev:latest
2. Siapkan .env
Kalau ingin benar-benar baru:
cp .env.example .env
Opsional isi GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET. Jangan isi APP_KEY manual — dibuat di langkah 4.
Kalau .env lama mau dipertahankan (berisi APP_KEY + kredensial), lewati langkah ini dan langkah 4.
3. Build image tanpa cache
podman compose build --no-cache
4. Generate APP_KEY (sebelum up)
podman compose run --rm \
  -e WAIT_FOR_DB=false -e RUN_MIGRATIONS=false \
  app php artisan key:generate
5. Jalankan stack
podman compose up -d
podman compose ps
podman compose logs -f rustfs-init
Tunggu rustfs-init selesai dengan status exited (0) dan bucket siap. app, queue, scheduler harus Up.
6. Migrasi + data demo
podman compose exec app php artisan migrate:fresh --force
podman compose exec app php artisan db:seed --class=DemoSeeder --force
7. Verifikasi
Buka:
- App: http://localhost:8000
- Login demo: demo@dropdeck.com / password
- Mailpit: http://localhost:8025
- phpMyAdmin: http://localhost:8080
- RustFS console: http://localhost:9001
Cek dashboard menampilkan: 1 papan, 5 kartu, 1 berkas, 1 link aktif. Buka /links → tombol Buat DropLink harus berfungsi. Buka /boards → buka papan → menu ⋯ di header harus punya arsip/hapus.
8. Kalau aset/Vite bermasalah
podman compose logs -f vite
podman compose restart vite


Instruksi rebuild dari nol
cd /home/rakarestu/Documents/dropdeck

# 1. Hentikan & hapus container/volume lama
podman compose down -v --remove-orphans
podman rm -f $(podman ps -aq --filter name=dropdeck) 2>/dev/null || true
podman volume rm -f $(podman volume ls -q --filter name=dropdeck) 2>/dev/null || true
podman rmi -f localhost/dropdeck-app 2>/dev/null || true

# 2. Env bersih
cp .env.example .env

# 3. Build tanpa cache
podman compose build --no-cache

# 4. Generate key tanpa menunggu DB
podman compose run --rm \
  -e WAIT_FOR_DB=false -e RUN_MIGRATIONS=false \
  app php artisan key:generate

# 5. Nyalakan stack
podman compose up -d

# 6. Pastikan rustfs-init selesai exit 0
podman compose ps -a
podman compose logs rustfs-init

# 7. Schema + data demo
podman compose exec app php artisan migrate:fresh --force
podman compose exec app php artisan db:seed --class=DemoSeeder --force