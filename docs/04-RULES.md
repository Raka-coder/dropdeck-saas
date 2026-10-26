# DropDeck — Rules (Aturan Pengembangan)

Dokumen ini adalah aturan wajib untuk kode, keamanan, data, container, dan proses kerja di DropDeck. Berlaku untuk developer maupun asisten AI yang membantu coding. Kata **WAJIB**, **JANGAN**, **SEBAIKNYA** mengikuti arti biasa.

Dokumen terkait: `01-PRD.md` (apa), `02-ARCHITECTURE.md` (bagaimana), `03-DATABASE-SCHEMA.md` (data).

---

## 1. Prinsip Umum

1. **Ikuti dokumen.** Jika kode bertentangan dengan PRD/Architecture/Schema, perbarui dokumen lebih dulu (atau tanyakan), jangan diam-diam menyimpang.
2. **Scope MVP.** JANGAN menambah fitur di luar PRD (plan Pro, teams, real-time, payment). Ide baru masuk ke daftar "Fase 2".
3. **Aman secara default.** Tolak dulu, izinkan eksplisit.
4. **Sederhana dulu.** Gunakan fitur bawaan Laravel/Livewire/Flux sebelum menambah package.
5. **Satu tanggung jawab per lapisan** (Livewire tipis → Action → Model/Storage).
6. **Semua perubahan teruji** untuk aturan inti (isolasi, kuota, DropLink).

## 2. Stack dan Versi

| Item | Aturan |
|:---|:---|
| PHP | 8.5.12 (minimum patch untuk php2shell). Semua file `declare(strict_types=1);` |
| Laravel | 13, struktur skeleton standar |
| UI | Livewire + Flux UI + Tailwind. JANGAN menambah framework JS (Vue/React) |
| Auth | Fortify (email) + Socialite (Google). JANGAN mengganti ke WorkOS atau menambah provider tanpa keputusan |
| DB | MariaDB, driver `mariadb`. JANGAN menulis SQL spesifik DB lain |
| Storage | Hanya lewat disk `vault`. JANGAN memakai `Storage::disk('s3')` langsung atau SDK AWS manual untuk operasi file |
| Queue/Cache/Session | Driver `database` |
| Dependensi | Tambah package hanya jika perlu; catat alasannya di PR/commit. Pin lewat `composer.lock`/`package-lock.json` |

## 3. Struktur dan Penamaan

