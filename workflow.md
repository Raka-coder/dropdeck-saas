# DropDeck — Workflow & Application Flow (Lengkap)

Dokumentasi detail workflow pengguna, alur aplikasi, dan komponen halaman dashboard untuk agent evaluasi dan build fitur. Referensi untuk: PRD, Architecture, Schema, Rules, dan build prompt.

---

## 📑 Table of Contents

1. [User Workflow End-to-End](#1-user-workflow-end-to-end)
2. [Page Sitemap & Navigation](#2-page-sitemap--navigation)
3. [Feature Flow Details](#3-feature-flow-details)
4. [Dashboard Page Complete](#4-dashboard-page-complete)
5. [Data State Management](#5-data-state-management)
6. [Component Interaction Diagram](#6-component-interaction-diagram)
7. [User Account & Settings](#7-user-account--settings)
8. [Public Access Flow (Guest)](#8-public-access-flow-guest)
9. [Error & Edge Case Handling](#9-error--edge-case-handling)
10. [Performance & Caching Strategy](#10-performance--caching-strategy)

---

## 1. User Workflow End-to-End

### 1.1 Complete User Journey (Owner)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         DROPDECK USER JOURNEY                               │
└─────────────────────────────────────────────────────────────────────────────┘

PHASE 1: ONBOARDING
├─ [1] User buka http://localhost:8000
│  └─ → Landing page / redirect /dashboard (jika sudah login)
│
├─ [2] Pilih: Email login atau Google login
│  ├─ Email: form daftar (name, email, password)
│  │  └─ Verifikasi email via link (Fortify)
│  └─ Google: OAuth flow, pilih akun Google → redirect callback → buat akun
│
└─ [3] Masuk ke /dashboard
   └─ → Halaman kosong, tampil welcome + button "Board baru"

PHASE 2: SETUP (Hari pertama)
├─ [1] Klik tombol "Board baru"
│  └─ → Modal create board
│     ├─ Input: judul, deskripsi (opsional), warna (opsional)
│     └─ Submit → board dibuat + redirect ke /boards/{board}
│
├─ [2] Halaman board (Kanban kosong)
│  └─ Layout:
│     ├─ Header: judul board, tombol [...] (edit, delete, arsip)
│     ├─ 3 kolom default dibuat otomatis: "To Do", "In Progress", "Done"
│     │  (atau user custom via modal)
│     └─ Kanban area kosong (tidak ada kartu)
│
├─ [3] Klik "Buat kartu" pada kolom "To Do"
│  └─ → Modal create card
│     ├─ Input: judul, deskripsi
│     ├─ Optional: label (warna), daftar checklist
│     └─ Submit → kartu muncul di kolom
│
└─ [4] Ulangi hingga ada 3-5 kartu

PHASE 3: FILE SHARING (Hari ke-2)
├─ [1] Klik kartu "Desain Logo"
│  └─ → Panel detail (modal atau sidebar)
│     ├─ Edit: judul, deskripsi, label, checklist
│     ├─ Tab "File": kosong
│     └─ Button "Upload file"
│
├─ [2] Upload 3 file (JPG, PDF)
│  ├─ File 1 (5 MB JPG): upload → progress bar → sukses
│  ├─ File 2 (10 MB PNG): upload → progress bar → sukses
│  └─ File 3 (20 MB PDF): upload → progress bar → sukses
│     └─ Dashboard storage meter naik: 0 → 35 MB (0.7% dari 2GB)
│
├─ [3] Download file milikmu untuk verifikasi
│  └─ Klik file → download via /attachments/{id}/download
│
└─ [4] View thumbnail file (jika ada)
   └─ Klik file → preview WebP thumbnail

PHASE 4: PUBLIC SHARING (Hari ke-3)
├─ [1] Klik kartu "Desain Logo"
│  └─ → Tab "File" → file PDF ada
│
├─ [2] Klik menu file → "Bagikan via DropLink"
│  └─ → Modal create DropLink
│     ├─ Kedaluwarsa: select "24 jam"
│     ├─ Batas unduhan: input "3"
│     ├─ Password: checkbox aktif, input "1234"
│     └─ Submit → link dibuat
│
├─ [3] Tampil link publik
│  └─ Copy link: "https://localhost:8000/d/a1b2c3d4e5f..."
│
├─ [4] Kirim link ke klien via WhatsApp
│  └─ Klien buka link → halaman publik
│
└─ [5] Monitor akses di /links
   └─ Lihat: "Klien membuka link 14:32"
      └─ "Unduhan 1/3 terpakai"

PHASE 5: MANAGEMENT
├─ [1] Lihat aktivitas di /dashboard
│  └─ Activity log: "Klien membuka Desain Logo (password salah)"
│
├─ [2] Cabut link (revoke)
│  └─ /links → cari link → klik "Cabut"
│     └─ Link tidak bisa diakses lagi (404)
│
├─ [3] Edit settings akun
│  └─ /settings/profile → ubah nama, email, password
│
└─ [4] Logout
   └─ Klik user menu → logout → redirect /login
```

---

## 2. Page Sitemap & Navigation

### 2.1 Complete Page Structure

```
DropDeck Sitemap
├─ (PUBLIC)
│  ├─ / (landing page)
│  ├─ /login (email login)
│  ├─ /register (email daftar)
│  ├─ /forgot-password (reset via email)
│  ├─ /auth/google/redirect (Google OAuth start)
│  ├─ /auth/google/callback (Google OAuth complete, internal)
│  └─ /d/{token} (PUBLIC: halaman DropLink publik)
│     ├─ GET: tampil file, form password, tombol unduh
│     └─ POST: verifikasi password, unduh file
│
├─ (AUTHENTICATED - Logged In)
│  ├─ /dashboard
│  │  ├─ Metric cards (board, kartu, file, link aktif)
│  │  ├─ Storage meter + warning 80%
│  │  ├─ Activity log (recent 10)
│  │  ├─ Button "Board baru"
│  │  └─ Link ke "/boards"
│  │
│  ├─ /boards (list boards)
│  │  ├─ Grid board cards
│  │  ├─ Button create board
│  │  ├─ Search / filter
│  │  └─ Untuk setiap board: edit, delete, arsip, open
│  │
│  ├─ /boards/{board} (Kanban board detail)
│  │  ├─ Header: judul, tombol [...] (edit, delete, arsip)
│  │  ├─ Kolom bar (create kolom button)
│  │  ├─ Layout Kanban dengan drag-drop
│  │  ├─ Untuk setiap kolom:
│  │  │  └─ Kartu (create button, drag-drop area)
│  │  │     ├─ Untuk setiap kartu:
│  │  │     │  ├─ Title, label badge, checklist progress
│  │  │     │  └─ Click → panel detail / modal
│  │  │     │     ├─ Edit title, desc, labels, checklist
│  │  │     │     ├─ Tab "File"
│  │  │     │     │  ├─ Daftar attachment
│  │  │     │     │  ├─ Download button per file
│  │  │     │     │  ├─ Hapus button per file
│  │  │     │     │  └─ Upload file button
│  │  │     │     ├─ Tab "Activity" (opsional: log perubahan kartu)
│  │  │     │     └─ Delete card button
│  │  │     └─ Delete kolom button
│  │  │
│  │  └─ Label manager (edit board labels)
│  │
│  ├─ /links (DropLink management)
│  │  ├─ Table: kolom link, expires, sisa unduhan, status, actions
│  │  ├─ Button "Buat DropLink baru"
│  │  ├─ Untuk setiap link:
│  │  │  ├─ Copy link button
│  │  │  ├─ Edit (perpanjang, ubah batas) — P2
│  │  │  └─ Revoke button
│  │  ├─ Filter: event (view, download, password_failed, blocked)
│  │  ├─ Date range filter (opsional P2)
│  │  └─ "Lihat semua aktivitas" → /links/activity
│  │
│  ├─ /links/activity (activity log lengkap) — P1
│  │  ├─ Table: DropLink, event, waktu, IP, user agent
│  │  ├─ Filter: event type, tanggal, status
│  │  └─ Export CSV (opsional P2)
│  │
│  ├─ /settings/profile
│  │  ├─ Edit: nama, email, avatar (upload opsional)
│  │  ├─ Change password section
│  │  └─ Delete account button (dengan konfirmasi password)
│  │
│  ├─ /settings/password
│  │  ├─ Form: current password, new password, confirm
│  │  └─ Submit → change
│  │
│  ├─ /settings/two-factor (dari Fortify starter kit)
│  │  └─ Setup 2FA (optional)
│  │
│  ├─ /settings/appearance (dari Flux UI)
│  │  └─ Toggle dark mode
│  │
│  └─ /logout (POST)
│     └─ Invalidate session → redirect /login
│
└─ (ADMIN / INTERNAL) — tidak untuk MVP
   └─ phpMyAdmin @ :8080 (dev saja)
```

### 2.2 Navigation Sidebar (Authenticated)

```
┌──────────────────────────┐
│ DropDeck                 │  ← Logo / home link
├──────────────────────────┤
│ Platform                 │  ← Section header
├──────────────────────────┤
│ 🏠 Dashboard             │  ← /dashboard (primary)
│ 🔗 DropLink              │  ← /links (primary)
│ 📋 Boards                │  ← /boards (secondary, optional)
├──────────────────────────┤
│ Settings                 │  ← Section header
├──────────────────────────┤
│ ⚙️  Pengaturan            │  ← /settings/profile
│ 🌙 Appearance            │  ← dark mode toggle
│ 🔐 2FA                   │  ← /settings/two-factor
├──────────────────────────┤
│ User: Raka               │  ← User profile
│ [Avatar] Logout          │  ← /logout
└──────────────────────────┘
```

---

## 3. Feature Flow Details

### 3.1 Kanban Board Feature Flow

```
CREATE BOARD
─────────────
User @ /boards → Click "Board baru"
  ↓
Modal dialog
  Input: title, description (opt), color (opt)
  ↓
POST /boards (create board)
  Action: CreateBoard::handle()
    ✓ Create board
    ✓ Create 3 default columns: To Do, In Progress, Done
    ✓ Return board + redirect
  ↓
Redirect /boards/{board}
  ↓
Kanban page loads (Livewire/Boards/Show)

DRAG-DROP CARD
──────────────
User drags card "Task A" dari "To Do" ke "In Progress"
  ↓
Alpine JS event: @sort:updated
  ↓
Livewire method: moveCard($cardId, $toColumnId, $newPosition)
  ↓
Action: MoveCard::handle()
  ✓ Verify ownership via Policy
  ✓ Calculate new position (Position::between)
  ✓ If gap < 2, rebalance column
  ✓ Update card.column_id + position
  ↓
UI update optimistic (instant)
  ↓
If error: toast error + revert UI

CREATE CARD
───────────
User @ Kanban → Click "Buat kartu" di kolom "To Do"
  ↓
Modal dialog
  Input: title, description, labels (multiselect), checklist items
  ↓
POST /cards (create)
  Action: CreateCard::handle()
    ✓ Verify column ownership
    ✓ Calculate position (gap 1024)
    ✓ Create card
    ✓ Attach labels (many-to-many)
    ✓ Create checklist items
  ↓
Card muncul di kolom (Livewire refresh)

EDIT CARD
─────────
User click kartu "Task A"
  ↓
Modal detail card (CardModal.php)
  ├─ Tab "Detail": title, description, labels, checklist
  ├─ Tab "File": attachment list
  └─ Tab "Activity": history (opsional P2)
  ↓
Edit field → wire:model → Save
  ↓
Action: UpdateCard::handle() [buat baru atau pakai UpdateCard jika ada]
  ↓
Update successful → toast "Kartu diperbarui"

DELETE CARD
───────────
User @ card detail → Click "Hapus kartu"
  ↓
Confirm dialog: "Hapus kartu & semua lampirannya?"
  ↓
POST /cards/{card}/delete
  ↓
Action: DeleteCard::handle()
  ✓ Get attachment paths
  ✓ Delete card (cascade: checklist, labels)
  ✓ Reduce storage_used_bytes
  ✓ Commit DB
  ✓ afterCommit: dispatch DeleteStoredObjects($paths)
  ↓
Card hilang dari Kanban, storage meter berkurang
```

### 3.2 Upload & Storage Feature Flow

```
UPLOAD FILE
───────────
User @ card detail → Tab "File" → Click "Upload file"
  ↓
File input / drag-drop zone
  ↓
Select file: "design-v2.pdf" (25 MB)
  ↓
Validation (browser + server):
  ✓ Size: 25 MB < 100 MB (limit) ✓
  ✓ Ext: .pdf not in [php, exe, sh, ...] ✓
  ✓ Already 5 files in card (limit 20) ✓
  ✓ Storage used: 50 MB, total available 2000 MB ✓
  ↓
Livewire WithFileUploads: tmp file created
  ↓
Progress bar: 0% → 100%
  ↓
POST attachment (store)
  ↓
Action: StoreAttachment::handle($card, $file)
  
  BEGIN TRANSACTION:
    1. Reserve kuota atomik (UPDATE users SET storage_used_bytes ... WHERE id AND space available)
       → If rows = 0: throw QuotaExceededException
    2. Upload to RustFS: PUT users/{user_id}/cards/{card_id}/{uuid}.pdf
       → If fail: ROLLBACK
    3. Insert attachment row:
       - path: users/1/cards/5/{uuid}.pdf
       - original_name: design-v2.pdf
       - size_bytes: 26214400 (25 MB)
       - mime_type: application/pdf
       - thumbnail_status: pending
    4. COMMIT transaction
  END TRANSACTION
  ↓
  afterCommit: dispatch GenerateThumbnail job
  ↓
UI: file muncul di list, toast "File berhasil di-upload"
Dashboard storage meter naik: 50 MB → 75 MB

DOWNLOAD FILE (OWNER)
─────────────────────
User @ card → Tab "File" → file "design-v2.pdf"
  ↓
Click file atau icon "download"
  ↓
GET /attachments/{attachment}/download
  ↓
Controller: AttachmentController::download()
  ✓ Verify ownership via Policy
  ✓ Get file from disk('vault')
  ✓ Stream: StreamedResponse(readStream(), headers: attachment, nosniff)
  ↓
Browser download file "design-v2.pdf"

PREVIEW THUMBNAIL (OWNER)
──────────────────────────
User @ card → Tab "File" → file gambar
  ↓
Click file → small thumbnail rendered inline
  ↓
GET /attachments/{attachment}/thumbnail
  ↓
Controller: AttachmentController::thumbnail()
  ✓ Check thumbnail_status
  ✓ If status = ready: stream WebP thumbnail (cache 7 hari)
  ✓ If status = pending: return placeholder + trigger job
  ✓ If status = failed: return X icon
  ↓
Thumbnail displayed in modal

DELETE FILE
───────────
User @ card detail → Tab "File" → file list
  ↓
Click delete icon on file
  ↓
POST /attachments/{attachment}/delete
  ↓
Action: DeleteAttachment::handle()
  
  BEGIN TRANSACTION:
    1. Get attachment.size_bytes
    2. Delete attachment row
    3. UPDATE users SET storage_used_bytes = storage_used_bytes - size (safe: GREATEST())
    4. COMMIT
  END TRANSACTION
  ↓
  afterCommit: dispatch DeleteStoredObjects([path, thumbnail_path])
  ↓
UI: file hilang dari list, storage meter berkurang

GENERATE THUMBNAIL (BG JOB)
──────────────────────────
Job triggered saat upload gambar/PDF
  ↓
Job: GenerateThumbnail::handle($attachment)
  ✓ Check if image (MIME: image/jpeg, image/png, image/webp)
  ✓ OR if PDF (MIME: application/pdf) dan THUMBNAIL_PDF_ENABLED
  ✓ Read original dari RustFS
  ✓ Resize / convert to WebP
  ✓ Upload thumbnail ke path: users/{id}/cards/{card}/thumbs/{uuid}.webp
  ✓ Update attachment.thumbnail_path + thumbnail_status = 'ready'
  ✓ If error: thumbnail_status = 'failed'
```

### 3.3 DropLink Feature Flow

```
CREATE DROPLINK
───────────────
User @ /links → Click "Buat DropLink baru"
  ↓
Modal create
  Pilihan 1: Link dari file
    └─ Dropdown: select file dari list lampirannya
       └─ (load lazily dari card)
  Pilihan 2: Link dari kartu
    └─ Dropdown: select card dari list boards
       └─ (load lazily semua cards)
  ↓
  Form fields:
    - Kedaluwarsa: select dropdown (1h, 6h, 24h, 7d, 30d)
    - Batas unduhan: input number, nullable (null = unlimited)
    - Password: toggle + input (optional)
  ↓
Submit → POST /links
  ↓
Action: CreateDropLink::handle($target, $expires_at, $max_downloads, $password)
  ✓ Verify ownership of target (file atau card)
  ✓ Check batas link aktif per user (default 100)
  ✓ Generate token: Str::random(40)
  ✓ Hash password: Hash::make($password)
  ✓ Create droplink row
  ✓ Return token
  ↓
UI: Modal close, tampil link:
  "https://localhost:8000/d/a1b2c3d4e5f6..."
  dengan tombol "Copy link" + "Done"
  ↓
User copy & kirim link ke klien

PUBLIC ACCESS (GUEST - NO AUTH)
───────────────────────────────
Guest buka link: https://localhost:8000/d/a1b2c3d4e5f6
  ↓
GET /d/{token}
  ↓
Controller: PublicDropLinkController::show($token)
  ✓ Find droplink by token
  ✓ Check status:
    - Not found → 404 (generic)
    - revoked_at IS NOT NULL → page: "Link telah dicabut"
    - expires_at < now → page: "Link telah kedaluwarsa"
    - download_count >= max_downloads → page: "Batas unduhan tercapai"
    - active → tampil halaman
  ✓ If password protected & session tidak unlock:
    └─ Tampil password form + "Masukkan password"
  ✓ Else:
    └─ Tampil file list (jika file) atau daftar file kartu (jika card)
  ✓ Log event: view
  ↓
Halaman publik rendered:
  ├─ File name only (no owner email)
  ├─ If file: 1 item "design.pdf" + tombol unduh
  ├─ If card: daftar file dalam kartu + tombol unduh per file
  └─ Footer: "DropDeck © 2024" (no owner info)

PASSWORD UNLOCK
───────────────
Guest @ public page → lihat form password
  ↓
Input password → POST /d/{token}/verify-password
  ↓
Controller: verifyPassword($token, $password)
  ✓ Check rate limit: 5 fails/menit, lockout 10 menit
  ✓ Hash::check($password, $link->password_hash)
  ✓ If correct:
    └─ Set session: session(['droplink_unlocked.' . $token => true])
    └─ Log event: password_ok
    └─ Return JSON {success: true}
  ✓ If wrong:
    └─ Log event: password_failed
    └─ Increment fail counter (redis atau DB)
    └─ Return JSON {success: false, message: "Password salah"}
  ↓
If success: page refresh/reload → file list muncul

DOWNLOAD FILE (GUEST)
─────────────────────
Guest @ public page → click "Unduh" button
  ↓
POST /d/{token}/download [atau /d/{token}/download/{attachment}]
  ↓
Controller: download($token, $attachment = null)
  ✓ Validate token (ulang semua check: revoked, expired, exhausted, password)
  ✓ If card link & $attachment diberikan:
    └─ Verify attachment belong to card
  ✓ RegisterDownload::handle() ← ATOMIK!
    
    UPDATE droplinks
       SET download_count = download_count + 1,
           last_accessed_at = NOW()
     WHERE id = :id
       AND revoked_at IS NULL
       AND expires_at > NOW()
       AND (max_downloads IS NULL OR download_count < max_downloads);
    
    If affected_rows = 1:
      └─ Stream file
      └─ Log event: download
    Else:
      └─ Return 403: "Batas unduhan tercapai / Link mati"
  ↓
  Header: Content-Disposition: attachment, X-Content-Type-Options: nosniff
  ↓
Browser download file

REVOKE LINK
───────────
User @ /links → lihat link aktif → click revoke icon
  ↓
POST /links/{link}/revoke
  ↓
Action: RevokeDropLink::handle()
  ✓ Verify ownership
  ✓ Update droplink.revoked_at = now()
  ✓ COMMIT
  ↓
UI: link status jadi "Dicabut", unduh button disabled
Guest mencoba akses: → halaman "Link telah dicabut"
```

---

## 4. Dashboard Page Complete

### 4.1 Dashboard Components & Data

```
╔════════════════════════════════════════════════════════════════════╗
║                          DASHBOARD PAGE                            ║
╠════════════════════════════════════════════════════════════════════╣
║                                                                    ║
║  Header Row:                                                       ║
║  ┌─────────────────────────────┐  ┌─────────────────────────────┐ ║
║  │ Dashboard                   │  │ [⚙️  Settings] [+ Board baru]  │ ║
║  │ Selamat pagi, Raka 👋       │  │                             │ ║
║  └─────────────────────────────┘  └─────────────────────────────┘ ║
║                                                                    ║
║  Alert (jika storage > 80%):                                       ║
║  ┌─────────────────────────────────────────────────────────────┐  ║
║  │ ⚠️  Kuota penyimpanan 85% penuh.                             │  ║
║  │    Hapus atau arsipkan file lama untuk membuat ruang.       │  ║
║  └─────────────────────────────────────────────────────────────┘  ║
║                                                                    ║
║  2-Column Layout:                                                  ║
║  ┌──────────────────────────┬──────────────────────────────────┐  ║
║  │   STORAGE (kiri)         │     RINGKASAN (kanan)            │  ║
║  ├──────────────────────────┼──────────────────────────────────┤  ║
║  │                          │                                  │  ║
║  │ Penyimpanan              │  4 Metric Cards (grid 4):        │  ║
║  │ ┌─────────────────────┐  │  ┌────┬────┬────┬────┐          │  ║
║  │ │ Pemakaian           │  │  │ B  │ K  │ F  │ L  │          │  ║
║  │ │ 1.7 GB / 2 GB       │  │  │ 5  │ 42 │127 │ 8  │          │  ║
║  │ │                     │  │  │ BAD│ KAR│FILE│LINK│          │  ║
║  │ │ [████████░░░░░░░░░░] │  │  │AKT│TOT│TRS│AKT │          │  ║
║  │ │ 85%                 │  │  └────┴────┴────┴────┘          │  ║
║  │ │                     │  │  Colors:                         │  ║
║  │ │ Sisa: 300 MB        │  │  - Board: board icon             │  ║
║  │ │                     │  │  - Kartu: card icon              │  ║
║  │ └─────────────────────┘  │  - File: attachment icon         │  ║
║  │                          │  - Link: link icon               │  ║
║  │ Status: ⚠️  Warning 80%+   │                                 │  ║
║  │                          │                                  │  ║
║  └──────────────────────────┴──────────────────────────────────┘  ║
║                                                                    ║
║  Full Width: AKTIVITAS TERBARU                                     ║
║  ┌────────────────────────────────────────────────────────────┐   ║
║  │ Activity Log  [🔗 Lihat semua]                              │   ║
║  ├────────────────────────────────────────────────────────────┤   ║
║  │ 1. Raka membuka "Desain Landing Page"                      │   ║
║  │    Hari ini, 14:32 • 2 file diunduh       [✓ Sukses]      │   ║
║  │                                                            │   ║
║  │ 2. Klien membuka "Proposal Q4"                             │   ║
║  │    Kemarin, 09:15 • 1 file diunduh        [✓ Sukses]      │   ║
║  │                                                            │   ║
║  │ 3. Percobaan akses "Invoice_v3" (password salah)          │   ║
║  │    2 hari lalu, 16:48 • IP: 116.xx.xx.xx  [✗ Ditolak]    │   ║
║  │                                                            │   ║
║  │ 4. DropLink "Portfolio 2024" telah kedaluwarsa           │   ║
║  │    3 hari lalu, 23:59 • Otomatis dihapus  [✗ Kedaluwa...]│   ║
║  │                                                            │   ║
║  └────────────────────────────────────────────────────────────┘   ║
║                                                                    ║
║  Footer:                                                           ║
║  DropDeck © 2024 · Status: ● Semua sistem normal                   ║
║  [Dokumentasi] · [Hubungi dukungan]                               ║
║                                                                    ║
╚════════════════════════════════════════════════════════════════════╝
```

### 4.2 Dashboard Data Sources & Queries

| Component | Data | Query / Source | Refresh |
|:---|:---|:---|:---|
| **Greeting** | "Selamat pagi, {name}" | `auth()->user()->name` | Page load |
| **Storage Meter** | used_bytes, quota_bytes, percent | `auth()->user()->storage_used_bytes`, `storage_quota_bytes` | Page load |
| **Storage Warning** | Show alert jika >= 80% | `$percent >= 80` | Conditional |
| **Metric: Board** | COUNT | `auth()->user()->boards()->count()` | Page load |
| **Metric: Kartu** | COUNT | `auth()->user()->cards()->count()` | Page load |
| **Metric: File** | COUNT | `auth()->user()->attachments()->count()` | Page load |
| **Metric: Link Aktif** | COUNT | `auth()->user()->droplinks()->where('revoked_at', null)->where('expires_at', '>', now())->count()` | Page load |
| **Activity Log** | 10 recent events | `auth()->user()->droplinks()->with(['attachment', 'card'])->orderBy('created_at', 'desc')->limit(10)->get()` | Page load |
| **Activity Event Type** | view, download, password_failed, blocked | Mapped dari DropLink status | Display |
| **Activity Status Badge** | Green (sukses) / Red (blocked) | `$link->revoked_at ? 'blocked' : ($link->expires_at < now() ? 'blocked' : 'success')` | Display |

### 4.3 Dashboard Livewire Component Code

```php
// app/Livewire/Dashboard/Overview.php
namespace App\Livewire\Dashboard;

use Livewire\Component;

class Overview extends Component
{
    public function render()
    {
        $user = auth()->user();
        
        // Metric queries
        $boards_count = $user->boards()->count();
        $cards_count = $user->cards()->count();
        $attachments_count = $user->attachments()->count();
        $links_active = $user->droplinks()
            ->where('revoked_at', null)
            ->where('expires_at', '>', now())
            ->count();
        
        // Storage
        $storage_used = $user->storage_used_bytes;
        $storage_quota = $user->storage_quota_bytes;
        $storage_percent = intval(($storage_used / $storage_quota) * 100);
        $storage_warning = $storage_percent >= 80;
        $storage_remaining = $storage_quota - $storage_used;
        
        // Activity log
        $activities = $user->droplinks()
            ->with(['attachment:id,original_name', 'card:id,title'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($link) {
                return [
                    'link' => $link,
                    'target_name' => $link->target_type === 'file' 
                        ? $link->attachment?->original_name 
                        : $link->card?->title,
                    'status' => $this->getLinkStatus($link),
                    'status_color' => $link->revoked_at ? 'blocked' : 
                                     ($link->expires_at < now() ? 'blocked' : 'success'),
                ];
            });
        
        return view('livewire.dashboard.overview', [
            'boards_count' => $boards_count,
            'cards_count' => $cards_count,
            'attachments_count' => $attachments_count,
            'links_active' => $links_active,
            'storage_used' => $storage_used,
            'storage_quota' => $storage_quota,
            'storage_percent' => $storage_percent,
            'storage_warning' => $storage_warning,
            'storage_remaining' => $storage_remaining,
            'activities' => $activities,
        ]);
    }
    
    private function getLinkStatus($link)
    {
        if ($link->revoked_at) return 'Dicabut';
        if ($link->expires_at < now()) return 'Kedaluwarsa';
        if ($link->max_downloads && $link->download_count >= $link->max_downloads) 
            return 'Habis';
        return 'Aktif';
    }
}
```

### 4.4 Dashboard Blade Template

```blade
{{-- resources/views/livewire/dashboard/overview.blade.php --}}
<div class="container">
  <div class="header">
    <div class="header-left">
      <h1>Dashboard</h1>
      <p>Selamat pagi, {{ auth()->user()->name }} 👋</p>
    </div>
    <div class="header-right">
      <button><i class="ti ti-settings"></i> Pengaturan</button>
      <button class="primary"><i class="ti ti-plus"></i> Board baru</button>
    </div>
  </div>

  @if($storage_warning)
    <div class="alert">
      <i class="ti ti-alert-triangle"></i>
      <div class="alert-content">
        <strong>Kuota penyimpanan {{ $storage_percent }}% penuh.</strong> 
        Hapus atau arsipkan file lama untuk membuat ruang.
      </div>
    </div>
  @endif

  <div class="grid-2">
    {{-- Storage Section --}}
    <div>
      <h2>Penyimpanan</h2>
      <div class="card">
        <div class="storage-meter">
          <div class="storage-meter-label">
            <span>Pemakaian</span>
            <span>{{ number_format($storage_used / 1024 / 1024, 1) }} GB / 
                  {{ number_format($storage_quota / 1024 / 1024, 1) }} GB</span>
          </div>
          <div class="storage-meter-bar">
            <div class="storage-meter-fill @if($storage_warning) warning @endif" 
                 style="width: {{ $storage_percent }}%;"></div>
          </div>
        </div>
        <p style="font-size: 13px; color: var(--text-secondary);">
          Sisa: <strong>{{ number_format($storage_remaining / 1024 / 1024, 1) }} MB</strong>
        </p>
      </div>
    </div>

    {{-- Metrics Section --}}
    <div>
      <h2>Ringkasan</h2>
      <div class="grid-4">
        <div class="card metric">
          <div class="metric-label">Board</div>
          <div class="metric-value">{{ $boards_count }}</div>
          <div class="metric-subtext">aktif</div>
        </div>
        <div class="card metric">
          <div class="metric-label">Kartu</div>
          <div class="metric-value">{{ $cards_count }}</div>
          <div class="metric-subtext">total</div>
        </div>
        <div class="card metric">
          <div class="metric-label">File</div>
          <div class="metric-value">{{ $attachments_count }}</div>
          <div class="metric-subtext">tersimpan</div>
        </div>
        <div class="card metric">
          <div class="metric-label">DropLink</div>
          <div class="metric-value">{{ $links_active }}</div>
          <div class="metric-subtext">aktif</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Activity Log --}}
  <div>
    <div class="activity-header">
      <div class="section-title">
        <i class="ti ti-history"></i>
        <h2 style="margin: 0;">Aktivitas Terbaru</h2>
      </div>
      <a href="/links/activity"><i class="ti ti-arrow-right"></i> Lihat semua</a>
    </div>
    <div class="card">
      @if($activities->count() > 0)
        <div class="activity-list">
          @foreach($activities as $item)
            <div class="activity-item">
              <div class="activity-item-left">
                <div class="activity-item-title">
                  {{ ucfirst($item['link']->target_type === 'file' ? 'Akses' : 'Akses') }}
                  <strong>{{ $item['target_name'] }}</strong>
                </div>
                <div class="activity-item-meta">
                  {{ $item['link']->created_at->diffForHumans() }} • 
                  {{ $item['link']->download_count }} {{ $item['link']->max_downloads ? '/' . $item['link']->max_downloads : '' }} unduhan
                </div>
              </div>
              <div class="activity-item-status status-{{ $item['status_color'] }}">
                {{ $item['status'] }}
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="empty">
          <p>Belum ada aktivitas.</p>
        </div>
      @endif
    </div>
  </div>

  <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border); 
              font-size: 13px; color: var(--text-muted); display: flex; justify-content: space-between;">
    <div>DropDeck © 2024 · Status: <span style="color: var(--text-success)">●</span> Semua sistem normal</div>
    <div><a href="#">Dokumentasi</a> · <a href="#">Hubungi dukungan</a></div>
  </div>
</div>
```

---

## 5. Data State Management

### 5.1 State Diagram

```
┌─────────────────────────────────────────────────────────────┐
│              DropDeck STATE MANAGEMENT FLOW                 │
└─────────────────────────────────────────────────────────────┘

USER AUTHENTICATION STATE
──────────────────────────
┌──────────┐
│  Logout  │  ← Unauthenticated
│  State   │
└────┬─────┘
     │ (Register OR Login successful)
     ↓
┌──────────────────────────┐
│  Authenticated State     │
│  - user_id              │
│  - user.name            │
│  - user.email           │
│  - storage_quota_bytes  │
│  - storage_used_bytes   │
│  - google_id (nullable) │
└────┬─────────────────────┘
     │
     ├─→ /dashboard (default redirect)
     ├─→ /boards (list)
     ├─→ /boards/{board} (Kanban)
     ├─→ /links (DropLink management)
     └─→ /settings/* (profile, password)


BOARD STATE (per board)
────────────────────────
┌─────────────────────────────┐
│  Board Instance             │
│  - id, user_id, title       │
│  - archived_at (nullable)   │
│  - columns: [] (eager load) │
│  - cards: [] (eager load)   │
│  - labels: [] (eager load)  │
└────┬────────────────────────┘
     │
     ├─ COLUMN STATE
     │  ├─ id, board_id, title, position
     │  └─ cards: [] (nested)
     │
     ├─ CARD STATE
     │  ├─ id, user_id, column_id, title, position
     │  ├─ labels: [] (many-to-many)
     │  ├─ checklist_items: []
     │  ├─ attachments: [] (via Card)
     │  └─ droplinks: [] (via Attachment OR via Card)
     │
     └─ LABEL STATE
        └─ id, board_id, name, color


ATTACHMENT STATE
─────────────────
┌──────────────────────────────────┐
│  Attachment Instance             │
│  - id, user_id, card_id          │
│  - path (unique, S3 key)         │
│  - original_name, size_bytes     │
│  - mime_type, thumbnail_status   │
│  - thumbnail_path (nullable)     │
└────┬─────────────────────────────┘
     │
     ├─ (Linked) Droplinks: [] (attachment_id)
     └─ (Storage) RustFS: /users/{user_id}/cards/{card_id}/{uuid}.{ext}


DROPLINK STATE
───────────────
┌────────────────────────────────────┐
│  DropLink Instance                 │
│  - id, user_id                     │
│  - target_type: file | card        │
│  - attachment_id (nullable)        │
│  - card_id (nullable)              │
│  - token (UNIQUE)                  │
│  - expires_at, max_downloads       │
│  - download_count, last_accessed_at│
│  - revoked_at (nullable)           │
│  - password_hash (nullable)        │
│  - created_at, updated_at          │
└────┬───────────────────────────────┘
     │
     ├─ Status: active | expired | exhausted | revoked
     ├─ Access Log: DropLinkAccess[] (many)
     │  └─ event: view | password_ok | password_failed | download | blocked
     │  └─ created_at, ip_address, user_agent
     └─ Public Page: /d/{token}
        ├─ GET: show file (if unlocked OR no password)
        └─ POST: download (atomik increment counter)


STORAGE QUOTA MANAGEMENT
─────────────────────────
Initial:
  user.storage_quota_bytes = 2 GB (config('dropdeck.quota_mb') * 1024 * 1024)
  user.storage_used_bytes = 0

On Upload:
  BEGIN TX:
    1. Reserve: UPDATE users SET storage_used_bytes += file_size WHERE user_id AND space_available
       → If rows=0: throw QuotaExceededException
    2. INSERT attachment with size_bytes
    3. COMMIT
  END TX

On Delete:
  BEGIN TX:
    1. DELETE attachment
    2. UPDATE users SET storage_used_bytes = GREATEST(storage_used_bytes - size_bytes, 0)
    3. COMMIT
  END TX
  → Async: DeleteStoredObjects job

On ReconcileStorageUsage (daily job):
  → Compare SUM(attachments.size_bytes) per user vs user.storage_used_bytes
  → Fix discrepancies
  → Log orphaned objects


SESSION STATE (Livewire / Browser)
──────────────────────────────────
Auth Session:
  - auth()->user() → User model + cached
  - session(['auth.password_confirmed_at'])

DropLink Unlock:
  - session(['droplink_unlocked.{link_id}' => true])
  → Checked di Public page untuk skip password form

Livewire State (per-component):
  - properties (public / #[Locked])
  - computed properties (live query)
```

### 5.2 Data Flow Diagram (Upload File)

```
User @ Browser (UI)
    ↓
    └─→ Select file → WithFileUploads (tmp storage)
        ↓
        └─→ POST /attachments/store
            ↓
            └─→ App/Livewire/Cards/AttachmentUpload
                ├─ Validate: size, ext, MIME, quota
                ├─ Call: StoreAttachment action
                │   ├─ BEGIN TX
                │   ├─ Reserve quota atomically
                │   ├─ Upload to RustFS (users/{id}/cards/{card}/{uuid}.ext)
                │   ├─ Insert attachment row
                │   ├─ COMMIT TX
                │   └─ afterCommit: dispatch GenerateThumbnail
                ├─ Update UI: file appears in list
                ├─ Update storage_used_bytes in UI (live)
                └─ Toast success
```

---

## 6. Component Interaction Diagram

```
Page Hierarchy
──────────────

/dashboard (Livewire Component: Dashboard/Overview)
    ├─ Header (greeting, buttons)
    ├─ Alert (storage warning)
    ├─ Metrics (4 cards)
    ├─ Storage Meter
    └─ Activity Log
        └─ Table (recent 10 activities)

/boards (Livewire Component: Boards/Index)
    ├─ Header (title, create button)
    └─ Grid: Board Cards (clickable → /boards/{board})
        └─ Each board card: title, 1 label, actions (edit, delete, arsip)

/boards/{board} (Livewire Component: Boards/Show)
    ├─ Header (board title, actions)
    ├─ Label Manager button
    ├─ Create Column button
    └─ Kanban Layout
        ├─ Column (Livewire/Boards/Column) × N
        │   ├─ Column title, create card button
        │   └─ Sortable area (SortableJS / Alpine Sort)
        │       ├─ Card (Livewire/Boards/Card) × N
        │       │   ├─ Title, labels, checklist progress
        │       │   └─ @click → CardModal (detail / edit)
        │       │       ├─ Detail tab (title, desc, labels, checklist)
        │       │       ├─ File tab
        │       │       │   ├─ AttachmentUpload component
        │       │       │   ├─ File list (download, delete per file)
        │       │       │   └─ Upload file button
        │       │       └─ Delete card button
        │       └─ Delete column button

/links (Livewire Component: Links/Index)
    ├─ Header (title, create button)
    ├─ CreateModal (Livewire/Links/CreateModal)
    │   ├─ Select target (file OR card)
    │   ├─ Kedaluwarsa select
    │   ├─ Max downloads input
    │   └─ Password toggle + input
    └─ Table: DropLinks
        ├─ Per row: name, expires, remaining, status, actions
        ├─ Copy link button
        ├─ Edit button (P2)
        └─ Revoke button

/settings/profile (Livewire Component: Settings/Profile)
    ├─ Edit name
    ├─ Edit email
    ├─ Edit avatar (upload, optional)
    └─ Delete account (confirm)

/d/{token} (Public Page - NO AUTH)
    ├─ If revoked / expired / exhausted:
    │   └─ Status page (generic, no owner info)
    └─ Else:
        ├─ File name(s)
        ├─ If password protected & not unlocked:
        │   └─ Password form
        └─ Else:
            ├─ File list with download buttons
            └─ OR File item (if single file)
```

---

## 7. User Account & Settings

### 7.1 /settings/profile Flow

```
User @ /settings/profile (GET)
  ↓
Render form dengan current data:
  - Name: input text (value: auth()->user()->name)
  - Email: input email (value: auth()->user()->email)
    └─ Disabled jika belum verifikasi (Fortify: email_verified_at)
  - Avatar: preview (jika ada), upload button, delete button (P2)
  - Change password link → /settings/password
  - Two-factor setup link → /settings/two-factor (Fortify)
  - Delete account button (modal konfirmasi)
  ↓
User edit name → wire:model → autosave OR submit button
  ↓
POST /settings/profile (update)
  ↓
Fortify UpdateProfileInformation atau custom Action
  ✓ Update user.name
  ✓ If email berubah: set email_verified_at = null, kirim verifikasi email
  ↓
Toast success / error

DELETE ACCOUNT
──────────────
User @ /settings/profile → click "Hapus akun"
  ↓
Modal: "Yakin hapus? Ini permanent."
  Input: password confirmation
  ↓
POST /settings/profile/delete
  ↓
Action: DeleteUserAccount::handle()
  ✓ Verify password
  ✓ Get all attachments path untuk user ini
  ✓ Delete all rows yang terkait user (cascade)
  ✓ COMMIT
  ✓ afterCommit: dispatch DeleteStoredObjects(all user paths)
  ↓
Logout + redirect /login dengan message "Akun dihapus"
```

### 7.2 Settings Pages Summary

| Page | URL | Features |
|:---|:---|:---|
| **Profile** | `/settings/profile` | Edit nama, email, avatar; delete akun |
| **Password** | `/settings/password` | Change password form (current → new → confirm) |
| **2FA** | `/settings/two-factor` | Setup Google Authenticator / recovery codes |
| **Appearance** | `/settings/appearance` | Toggle dark mode (Flux UI) |
| **Sessions** (P2) | `/settings/sessions` | List logged-in devices, logout dari device lain |

---

## 8. Public Access Flow (Guest)

### 8.1 Guest Download Scenario

```
Scenario: Klien menerima link PDF dari Raka

1. KLIEN buka link di browser
   URL: https://localhost:8000/d/a1b2c3d4e5f6g7h8...
   ↓
2. Browser GET /d/{token}
   ↓
3. PublicDropLinkController::show($token)
   ├─ Find droplink by token
   ├─ Check status (active / expired / exhausted / revoked)
   ├─ Check password protection
   ├─ If password protected & not unlocked:
   │   └─ Render: password form
   ├─ Else:
   │   └─ Render: file name + download button
   ├─ Log event: view (only for initial GET)
   ↓
4. KLIEN lihat halaman: "Desain Logo.pdf" + tombol unduh
   └─ Halaman statis (tidak punya info pemilik / email)
   ↓
5. KLIEN klik tombol unduh
   ↓
6. Browser POST /d/{token}/download
   ↓
7. PublicDropLinkController::download($token)
   ├─ Verify ulang token (expired? exhausted? revoked?)
   ├─ RegisterDownload::handle() ← ATOMIK!
   │   ├─ UPDATE droplinks SET download_count = download_count + 1 WHERE ...
   │   ├─ If affected_rows = 1: continue
   │   ├─ If affected_rows = 0: return 403 (limit reached)
   ├─ Stream file from RustFS
   ├─ Header: Content-Disposition=attachment, nosniff
   ├─ Log event: download
   ↓
8. Browser download file "Desain Logo.pdf"
   ↓
9. KLIEN dapat file di komputer

Back to RAKA (owner):
  @ /links → lihat row untuk link tersebut
  ├─ Status: Aktif
  ├─ Sisa unduhan: 2/3
  ├─ Last accessed: hari ini, 14:32
  ↓
  @ /dashboard → activity log
  ├─ Event: "Klien membuka Desain Logo"
  ├─ Status: ✓ Sukses
  ├─ Time: hari ini, 14:32
```

---

## 9. Error & Edge Case Handling

### 9.1 Error Scenarios

| Scenario | Trigger | Handling | User Message |
|:---|:---|:---|:---|
| **Quota penuh** | Upload saat storage >= quota | Abort upload, rollback reserve | "Penyimpanan penuh. Hapus file lama." |
| **File terlalu besar** | Upload > 100 MB | Client + server validation | "File max 100 MB" |
| **Link kedaluwarsa** | GET /d/{token} saat expires_at < now() | Render expired page | "Link telah kedaluwarsa" |
| **Link habis** | POST download saat download_count >= max | Return 403 | "Batas unduhan tercapai" |
| **Link dicabut** | GET /d/{token} saat revoked_at NOT NULL | Render revoked page | "Link telah dicabut" |
| **Password salah** | POST /verify-password N kali | Rate limit (5/menit), lockout 10 menit | "Password salah (attempt 5/5)" |
| **Isolasi user** | User A akses board user B | Policy deny → 404 | 404 Not Found |
| **Storage mismatch** | SUM(attachments) != storage_used | ReconcileStorageUsage job fix | (Logged, auto-fixed) |

### 9.2 Edge Cases

```
1. DELETE CARD while attachment linked to DropLink
   ├─ Action: DeleteCard
   ├─ Cascade: delete attachments (FK)
   ├─ Action: DeleteAttachment (per attachment)
   ├─ Cascade: delete DropLink references (FK)
   ├─ Cascade: delete DropLinkAccess (FK)
   └─ Result: DropLink mati, guest akses → 404 (revoked_at IS NULL, attachment deleted)

2. DRAG CARD dari Column A (pos: 512) ke Column B (pos: 1024, ada 1 kartu)
   ├─ Kartu di A pergi → pos gaps antara 512 dan next
   ├─ Kartu ke B: position dihitung antara last item (1024) dan next
   ├─ Jika gap < 2: rebalance Column B → 1024, 2048, 3072, ...
   └─ Result: kartu muncul di B, posisi tepat

3. UPLOAD file saat quota at 90% (1800/2000 MB)
   ├─ File 50 MB → 1850/2000 (+warning!)
   ├─ File 150 MB → rejected (1850 + 150 > 2000)
   └─ User harus delete sebelum bisa upload lagi

4. REVOKE LINK kemudian unduh dalam 10 ms (race)
   ├─ Request 1: revoke → UPDATE droplinks SET revoked_at = NOW()
   ├─ Request 2: download → check revoked_at IS NULL (fail)
   └─ Result: download tidak jadi, guest akses → "Link dicabut"

5. CONCURRENT DOWNLOADS (paralel 5 request pada link max_downloads=3)
   ├─ Request 1: RegisterDownload (count 0→1) ✓
   ├─ Request 2: RegisterDownload (count 1→2) ✓
   ├─ Request 3: RegisterDownload (count 2→3) ✓
   ├─ Request 4: RegisterDownload (count 3 >= 3, no change) ✗
   ├─ Request 5: RegisterDownload (count 3 >= 3, no change) ✗
   └─ Result: exactly 3 succeed (atomik UPDATE dengan WHERE condition)

6. DELETE ATTACHMENT while DropLink public page LOADING
   ├─ Step 1: User @ public page, file daftar ter-render
   ├─ Step 2: Owner hapus file → attachment deleted
   ├─ Step 3: Guest klik download → attachment not found → 404
   └─ Acceptable (owner dapat cabut kapan saja)
```

---

## 10. Performance & Caching Strategy

### 10.1 Query Optimization

```
EAGER LOAD PATTERN
───────────────────
Kanban page: /boards/{board}
  Bad:
    $board = Board::find($id);
    foreach ($board->columns as $col) {     // N+1 (cols)
      foreach ($col->cards as $card) {      // N+1 (cards per col)
        $card->labels;                       // N+1 (labels per card)
      }
    }

  Good:
    $board = Board::with([
      'columns' => fn($q) => $q->orderBy('position')
        .with([
          'cards' => fn($q) => $q->orderBy('position')
            .with('labels')
            .withCount('attachments')
        ])
    ])->findOrFail($id);

Dashboard:
  Bad:
    $user->boards()->count();    // query 1
    $user->cards()->count();     // query 2
    $user->attachments()->count(); // query 3
    $user->droplinks()->count();   // query 4

  Good:
    // Combine dalam satu fetch atau use materialized views (P2)

Activity log:
  Bad:
    $droplinks = DropLink::all();
    foreach ($droplinks as $link) {
      $link->attachment->original_name;  // N+1
      $link->card->title;                // N+1
    }

  Good:
    DropLink::with(['attachment:id,original_name', 'card:id,title'])
      ->orderBy('created_at', 'desc')
      .limit(10)
      .get();
```

### 10.2 Caching Strategy

| What | Cache Key | TTL | Invalidate | Notes |
|:---|:---|:---|:---|:---|
| User storage_used | `storage:{user_id}` | 5 menit | After upload/delete | Prevent frequent recalc |
| Board eager-loaded | `board:{board_id}` | 10 menit | After edit/delete/move | Kanban heavy-query |
| Dashboard metrics | `dashboard:{user_id}` | 5 menit | After action | Fast page load |
| Thumbnail | `thumb:{attachment_id}` | 7 hari | After gen/delete | Serve via cache |
| Config droplink | Config cached | Runtime | On deploy | Via `php artisan config:cache` |

### 10.3 Database Index Strategy

```
Critical Indexes:
─────────────────
users:
  - INDEX(id) ← PK
  - INDEX(email) ← UNIQUE
  - INDEX(google_id) ← UNIQUE

boards:
  - INDEX(user_id, archived_at)     ← user list + filter active
  - INDEX(id, user_id)              ← scoped route binding

cards:
  - INDEX(column_id, position)      ← order in column
  - INDEX(board_id)                 ← count per board
  - INDEX(user_id)                  ← scope owner

attachments:
  - INDEX(card_id)                  ← eager load
  - INDEX(user_id)                  ← isolation + quota calc
  - UNIQUE INDEX(path)              ← prevent dupe

droplinks:
  - UNIQUE INDEX(token)             ← public access
  - INDEX(user_id, revoked_at, expires_at) ← list active
  - INDEX(expires_at)               ← pruning task
  - INDEX(attachment_id)            ← find by file
  - INDEX(card_id)                  ← find by card

droplink_accesses:
  - INDEX(droplink_id, created_at)  ← activity log
  - INDEX(created_at)               ← pruning task
```

---

## Summary for Agent AI

**Gunakan dokumen ini untuk:**
1. **Evaluasi:** pahami halaman apa, komponen apa, data apa yang diperlukan
2. **Build fitur:** lihat scenario step-by-step, query patterns, edge cases
3. **Database:** tahu relasi, eager-load pattern, index yang perlu
4. **Testing:** validate state transitions, concurrent scenarios, isolation
5. **Optimization:** caching, query batching, index strategy

**Key takeaway:**
- Dashboard adalah **entry point**: live metrics + activity log
- Kanban adalah **core**: drag-drop dengan atomic position update
- Upload adalah **critical**: atomic kuota reserve + rollback
- DropLink adalah **USP**: atomic download counter + multi-status checks
- Everything is **user-isolated**: Policy + scoped query pada setiap endpoint