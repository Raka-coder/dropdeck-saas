# DropDeck — Implementation Plan

| | |
|:---|:---|
| **Versi** | 1.0 |
| **Status** | Draft untuk implementasi |
| **Sumber** | PRD (01), Architecture (02), Schema (03), Rules (04) |
| **Prinsip** | Bertahap, setiap milestone selesai → tes → commit |

---

## Ringkasan Milestone

| # | Milestone | Deliverable | Status |
|:--|:---|:---|:---|
| M0 | Infrastruktur | Container, compose, RustFS, tes upload | Done |
| M1 | Auth | Fortify, Google login, profile settings | Done |
| M2 | Skema DB | Migrasi, model, policy, factory, seeder | Done |
| M3 | Kanban | Board, kolom, kartu, drag-drop, label, checklist | Pending |
| M4 | Vault | Upload, kuota, download, thumbnail, hapus | Pending |
| M5 | DropLink | Buat, publik, password, limit, revoke, log | Pending |
| M6 | Dashboard | Storage monitor, activity log, daftar link | Pending |
| M7 | Hardening | Tes penuh, rate limit, header, scheduler, cleanup | Pending |
| M8 | Deploy | Dokploy compose, domain, backup | Pending |

---

## M0 — Infrastruktur ✅

**Tujuan:** Lingkungan lokal jalan end-to-end.

- [x] `infra/Containerfile` — multi-stage (base, dev, vendor, assets, prod)
- [x] `infra/Caddyfile` — FrankenPHP mode klasik
- [x] `infra/php.ini` — upload limits, opcache
- [x] `infra/docker-entrypoint.sh` — wait DB, migrate, optimize
- [x] `compose.yaml` — 8 service (app, queue, scheduler, vite, mariadb, phpmyadmin, rustfs, mailpit)
- [x] `compose.prod.yaml` — untuk Dokploy
- [x] `.env.example.snippet` — variabel env
- [x] RustFS bucket auto-create via `rustfs-init`
- [x] `security_opt: label:disable` untuk SELinux (Fedora/RHEL)
- [x] Vite config — `host: 0.0.0.0`, `port: 5173`, HMR
- [x] Healthcheck `/up` di container app

**Verifikasi:** `podman compose up` → semua container Up → `curl localhost:8000` → 200.

---

## M1 — Autentikasi ✅

**Tujuan:** Login email + Google berfungsi.

- [x] Fortify (starter kit) — login, register, verifikasi email, reset password
- [x] `laravel/socialite` terinstall
- [x] `config/services.php` — config Google
- [x] `routes/web.php` — `auth/google/redirect`, `auth/google/callback`
- [x] `app/Http/Controllers/Auth/GoogleAuthController.php` — create/find user by email
- [x] Tombol Google di login & register views
- [x] `GOOGLE_*` env di `.env.example.snippet`
- [x] Email verification via Mailpit (lokal) / SMTP (prod)

**Verifikasi:** Login email → verified → dashboard. Login Google → akun auto-create/tautkan.

---

## M2 — Skema Database ✅

**Tujuan:** Migrasi, model, policy, factory sesuai Schema.

### Langkah:

1. **Migrasi `users` — tambah kolom:**
   ```bash
   php artisan make:migration alter_users_add_dropdeck_columns
   ```
   - `password` → nullable
   - `google_id` varchar(64) nullable unique
   - `avatar_url` varchar(512) nullable
   - `storage_quota_bytes` bigint unsigned default from config
   - `storage_used_bytes` bigint unsigned default 0

2. **Migrasi boards:**
   ```bash
   php artisan make:migration create_boards_table
   ```
   - `user_id` FK cascade, `title`, `description`, `color`, `archived_at`
   - Index: `(user_id, archived_at)`

3. **Migrasi board_columns:**
   ```bash
   php artisan make:migration create_board_columns_table
   ```
   - `board_id` FK cascade, `title`, `position`
   - Index: `(board_id, position)`

4. **Migrasi cards:**
   ```bash
   php artisan make:migration create_cards_table
   ```
   - `user_id`, `board_id`, `column_id` FK cascade
   - `title`, `description`, `position`
   - Index: `(column_id, position)`, `(board_id)`, `(user_id)`

5. **Migrasi labels + card_label:**
   ```bash
   php artisan make:migration create_labels_table
   php artisan make:migration create_card_label_table
   ```
   - labels: `board_id`, `name`, `color`, unique `(board_id, name)`
   - card_label: pivot PK `(card_id, label_id)`