| Hal | Aturan | Contoh |
|:---|:---|:---|
| Namespace | PSR-4 `App\` | `App\Actions\DropLinks\CreateDropLink` |
| Model | Singular PascalCase | `Card`, `DropLink` |
| Tabel | Plural snake_case | `board_columns`, `droplinks` |
| Action | Kata kerja + objek, satu method publik `handle()` | `MoveCard::handle()` |
| Policy | `{Model}Policy` | `CardPolicy` |
| Job | Kata kerja | `GenerateThumbnail` |
| Enum | Backed enum di `App\Enums` | `DropLinkStatus` |
| Livewire | Namespace sesuai domain | `Boards\Show` |
| Route name | `domain.aksi` | `boards.show`, `droplinks.public.show` |
| Config bisnis | Hanya `config/dropdeck.php`; JANGAN hardcode angka batas | `config('dropdeck.limits.max_file_mb')` |
| Teks UI | Lewat `lang/id/*.php` atau JSON, JANGAN hardcode di Blade/komponen | `__('boards.empty')` |
| Commit | Conventional Commits | `feat(droplink): add atomic download counter` |
| Branch | `main` (stabil), `feat/*`, `fix/*`, `chore/*` | `feat/kanban-dnd` |

## 4. Aturan Kode PHP/Laravel

1. **Livewire/Controller tipis:** validasi + otorisasi + panggil Action + render. Logika bisnis di Action/Service.
2. **Validasi:** Livewire Form Object atau Form Request. JANGAN memvalidasi manual di banyak tempat.
3. **Mass assignment:** definisikan `$fillable` eksplisit. JANGAN memakai `$guarded = []`. JANGAN `Model::create($request->all())`.
4. **Query:** gunakan Eloquent/Query Builder dengan binding. JANGAN menyusun SQL dari input user. `DB::raw` hanya untuk ekspresi statis.
5. **N+1:** aktifkan `Model::preventLazyLoading(! app()->isProduction())` dan eager load relasi. Tes halaman board memastikan jumlah query tetap.
6. **Transaksi:** wajib untuk operasi yang mengubah >1 tabel/baris yang saling bergantung (upload, hapus, pindah dengan rebalance, buat link).
7. **Efek samping setelah commit:** dispatch job penghapusan/thumbnail dengan `afterCommit`.
8. **Tipe:** type hint parameter dan return; gunakan enum untuk nilai tetap; hindari `mixed`.
9. **Tidak ada logika di Blade** selain presentasi sederhana.
10. **Error:** gunakan exception terarah (mis. `QuotaExceededException`) dan tampilkan pesan ramah di UI; jangan bocorkan stack trace.
11. **Waktu:** gunakan `now()` (Carbon) dan simpan UTC; konversi tampilan di UI.
12. **Konfigurasi:** jangan memanggil `env()` di luar file `config/*`. Gunakan `config()`.
13. **Format dan analisis:** `vendor/bin/pint` dan `vendor/bin/phpstan` (Larastan) HARUS bersih sebelum commit.

## 5. Otorisasi dan Isolasi Data (KRITIS)

1. Setiap model privat (`Board`, `Card`, `Attachment`, `DropLink`, dst.) **WAJIB** punya Policy dan dipanggil lewat `$this->authorize()` / `Gate`.
2. Semua query data privat **WAJIB** ter-scope ke user login (`$request->user()->boards()`, atau `where('user_id', ...)`). JANGAN `Board::find($id)` mentah dari input.
3. Route model binding untuk resource privat HARUS di-scope atau diverifikasi oleh Policy.
4. Resource milik user lain → respons **404**.
5. ID dari klien (Livewire property) **tidak dipercaya**: validasi ulang kepemilikan di Action setiap kali (properti Livewire dapat dimanipulasi). Gunakan `#[Locked]` untuk ID yang tidak boleh berubah.
6. Setiap endpoint privat baru **WAJIB** punya tes isolasi dua-user.
7. Operasi lintas tabel menjaga satu board/satu pemilik (kolom, label, kartu) sesuai Schema bagian 4.

## 6. Aturan Storage dan Upload

1. File hanya lewat disk `vault`, bucket privat. JANGAN membuat URL publik RustFS atau `temporaryUrl` untuk browser.
2. Object key dibuat server: `users/{user_id}/cards/{card_id}/{uuid}.{ext}`. JANGAN memakai nama asli file sebagai key.
3. Validasi upload (server): ukuran ≤ `max_file_mb`, jumlah ≤ `max_files_per_card`, ekstensi tidak ada di daftar terlarang, MIME terdeteksi dari konten, dan kuota cukup.
4. **Reservasi kuota atomik** (Schema 4.2) sebelum/bersamaan dengan simpan objek. Gagal simpan → rollback kuota.
5. Hapus apa pun yang berisi lampiran → kurangi kuota dalam transaksi + `DeleteStoredObjects` setelah commit (idempotent).
6. Unduh/pratinjau: `StreamedResponse` dari `readStream`, dengan header: `Content-Disposition: attachment; filename*=UTF-8''...`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`.
7. **JANGAN** menyajikan SVG/HTML/JS unggahan secara inline. Satu-satunya "pratinjau" adalah thumbnail WebP hasil render server.
8. Nama file asli disanitasi (hapus karakter kontrol, batasi panjang) sebelum disimpan dan sebelum masuk header.
9. Pemrosesan gambar/PDF hanya di job dengan batas dimensi, memori, dan timeout; PDF di-thumbnail hanya jika `THUMBNAIL_PDF_ENABLED=true`.
10. Kredensial storage produksi memakai service account terbatas pada bucket aplikasi, bukan root.

## 7. Aturan DropLink (KRITIS)

1. Token = `Str::random(40)`, UNIQUE. JANGAN memakai ID berurutan atau token pendek. JANGAN mencatat token ke log aplikasi.
2. Password/PIN di-hash (`Hash::make`); verifikasi dengan `Hash::check`. JANGAN menyimpan plaintext.
3. Kedaluwarsa **wajib** dan ≤ `link_max_days` (30). Batas unduhan 1–`link_max_downloads` atau NULL.
4. **Urutan validasi publik (selalu sama):** token ada? → dicabut? → kedaluwarsa? → habis? → butuh password dan belum unlock? → tampil/unduh.
5. Token tidak ditemukan → **404 generik**. Status mati (dicabut/kedaluwarsa/habis) → halaman status ramah dan log `blocked`.
6. **GET tidak pernah mengubah `download_count`.** Unduh hanya lewat **POST** (CSRF aktif) dan **mengulang** seluruh validasi.
7. Penghitungan unduhan hanya lewat satu query atomik bersyarat (Architecture 5.4). JANGAN membaca-lalu-menulis (`$link->download_count++`).
8. Link kartu: `attachment` yang diunduh HARUS milik kartu yang dibagikan.
9. Unlock password disimpan di session per link (`droplink_unlocked.{id}`), bukan cookie permanen.
10. Rate limit: password 5/menit per IP+token (lockout setelah 10 gagal), halaman 60/menit, unduh 20/menit per IP. Event `password_failed` dicatat.
11. Respons publik: `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer`, tanpa menampilkan nama/email owner.
12. Link yang dicabut atau mati tidak pernah hidup lagi.
13. Log akses mencatat: `view`, `password_ok`, `password_failed`, `download`, `blocked`.
14. Hapus file/kartu otomatis mematikan link terkait (cascade).

## 8. Aturan Autentikasi

1. Email/password lewat Fortify; JANGAN membuat sistem login kedua.
2. Google lewat Socialite. Terima login hanya jika email Google terverifikasi. Tautkan ke akun yang ada berdasarkan email; JANGAN membuat akun ganda untuk email yang sama.
3. Setelah login: regenerate session.
4. Hapus akun dan ubah email/password memakai konfirmasi password (`password.confirm`).
5. `google_id` UNIQUE; mencoba menautkan Google ID yang sudah dipakai akun lain ditolak.
6. Kredensial OAuth hanya dari environment.

## 9. Aturan Frontend (Livewire/Flux/Alpine)

1. Gunakan komponen Flux sebelum membuat komponen sendiri; Tailwind untuk layout. Dukung dark mode dan responsif.
2. `wire:key` unik pada setiap item list (kolom, kartu, label, checklist, lampiran).
3. Drag-drop memakai satu pustaka (Alpine Sort **atau** SortableJS), mengirim event ke Livewire yang memanggil Action `Move*`. Optimistic UI + rollback saat gagal.
4. JANGAN menyimpan data besar atau hasil query berat di properti publik Livewire (ikut ter-serialisasi ke klien). Pakai `#[Computed]` atau query di `render()`.
5. Properti ID sensitif: `#[Locked]`.
6. Operasi lama (upload) menampilkan progres dan status; hindari klik ganda dengan `wire:loading`/`disabled`.
7. Escape output (Blade `{{ }}`); `{!! !!}` dilarang untuk data user.
8. Aksesibilitas: label pada input, fokus terlihat, dapat dioperasikan keyboard, kontras cukup.
9. Teks UI Bahasa Indonesia, nada singkat dan jelas; pesan error menyebut penyebab dan langkah selanjutnya.

## 10. Aturan Database dan Migrasi

1. Perubahan skema **hanya lewat migrasi**. Migrasi yang sudah dideploy JANGAN diedit; buat migrasi baru.
2. Migrasi harus dapat dijalankan dari nol dan idempotent terhadap data dev; sertakan `down()` bila memungkinkan.
3. Semua FK dengan perilaku `ON DELETE` eksplisit sesuai Schema.
4. Indeks sesuai Schema bagian 5; tambahkan indeks hanya bila ada query nyata.
5. JANGAN `ENUM` DB; gunakan `varchar` + enum PHP.
6. Data uji lewat factory/seeder, bukan manual di DB. `DemoSeeder` hanya jalan di `local`.
7. Produksi: jalankan `migrate --force` oleh entrypoint service `app` saja; backup sebelum migrasi destruktif.

## 11. Aturan Container dan Environment

### 11.1 Umum
1. **Satu `Containerfile`**, multi-stage (`dev`, `prod`). Image produksi tidak berisi tool dev dan **berjalan sebagai user non-root**.
2. **Konfigurasi 100% lewat environment.** JANGAN meng-hardcode host/kredensial di image atau compose produksi.
3. `.env` JANGAN dicommit. Commit hanya `.env.example` yang berisi nama variabel tanpa nilai rahasia.
4. `APP_KEY` dibuat sekali dan disimpan sebagai secret; JANGAN diganti sembarangan (menginvalidasi sesi/data terenkripsi).
5. Image memakai tag versi eksplisit untuk dependensi infrastruktur (MariaDB, RustFS, dll) di produksi; hindari `latest` di produksi.
6. Satu image, tiga peran: `app`, `queue`, `scheduler`. Hanya `app` menjalankan migrasi.
7. Log ke stdout/stderr. Tidak menulis log ke file di container produksi.
8. Healthcheck pada `app` (`/up`), MariaDB, dan RustFS.

### 11.2 Podman (lokal)
1. Gunakan nama image penuh (`docker.io/...`).
2. Bind mount kode: `:Z` pada sistem SELinux. Pada Podman rootless, root container = user host sehingga kepemilikan file benar; `keep-id` hanya bila container berjalan non-root.
3. Port host ≥ 1024. MariaDB hanya di-bind ke `127.0.0.1`.
4. Perintah standar tercantum di `README.md`; jangan memakai perintah ad-hoc yang tidak terdokumentasi untuk setup.
5. phpMyAdmin dan Mailpit **hanya di compose dev**.

### 11.3 Dokploy (produksi)
1. Deploy sebagai Compose service memakai `compose.prod.yaml`; tanpa `ports:` ke host (gunakan `expose` + domain Dokploy).
2. Rahasia diisi di Environment Dokploy. JANGAN menaruh nilai rahasia di repositori atau template.
3. phpMyAdmin dan console RustFS **tidak diekspos publik**.
4. Volume bernama untuk MariaDB dan RustFS; backup sesuai Architecture 10.4 dan diuji pulihkan.
5. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, cookie secure, proxy dipercaya hanya untuk jaringan internal.
6. Template Dokploy mengikuti format resmi terbaru (verifikasi sebelum rilis).

## 12. Aturan Keamanan (ringkasan checklist)

- [ ] Policy + scoping pada setiap resource privat
- [ ] Tes isolasi dua-user untuk endpoint baru
- [ ] Bucket privat, tanpa URL storage ke browser
- [ ] Upload tervalidasi (ukuran, jumlah, ekstensi, MIME, kuota)
- [ ] Unduh dengan `attachment` + `nosniff`; tanpa render inline SVG/HTML
- [ ] Password link di-hash; rate limit aktif
- [ ] GET link publik tidak menghabiskan unduhan
- [ ] Penghitungan unduhan atomik
- [ ] Header keamanan + CSP (mulai Report-Only)
- [ ] `APP_DEBUG=false` di produksi; tidak ada stack trace publik
- [ ] Secrets hanya di environment
- [ ] Input yang ditampilkan di-escape; tidak ada `{!! !!}` untuk data user
- [ ] Tidak ada token/password di log
- [ ] Dependensi diaudit (`composer audit`, `npm audit`) berkala

## 13. Aturan Pengujian (Pest)

| Wajib diuji | Keterangan |
|:---|:---|
| Isolasi | User A vs B pada board, kartu, lampiran, link, unduh |
| Kuota | Tolak saat penuh; akurat setelah upload/hapus/hapus kartu/hapus board/hapus akun |
| DropLink | Aktif; kedaluwarsa; habis; dicabut; password benar/salah; throttle; GET tidak menghitung; POST menghitung; paralel pada `max_downloads` |
| Posisi | `Position::between`, rebalance, pindah antar kolom |
| Google | Akun baru, tautkan akun lama, email tidak terverifikasi ditolak (Socialite di-mock) |
| Hapus | Job `DeleteStoredObjects` terdispatch dengan key yang benar |
| Validasi | Ekstensi terlarang, file terlalu besar, terlalu banyak file |

Aturan: `Storage::fake('vault')` untuk tes; tes tidak bergantung pada RustFS atau internet; setiap bug diperbaiki dengan tes regresi.

## 14. Aturan Proses Kerja

1. **Definition of Done** (per fitur): sesuai PRD; Policy + tes isolasi; validasi + pesan UI Indonesia; tes lulus; Pint + Larastan bersih; dokumentasi/Schema diperbarui bila berubah; berjalan di `podman compose` dari nol.
2. Commit kecil dan bermakna (Conventional Commits). Satu perubahan logis per commit.
3. Urutan kerja mengikuti milestone PRD bagian 13 (M0 → M8).
4. Perubahan skema/arsitektur dicatat di dokumen terkait pada commit yang sama.
5. Sebelum rilis: jalankan checklist keamanan (bagian 12) dan uji pemulihan backup.

## 15. Aturan untuk Asisten AI (Coding Agent)

1. Baca `docs/01-04` sebelum menulis kode; patuhi PRD, Schema, dan Rules ini.
2. JANGAN menambah fitur/dependensi di luar scope tanpa persetujuan eksplisit.
3. JANGAN mengubah skema tanpa migrasi baru dan pembaruan `03-DATABASE-SCHEMA.md`.
4. JANGAN melewati Policy, validasi, atau transaksi demi "lebih singkat".
5. JANGAN membuat endpoint yang menyajikan file lewat URL storage langsung.
6. JANGAN memakai `$guarded = []`, `{!! !!}` untuk data user, atau `env()` di luar config.
7. Tulis tes untuk aturan inti yang disentuh; jalankan Pint dan Larastan.
8. Jika detail pihak ketiga ditandai **[VERIFIKASI]**, cek dokumentasi resmi versi terbaru dan laporkan hasilnya; jangan menebak.
9. Bila ada ambiguitas pada aturan bisnis, tanyakan; jangan berasumsi diam-diam.
10. Jelaskan perubahan secara ringkas (apa, kenapa, bagaimana diuji).
