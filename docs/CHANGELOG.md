# DropDeck — Changelog

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)

---

## [Unreleased]

### Changed
- `vite.config.js` — added `host: '0.0.0.0'`, `port: 5173`, HMR config for container access
- `compose.yaml` — added `security_opt: label:disable` for all PHP/Vite services (SELinux dev workaround)
- `config/services.php` — added Google OAuth config
- `app/Models/User.php` — added `google_id`, `avatar_url`, `storage_quota_bytes`, `storage_used_bytes` to `$fillable`/casts + `boards()`, `attachments()`, `dropLinks()` relations
- `phpunit.xml` — force test env values and use custom bootstrap (`tests/bootstrap.php`)

### Added
- Google OAuth routes: `auth/google/redirect`, `auth/google/callback`
- `app/Http/Controllers/Auth/GoogleAuthController.php` — handle Google login/registration
- Google login button on login & register pages
- `docs/05-IMPLEMENTATION-PLAN.md` — step-by-step implementation plan (M0–M8)
- `docs/CHANGELOG.md` — this file

### Added (M2 — Skema DB)
- 9 migrations: alter `users` (`password` nullable, `google_id`, `avatar_url`, storage bytes), `boards`, `board_columns`, `cards`, `labels` + `card_label`, `checklist_items`, `attachments`, `droplinks` (+ CHECK constraints), `droplink_accesses`
- Models: `Board`, `BoardColumn`, `Card`, `Label`, `ChecklistItem`, `Attachment`, `DropLink`, `DropLinkAccess` (with relations + enum casts)
- Policies: `BoardPolicy`, `CardPolicy`, `AttachmentPolicy`, `DropLinkPolicy` (owner-scoped)
- Factories: `BoardFactory`, `BoardColumnFactory`, `CardFactory`, `LabelFactory`, `ChecklistItemFactory`, `AttachmentFactory`, `DropLinkFactory` (with states)
- Enums: `DropLinkTarget`, `DropLinkStatus`, `AccessEvent`, `ThumbnailStatus`
- `database/seeders/DemoSeeder.php` — demo user + "Alur Konten" board (4 columns, 5 cards, labels, checklist, attachment, droplink)
- `tests/bootstrap.php` — forces isolated test env (SQLite in-memory) when running inside the container

### Added (M3 — Kanban)
- `app/Support/Position.php` — `GAP` (1024), `next()`, `between()` (null = perlu rebalance), `sequence()`
- 19 Action kelas: `Boards/{Create,Update,Archive,Restore,Delete}Board`, `Columns/{Create,Rename,Move,Delete}Column`, `Cards/{Create,Update,Move,Delete}Card`, `Labels/{Create,Attach,Detach}Label`, `Checklists/{Add,Toggle,Remove}Item`
- Form Object: `app/Livewire/Forms/{Board,Card,Column,Label,ChecklistItem}Form.php` (validasi + pesan Indonesia)
- `resources/views/pages/boards/⚡index.blade.php` — daftar board, filter arsip, modal create/edit/delete
- `resources/views/pages/boards/⚡show.blade.php` — kanban dengan `wire:sort` native Livewire 4 (kolom + kartu antar kolom), modal kartu (deskripsi, label, checklist), modal label manager, rename/delete kolom, delete kartu
- `lang/id/boards.php`, `lang/id/cards.php`, `lang/id/columns.php`; `APP_LOCALE=id`
- Route `boards.index` (`/boards`) dan `boards.show` (`/boards/{board}`) via `Route::livewire`, middleware `auth`+`verified`
- Tes: `tests/Unit/PositionTest.php`, `tests/Feature/Boards/{BoardTest,ColumnTest,CardTest}.php` (63 tes, 133 assertion)