6. **Migrasi checklist_items:**
   ```bash
   php artisan make:migration create_checklist_items_table
   ```
   - `card_id` FK cascade, `content`, `is_done`, `position`

7. **Migrasi attachments:**
   ```bash
   php artisan make:migration create_attachments_table
   ```
   - `user_id`, `card_id` FK cascade
   - `disk`, `path` (unique), `original_name`, `extension`, `mime_type`
   - `size_bytes`, `checksum_sha256`
   - `thumbnail_path`, `thumbnail_status`

8. **Migrasi droplinks:**
   ```bash
   php artisan make:migration create_droplinks_table
   ```
   - `user_id`, `target_type`, `attachment_id` nullable, `card_id` nullable
   - `token` char(40) unique, `label`, `password_hash`
   - `expires_at`, `max_downloads` nullable, `download_count`
   - `last_accessed_at`, `revoked_at`
   - CHECK constraints untuk target_type

9. **Migrasi droplink_accesses:**
   ```bash
   php artisan make:migration create_droplink_accesses_table
   ```
   - `droplink_id` FK cascade, `attachment_id` nullable set null
   - `event`, `blocked_reason`, `ip_address`, `user_agent`

10. **Model + Policy + Factory:**

    | Model | Policy | Factory |
    |:---|:---|:---|
    | `Board` | `BoardPolicy` | `BoardFactory` |
    | `BoardColumn` | — (via Board) | `BoardColumnFactory` |
    | `Card` | `CardPolicy` | `CardFactory` |
    | `Label` | — (via Board) | `LabelFactory` |
    | `ChecklistItem` | — (via Card) | `ChecklistItemFactory` |
    | `Attachment` | `AttachmentPolicy` | `AttachmentFactory` |
    | `DropLink` | `DropLinkPolicy` | `DropLinkFactory` |

11. **Enum:**
    - `DropLinkTarget`: File, Card
    - `DropLinkStatus`: Active, Expired, Exhausted, Revoked
    - `AccessEvent`: View, PasswordOk, PasswordFailed, Download, Blocked
    - `ThumbnailStatus`: None, Pending, Ready, Failed

12. **Seeder:**
    - `DemoSeeder` — 1 user demo, 1 board "Alur Konten", 4 kolom, beberapa kartu

**Verifikasi:** `migrate` sukses → `tinker` bisa create semua model → factory jalan.

---

## M3 — Kanban

**Tujuan:** Board, kolom, kartu fungsional dengan drag-drop.

### Langkah:

1. **Board:**
   - `app/Actions/Boards/CreateBoard.php` — handle(User $user, string $title, ?string $description, ?string $color)
   - `app/Actions/Boards/UpdateBoard.php`
   - `app/Actions/Boards/ArchiveBoard.php` / `RestoreBoard.php`
   - `app/Actions/Boards/DeleteBoard.php` — cascade hapus kolom+kartu+lampiran+link
   - `app/Livewire/Boards/Index.php` — daftar board
   - `app/Livewire/Boards/CreateModal.php`
   - Route: `GET /boards`, `POST /boards`, `DELETE /boards/{board}`

2. **Column:**
   - `app/Actions/Columns/CreateColumn.php`
   - `app/Actions/Columns/RenameColumn.php`
   - `app/Actions/Columns/MoveColumn.php` — posisi
   - `app/Actions/Columns/DeleteColumn.php`
   - `app/Livewire/Boards/Column.php`

3. **Card:**
   - `app/Actions/Cards/CreateCard.php`
   - `app/Actions/Cards/UpdateCard.php`
   - `app/Actions/Cards/MoveCard.php` — `Position::between`, rebalance
   - `app/Actions/Cards/DeleteCard.php`
   - `app/Livewire/Boards/Card.php`
   - `app/Livewire/Boards/CardModal.php`
   - `app/Support/Position.php` — helper `between()`, `next()`

4. **Drag-and-drop:**
   - Install `@alpinejs/sort` (atau SortableJS)
   - `x-sort` pada kolom, `x-sort:group` antar kolom
   - Event → `MoveCard`/`MoveColumn` Action
   - Optimistic UI + rollback on failure

5. **Label:**
   - `app/Actions/Labels/CreateLabel.php` / `AttachLabel.php` / `DetachLabel.php`
   - `app/Livewire/Boards/LabelManager.php`

