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

### Fixed
- Vite container crash-loop (`ENOTEMPTY` npm error) — fixed by cleaning node_modules volume and adding explicit host/port config
- SELinux permission denied on bind mounts — fixed with `security_opt: label:disable`
- Vite dev server not accessible from host — fixed with `host: '0.0.0.0'` and correct port
- `droplinks` CHECK constraints failed on SQLite (test DB) — now guarded to MySQL/MariaDB/PostgreSQL only
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