### Added (M4 — Vault)
- `config/dropdeck.php` — seluruh batas bisnis (kuota, ukuran file, jumlah lampiran, dsb.) + pengaturan vault (ekstensi/MIME terlarang, thumbnail)
- `config/filesystems.php` — disk privat `vault` (S3/RustFS, `visibility: private`, path-style endpoint)
- `app/Support/Bytes.php` — konversi & format ukuran (basis 1024, desimal koma)
- `app/Support/FileGuard.php` — sanitasi nama, deteksi MIME dari konten, daftar ekstensi/MIME terlarang, MIME unduhan aman, `Content-Disposition` RFC 5987
- `app/Exceptions/QuotaExceededException.php`
- `app/Actions/Vault/StoreAttachment.php` — validasi ukuran/jumlah/ekstensi/MIME, reservasi kuota atomik, unggah ke `users/{uid}/cards/{cid}/{uuid}.{ext}`, checksum SHA-256, rollback objek bila transaksi gagal
- `app/Actions/Vault/DeleteAttachment.php` — hapus baris + kembalikan kuota, dispatch pembersihan objek setelah commit
- `app/Jobs/GenerateThumbnail.php` — WebP 480px via Intervention Image (Imagick/GD), batas piksel sumber, status Pending/Ready/Failed
- `app/Jobs/DeleteStoredObjects.php` — pembersihan objek idempoten
- `app/Http/Controllers/AttachmentController.php` — unduhan streamed + thumbnail inline, header `nosniff`, `private, no-store`, akses owner-only
- Route `attachments.download` (`GET /attachments/{attachment}/download`) dan `attachments.thumbnail` (`GET /attachments/{attachment}/thumbnail`)
- `app/Livewire/Vault/UploadZone.php` + `AttachmentList.php` beserta view — unggah multi-file dengan progres, daftar lampiran, konfirmasi hapus; terpasang di modal kartu
- `lang/id/attachments.php`; `config/livewire.php` dipublikasikan dengan batas unggah sementara mengikuti `DROPDECK_MAX_FILE_MB`
- Tes: `tests/Unit/BytesTest.php`, `tests/Feature/Vault/{FileGuard,AttachmentUpload,AttachmentDownload,AttachmentDelete,GenerateThumbnail,AttachmentUi}Test.php`

### Fixed
- Duplikasi `FILESYSTEM_DISK` di `.env` membuat disk default menjadi `vault` (S3); Livewire lalu menolak unggahan multi-file dengan error `S3DoesntSupportMultipleFileUploads`. Kini default tetap `local`, disk `vault` dipilih eksplisit lewat `config('dropdeck.vault.disk')`, dan `tests/bootstrap.php` memaksa `FILESYSTEM_DISK=local`
- Batas unggah sementara Livewire bawaan 12 MB menolak berkas 100 MB — `config/livewire.php` dipublikasikan dan `temporary_file_upload.rules` mengikuti batas bisnis
- Vite container crash-loop (`ENOTEMPTY` npm error) — fixed by cleaning node_modules volume and adding explicit host/port config
- SELinux permission denied on bind mounts — fixed with `security_opt: label:disable`
- Vite dev server not accessible from host — fixed with `host: '0.0.0.0'` and correct port
- `droplinks` CHECK constraints failed on SQLite (test DB) — now guarded to MySQL/MariaDB/PostgreSQL only
- `Card::column()` & `BoardColumn::cards()` menebak FK `board_column_id` — kini eksplisit `column_id`
- `ChecklistItem::is_done` bernilai `null` di memori setelah `create()` (default hanya di DB) — ditambah `$attributes = ['is_done' => false]`
- Halaman Livewire SFC gagal render 500 `MultipleRootElementsDetectedException` — layout `layouts::app` sudah otomatis, wrapper `<x-layouts::app>` dihapus
- Halaman Livewire (mis. `/settings/profile`) 500 `filemtime(): stat failed` — Blaze menanam path absolut `base_path()` di file kelas SFC yang penamaannya content-hash; cache views kini pakai named volume `views` per-runtime (app/queue/scheduler) di `compose.yaml` supaya host & container tidak berbagi
- Test suite ran against dev MariaDB and errored with 419 (container `env_file` overrode phpunit `<env>`) — fixed by forcing the test env in `tests/bootstrap.php`

---

## [0.1.0] — 2026-10-18

### Added
- Multi-stage `Containerfile` (base, dev, vendor, assets, prod)
- Podman compose stack: app (FrankenPHP), queue, scheduler, vite, mariadb, phpmyadmin, rustfs, mailpit
- Production compose template for Dokploy
- Google Cloud Console URLs documented for OAuth setup
- Healthcheck endpoint `/up`
- Vite dev server with HMR for container development
- SELinux workaround for Fedora/RHEL development

### Infrastructure
- MariaDB 11.4 with persistent volume
- RustFS S3-compatible object storage with auto-bucket creation
- Mailpit for development email capture
- FrankenPHP with Caddy (classic mode)
- PHP 8.5 with Imagick, Ghostscript for PDF thumbnails