6. **Checklist:**
   - `app/Actions/Checklists/AddItem.php` / `ToggleItem.php` / `RemoveItem.php`
   - Progress indicator di kartu

7. **Views:**
   - `resources/views/pages/boards/index.blade.php`
   - `resources/views/pages/boards/show.blade.php`
   - `resources/views/livewire/boards/column.blade.php`
   - `resources/views/livewire/boards/card.blade.php`
   - `resources/views/livewire/boards/card-modal.blade.php`

8. **Lang:**
   - `lang/id/boards.php`, `lang/id/cards.php`, `lang/id/columns.php`

**Verifikasi:** Pest test — CRUD, move card antar kolom, posisi ter-simpan, isolasi user.

---

## M4 — Vault (Attachment)

**Tujuan:** Upload, kuota, download, thumbnail, hapus.

### Langkah:

1. **Config:**
   - `config/filesystems.php` — disk `vault` (driver s3, private, path_style)
   - `config/dropdeck.php` — semua batas bisnis (quota, max_file_mb, dll)

2. **Upload Action:**
   - `app/Actions/Vault/StoreAttachment.php`
   - Validasi: ukuran, jumlah per kartu, ekstensi terlarang, MIME detection
   - `ReserveQuota` — query atomik bersyarat
   - Object key: `users/{uid}/cards/{cid}/{uuid}.{ext}`
   - Transaksi: reserve → put → insert → commit
   - After commit: dispatch `GenerateThumbnail`

3. **Download/Preview:**
   - `app/Http/Controllers/AttachmentController.php`
   - `GET /attachments/{attachment}/download` — StreamedResponse
   - `GET /attachments/{attachment}/thumbnail` — WebP
   - Header: `attachment`, `nosniff`, `private no-store`

4. **Delete:**
   - `app/Actions/Vault/DeleteAttachment.php`
   - Transaksi: hapus baris + kurangi `storage_used` → dispatch `DeleteStoredObjects` after commit

5. **Thumbnail:**
   - `app/Jobs/GenerateThumbnail.php` — WebP via Intervention Image
   - Queue: `php artisan queue:work`

6. **Livewire:**
   - `app/Livewire/Vault/UploadZone.php` — drag-drop upload
   - `app/Livewire/Vault/AttachmentList.php`

7. **Views:**
   - Upload zone di CardModal
   - Attachment list dengan thumbnail

**Verifikasi:** Pest — quota enforcement, extension validation, MIME detection, cascade delete.

---

## M5 — DropLink

**Tujuan:** Berbagi file/kartu dengan kontrol ketat.

### Langkah:

1. **Create DropLink:**
   - `app/Actions/DropLinks/CreateDropLink.php`
   - Validasi: kepemilikan target, batas link aktif, rentang nilai
   - Token: `Str::random(40)`, password: `Hash::make`
   - `app/Livewire/Links/CreateModal.php`
   - Route: `POST /links`

2. **Public Page:**
   - `app/Http/Controllers/PublicDropLinkController.php`
   - `GET /d/{token}` — halaman publik (noindex)
   - `POST /d/{token}/download` — unduh (validasi ulang)
   - `POST /d/{token}/download/{attachment}` — unduh dari link kartu
   - Validasi urutan: revoked → expired → exhausted → password → OK
   - Rate limit: password 5/menit, halaman 60/menit, unduh 20/menit
   - Session unlock: `droplink_unlocked.{id}`

3. **Atomic Download:**
   ```sql
   UPDATE droplinks SET download_count = download_count + 1, last_accessed_at = NOW()
   WHERE id = :id AND revoked_at IS NULL AND expires_at > NOW()
   AND (max_downloads IS NULL OR download_count < max_downloads);
   ```
   - Affected rows = 1 → stream file + log download
   - Affected rows = 0 → link mati

4. **Access Log:**
   - `app/Models/DropLinkAccess.php` — event, IP, user agent
   - Log: `view`, `password_ok`, `password_failed`, `download`, `blocked`

5. **Revoke:**
   - `app/Actions/DropLinks/RevokeDropLink.php`
   - `app/Livewire/Links/Index.php` — daftar link + revoke

6. **Views:**
   - `resources/views/pages/public/droplink.blade.php`
   - `resources/views/pages/links/index.blade.php`

7. **Lang:**
   - `lang/id/droplinks.php`

**Verifikasi:** Pest — semua cabang validasi, atomic download, concurrent request, password throttle.

---

## M6 — Dashboard

**Tujuan:** Storage monitor, activity log, ringkasan.

### Langkah:

1. **Storage Meter:**
   - `app/Livewire/Dashboard/StorageMeter.php`
   - Progress bar: `storage_used_bytes / storage_quota_bytes`
   - Warning 80% dan 100%

2. **Activity Log:**
   - `app/Livewire/Dashboard/ActivityLog.php`
   - Tabel `droplink_accesses` join `droplinks`
   - Filter by event, tanggal

3. **Summary:**
   - `app/Livewire/Dashboard/Overview.php`
   - Count: board, kartu, file, link aktif

4. **Views:**
   - `resources/views/pages/dashboard.blade.php`

**Verifikasi:** Dashboard render data benar.

---

## M7 — Hardening

**Tujuan:** Tes penuh, rate limit, header, scheduler, cleanup.

### Langkah:

1. **Rate Limiting** — `AppServiceProvider`:
   ```php
   RateLimiter::for('droplink-password', fn() => Limit::perMinute(5));
   RateLimiter::for('droplink-page', fn() => Limit::perMinute(60));
   RateLimiter::for('droplink-download', fn() => Limit::perMinute(20));
   RateLimiter::for('upload', fn() => Limit::perMinute(30));
   RateLimiter::for('droplink-create', fn() => Limit::perMinute(30));
   ```

2. **Security Headers** — `app/Http/Middleware/SecurityHeaders.php`:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `Referrer-Policy`
   - CSP Report-Only

3. **Scheduler** — `routes/console.php`:
   ```php
   $schedule->job(new PruneExpiredDropLinks)->daily();
   $schedule->job(new PruneAccessLogs)->daily();
   $schedule->job(new ReconcileStorageUsage)->daily();
   $schedule->command('queue:prune-failed')->weekly();
   ```

4. **Jobs:**
   - `PruneExpiredDropLinks` — hapus link mati > retensi
   - `PruneAccessLogs` — hapus log > 90 hari
   - `ReconcileStorageUsage` — koreksi storage_used
   - `DeleteStoredObjects` — idempotent, tries=5

5. **Tes:**
   - Unit: `Position`, `Bytes`, `FileGuard`, enum logic
   - Feature: Isolasi, kuota, DropLink semua cabang, Google mock
   - Konkurensi: Parallel download test
   - Storage: `Storage::fake('vault')`

6. **Pint + Larastan:**
   ```bash
   vendor/bin/pint --dirty
   vendor/bin/phpstan analyse
   ```

**Verifikasi:** `pest` semua hijau → `pint` clean → `phpstan` level max clean.

---

## M8 — Deploy

**Tujuan:** Produksi di Dokploy.

### Langkah:

1. **Build image prod:**
   ```bash
   podman build -f infra/Containerfile --target prod -t dropdeck-prod .
   ```

2. **Dokploy:**
   - Create Service → Compose → `compose.prod.yaml`
   - Environment: semua `*_` variabel
   - Domain → service `app` → port 8000 → HTTPS

3. **Post-deploy:**
   - `migrate --force` otomatis via entrypoint
   - Cek RustFS bucket + service account
   - Test end-to-end: register, upload, buat link, unduh guest

4. **Backup:**
   - MariaDB dump harian
   - RustFS sync harian/minggu
   - Test restore minimal sekali

5. **Observability:**
   - `LOG_CHANNEL=stderr`
   - `/up` healthcheck
   - Monitor `failed_jobs`

**Verifikasi:** Production HTTPS jalan, semua flow end-to-end sukses.

---

## Urutan Kerja per Milestone

```
M0 → M1 → M2 → M3 → M4 → M5 → M6 → M7 → M8
```

Setiap milestone:
1. Implement sesuai plan
2. Tes lokal (`pest`, manual)
3. Pint + Larastan clean
4. Update Schema/Rules jika ada perubahan
5. Commit dengan Conventional Commits
6. Update CHANGELOG.md

## Definition of Done (per Fitur)

- [ ] Sesuai PRD
- [ ] Policy + tes isolasi dua-user
- [ ] Validasi + pesan UI Bahasa Indonesia
- [ ] Tes lulus
- [ ] Pint + Larastan bersih
- [ ] Dokumen diperbarui jika berubah
- [ ] Berjalan di `podman compose` dari nol
