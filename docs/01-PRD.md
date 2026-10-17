# DropDeck — Product Requirements Document (PRD)

| | |
|:---|:---|
| **Versi** | 1.0 (MVP) |
| **Status** | Draft untuk implementasi |
| **Penulis** | Raka |
| **Target** | Proyek UTS — Universitas Siliwangi |
| **Stack** | Laravel 13, Livewire + Flux UI (starter kit, Fortify), PHP 8.5, MariaDB, RustFS (S3), FrankenPHP, Podman (lokal), Dokploy (produksi) |

---

## 1. Ringkasan

**DropDeck** adalah SaaS workspace visual yang menggabungkan **Kanban board** dengan **berbagi file aman (DropLink)**. Pengguna mengorganisir ide dan tugas dalam kartu, melampirkan file ke kartu, lalu membagikan file atau kartu lewat link publik yang punya batas waktu, batas unduhan, dan password opsional. Penerima tidak perlu akun.

> *"Organisir ide-visualmu, simpan asetnya dengan aman, dan bagikan hanya ketika kamu siap."*

## 2. Masalah yang Diselesaikan

1. Catatan, tugas, dan file proyek tersebar di banyak tempat.
2. Mengirim file ke klien lewat chat punya batas ukuran, tidak ada kontrol akses, dan link tidak bisa dicabut.
3. Layanan transfer file publik tidak terhubung dengan alur kerja (board/kartu) pengguna.

## 3. Tujuan dan Non-Tujuan

### 3.1 Tujuan (Goals)
| ID | Tujuan | Ukuran keberhasilan |
|:---|:---|:---|
| G1 | Pengguna dapat mengelola pekerjaan lewat Kanban | Board, kolom, kartu dapat dibuat, diubah, dipindah; posisi tersimpan |
| G2 | File tersimpan aman dan terisolasi per pengguna | Tidak ada akses lintas user (diuji otomatis) |
| G3 | Berbagi file dengan kontrol ketat | Link otomatis mati saat kedaluwarsa/habis kuota unduhan/dicabut |
| G4 | Berjalan identik di lokal dan produksi | Satu Containerfile dan satu pola compose dipakai di Podman lokal dan Dokploy |
| G5 | Kualitas SaaS yang dapat didemokan | Kuota storage, activity log, dashboard berfungsi end-to-end |

### 3.2 Non-Tujuan (Out of Scope MVP)
| Aspek | Tidak termasuk | Alasan |
|:---|:---|:---|
| Kolaborasi | Teams, multi-user pada satu board, edit bersamaan | Fokus isolasi data dulu |
| Real-time | WebSocket/notifikasi live | Kanban solo cukup dengan Livewire |
| Pembayaran | Plan Pro, payment gateway, billing | **Hanya tier Free** dengan batas storage |
| Media | Transcoding video, image editing | Butuh resource besar |
| Sharing lanjutan | Custom domain, embeddable widget | Kompleks (DNS) |
| Auth lanjutan | SSO enterprise, WorkOS, login selain Google/email | Fortify + Socialite sudah cukup |
| Mobile app | Aplikasi native | Web responsif saja |

## 4. Persona

| Persona | Deskripsi | Kebutuhan utama |
|:---|:---|:---|
| **Owner** (pengguna terdaftar) | Freelancer/mahasiswa/tim kecil, bekerja sendiri | Atur tugas, simpan aset, kirim file ke klien |
| **Guest** (penerima link) | Klien/teman, tanpa akun | Buka link, isi password bila ada, unduh file dengan mudah |
| **Admin sistem** | Raka (operator deployment) | Deploy, backup, pantau storage dan error |

## 5. Alur Pengguna Utama (End-to-End)

1. **Onboarding:** daftar dengan Google atau email, verifikasi email (untuk akun email), masuk ke dashboard kosong.
2. **Creation:** buat board, kolom, kartu "Desain Logo", upload 3 JPG dan 1 PDF.
3. **Organization:** geser kartu dari *To Do* ke *In Progress*.
4. **Sharing:** klik **Share DropLink** pada file PDF, set kedaluwarsa 24 jam, maksimum unduhan 3x, password opsional.
5. **Distribution:** salin link, kirim ke klien lewat WhatsApp.
6. **Access:** klien membuka link, memasukkan password, menekan tombol Unduh.
7. **Monitoring:** owner melihat "2/3 unduhan terpakai" dan riwayat akses di dashboard.

## 6. Kebutuhan Fungsional

Prioritas: **P0** wajib MVP, **P1** sebaiknya ada, **P2** fase 2.

### 6.1 Autentikasi dan Akun (AUTH)
| ID | Kebutuhan | Prioritas |
|:---|:---|:---|
| AUTH-01 | Registrasi dan login email + password (Fortify, starter kit) | P0 |
| AUTH-02 | Verifikasi email untuk akun email | P0 |
| AUTH-03 | Reset password via email | P0 |
| AUTH-04 | Login/daftar dengan Google (Socialite) | P0 |
| AUTH-05 | Jika email Google sudah terdaftar sebagai akun email, tautkan `google_id` ke akun tersebut (hanya jika email Google terverifikasi) | P0 |
| AUTH-06 | Akun Google-only boleh belum punya password; dapat menyetel password lewat pengaturan | P1 |
| AUTH-07 | Profile settings: ubah nama, email, password, hapus akun | P0 |
| AUTH-08 | Two-factor authentication (sudah ada di starter kit, dipertahankan) | P1 |
| AUTH-09 | Hapus akun menghapus seluruh data dan objek storage milik user | P0 |

### 6.2 Board dan Kolom (BOARD)
| ID | Kebutuhan | Prioritas |
|:---|:---|:---|
| BOARD-01 | CRUD board (judul, deskripsi, warna) | P0 |
| BOARD-02 | CRUD kolom kustom per board (judul) | P0 |
| BOARD-03 | Urutkan kolom dengan drag-and-drop | P0 |
| BOARD-04 | Arsipkan/pulihkan board | P1 |
| BOARD-05 | Hapus board menghapus kolom, kartu, lampiran, dan DropLink terkait | P0 |
| BOARD-06 | Template board (struktur kolom awal, mis. "Alur Konten") | P2 |

### 6.3 Kartu (CARD)
| ID | Kebutuhan | Prioritas |
|:---|:---|:---|
| CARD-01 | CRUD kartu: judul, deskripsi teks | P0 |
| CARD-02 | Drag-and-drop kartu antar dan dalam kolom; posisi tersimpan otomatis | P0 |
| CARD-03 | Label berwarna per board, ditempel ke kartu (many-to-many) | P0 |
| CARD-04 | Checklist per kartu (item, centang, urut) dengan indikator progres | P0 |
| CARD-05 | Hapus kartu menghapus lampiran dan DropLink terkait | P0 |
| CARD-06 | Pencarian kartu berdasarkan judul pada board | P1 |
| CARD-07 | Bulk action (pindah/hapus banyak kartu) | P2 |

### 6.4 Attachment Vault (VAULT)
| ID | Kebutuhan | Prioritas |
|:---|:---|:---|
| VAULT-01 | Upload multi-file ke kartu (PDF, ZIP, gambar, dokumen) | P0 |
| VAULT-02 | File disimpan di RustFS pada bucket **privat**, path terisolasi per user | P0 |
| VAULT-03 | Validasi ukuran per file, jumlah file per kartu, ekstensi terlarang, dan sisa kuota | P0 |
| VAULT-04 | Thumbnail otomatis untuk gambar (dan PDF, bila diaktifkan) lewat queue | P1 |
| VAULT-05 | Unduh dan pratinjau file milik sendiri lewat streaming terotorisasi (tanpa URL storage publik) | P0 |
| VAULT-06 | Hapus lampiran mengembalikan kuota dan menghapus objek storage | P0 |
| VAULT-07 | Ganti nama tampilan file (nama asli di DB; key storage tetap) | P2 |

### 6.5 DropLink (LINK)
| ID | Kebutuhan | Prioritas |
|:---|:---|:---|
| LINK-01 | Buat DropLink dari **file** atau dari **kartu** (halaman daftar file kartu) | P0 |
| LINK-02 | Kedaluwarsa wajib: 1 jam sampai 30 hari (default 24 jam) | P0 |
| LINK-03 | Batas unduhan opsional: 1–100 (kosong = tanpa batas) | P0 |
| LINK-04 | Password/PIN opsional (4–64 karakter), disimpan sebagai hash | P0 |
| LINK-05 | Guest dapat membuka dan mengunduh tanpa akun | P0 |
| LINK-06 | Owner dapat mencabut (revoke) link kapan saja | P0 |
| LINK-07 | Owner melihat daftar link: status (aktif/kedaluwarsa/habis/dicabut), sisa unduhan, kedaluwarsa | P0 |
| LINK-08 | Membuka halaman link **tidak** menghabiskan kuota unduhan; hanya aksi unduh yang dihitung | P0 |
| LINK-09 | Penghitungan unduhan atomik (tidak bisa melebihi batas saat request paralel) | P0 |
| LINK-10 | Brute-force password dibatasi (rate limit) | P0 |
| LINK-11 | Halaman publik `noindex` dan tidak menampilkan data pemilik selain nama file | P0 |
| LINK-12 | Perpanjang masa berlaku / ubah batas unduhan link yang ada | P2 |

### 6.6 Dashboard dan Analytics (DASH)
| ID | Kebutuhan | Prioritas |
|:---|:---|:---|
| DASH-01 | Storage monitor: progress bar pemakaian terhadap kuota (mis. 0.4 GB / 2 GB) | P0 |
| DASH-02 | Activity log akses DropLink: waktu, event, IP, user agent | P0 |
| DASH-03 | Ringkasan: jumlah board, kartu, file, link aktif | P1 |
| DASH-04 | Peringatan saat kuota di atas 80% dan 100% | P1 |

### 6.7 Fase 2 (opsional)
Template board, bulk action, export kolom ke PDF, perpanjang link, pencarian global, pratinjau PDF inline.

## 7. Aturan Bisnis dan Batas Default

Semua angka berada di `config/dropdeck.php` dan dapat diubah lewat environment.

| Parameter | Default | Env |
|:---|:---|:---|
| Kuota storage per user (tier Free) | 2 GB | `DROPDECK_QUOTA_MB=2048` |
| Ukuran maksimum per file | 100 MB | `DROPDECK_MAX_FILE_MB=100` |
| File per kartu | 20 | `DROPDECK_MAX_FILES_PER_CARD=20` |
| Board per user | 20 | `DROPDECK_MAX_BOARDS=20` |
| Kolom per board | 15 | `DROPDECK_MAX_COLUMNS=15` |
| Kartu per board | 500 | `DROPDECK_MAX_CARDS_PER_BOARD=500` |
| Label per board | 20 | `DROPDECK_MAX_LABELS=20` |
| Item checklist per kartu | 50 | `DROPDECK_MAX_CHECKLIST=50` |
| DropLink aktif per user | 100 | `DROPDECK_MAX_ACTIVE_LINKS=100` |
| Kedaluwarsa link | 1 jam – 30 hari | `DROPDECK_LINK_MAX_DAYS=30` |
| Batas unduhan link | 1 – 100 atau tanpa batas | `DROPDECK_LINK_MAX_DL=100` |
| Retensi log akses | 90 hari | `DROPDECK_LOG_RETENTION_DAYS=90` |
| Retensi link kedaluwarsa (record) | 30 hari setelah mati | `DROPDECK_LINK_RETENTION_DAYS=30` |

Aturan:
1. Kuota dihitung dari jumlah `size_bytes` lampiran. Thumbnail tidak dihitung.
2. Upload ditolak jika `storage_used + ukuran file > kuota`.
3. Menghapus lampiran, kartu, board, atau akun mengurangi `storage_used` secara konsisten.
4. Link dianggap **aktif** jika: tidak dicabut, belum kedaluwarsa, dan unduhan belum habis.
5. Link mati tidak dapat diaktifkan kembali (buat link baru).
6. Ekstensi terlarang (default): `php, phtml, phar, exe, msi, bat, cmd, com, scr, dll, jar, vbs, ps1, sh`. Daftar ada di config.
7. SVG dan HTML tidak pernah ditampilkan inline (selalu unduh); lihat dokumen Rules.

## 8. Daftar Halaman dan Route (tingkat produk)

| Area | Route | Akses |
|:---|:---|:---|
| Landing | `/` | Publik |
| Login / Register | `/login`, `/register` | Guest |
| Google OAuth | `/auth/google/redirect`, `/auth/google/callback` | Guest |
| Verifikasi/reset | `/email/verify`, `/forgot-password`, `/reset-password/{token}` | Sesuai Fortify |
| Dashboard | `/dashboard` | Auth + verified |
| Daftar board | `/boards` | Auth + verified |
| Board (Kanban) | `/boards/{board}` | Auth + owner |
| Kelola DropLink | `/links` | Auth + verified |
| Pengaturan | `/settings/profile`, `/settings/password`, `/settings/two-factor`, `/settings/appearance` | Auth |
| Halaman publik DropLink | `/d/{token}` | Publik (guest) |
| Unduh publik | `POST /d/{token}/download` (+ `/{attachment}` untuk link kartu) | Publik |
| Unduh/pratinjau milik sendiri | `/attachments/{attachment}/download`, `/attachments/{attachment}/thumbnail` | Auth + owner |
| Health check | `/up` | Publik |

## 9. Kebutuhan Non-Fungsional

| Kategori | Kebutuhan |
|:---|:---|
| **Keamanan** | Otorisasi via Policy pada setiap model; bucket privat; tanpa URL storage ke browser; password link di-hash; rate limit; CSRF; header keamanan; validasi file sisi server; lihat Rules |
| **Privasi** | Data user terisolasi; halaman publik tidak membocorkan identitas owner; IP di log disimpan hanya untuk aktivitas DropLink dengan retensi terbatas |
| **Performa** | Halaman board dimuat < 2 dtk untuk 500 kartu; drag-drop terasa instan (optimistic UI); thumbnail asinkron lewat queue |
| **Keandalan** | Penghitungan unduhan atomik; penghapusan objek storage lewat job yang dapat diulang (idempotent); healthcheck container |
| **Skalabilitas** | Stateless app container (session/cache/queue di database); storage terpisah di RustFS; mudah menambah worker |
| **Portabilitas** | Satu `Containerfile` multi-stage; konfigurasi 100% via environment; kompatibel Podman dan Docker |
| **Observabilitas** | Log ke stdout/stderr; `/up`; tabel activity log; `failed_jobs` |
| **Aksesibilitas/UX** | Responsif, dark mode (Flux), label dan fokus keyboard pada komponen interaktif |
| **Bahasa UI** | Bahasa Indonesia (default), string melalui file lang agar dapat dilokalkan |
| **Pemeliharaan** | Pint, Larastan, Pest; CI sederhana; dokumentasi di `docs/` |
| **Backup** | Dump MariaDB terjadwal + salinan data RustFS (lihat Architecture) |

## 10. Kriteria Penerimaan Utama

1. **Isolasi:** user A tidak dapat melihat, mengubah, mengunduh, atau membagikan board/kartu/file user B (semua endpoint mengembalikan 404/403) — diuji Pest.
2. **Kuota:** upload yang melewati kuota ditolak dengan pesan jelas; `storage_used` akurat setelah upload/hapus.
3. **DropLink kedaluwarsa:** setelah `expires_at`, unduh ditolak dan halaman menampilkan status kedaluwarsa.
4. **Batas unduhan:** dengan `max_downloads=3`, empat request unduh paralel menghasilkan tepat 3 sukses.
5. **Password:** salah password 5x/menit memicu throttle; benar membuka sesi unlock untuk link tersebut saja.
6. **Preview bot:** `GET /d/{token}` berkali-kali tidak mengubah `download_count`.
7. **Cabut link:** link yang dicabut langsung tidak dapat diakses.
8. **Hapus kartu/board/akun:** objek storage terkait terhapus dan kuota kembali.
9. **Google login:** akun baru terbentuk atau tertaut ke akun email yang sama tanpa duplikasi.
10. **Deployment:** `podman compose up` lokal berjalan dari nol; compose produksi di Dokploy berjalan dengan variabel environment saja.

## 11. Metrik

| Metrik | Tujuan |
|:---|:---|
| Waktu dari daftar ke link pertama | < 3 menit |
| Persentase unduhan DropLink sukses | > 95% (di luar link mati/password salah) |
| Error job (failed_jobs) per minggu | 0 yang tidak ditangani |
| Cakupan test aturan inti (quota, droplink, policy) | 100% skenario pada bagian 10 |

## 12. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|:---|:---|:---|
| Upload besar melewati PHP (memori/timeout) | Upload gagal | `php.ini` disetel; batas 100 MB; fase 2: presigned upload |
| Race condition kuota/unduhan | Kuota/limit bocor | Query atomik bersyarat di database |
| Bot preview (WhatsApp) menghabiskan unduhan | Link habis sebelum dipakai | Unduh hanya via POST aksi eksplisit |
| File berbahaya diunggah dan dibagikan | Penyalahgunaan | Ekstensi terlarang, `nosniff`, selalu `attachment`, opsi scan (fase 2: ClamAV), tombol lapor (fase 2) |
| Objek yatim di storage | Storage boros | Job hapus idempotent + job rekonsiliasi terjadwal |
| Perbedaan lokal vs produksi | Bug saat deploy | Image sama; konfigurasi via env; checklist deployment |
| Ketergantungan versi (PHP 8.5, imagick) | Build gagal | Verifikasi tag image dan ekstensi di awal (lihat README) |
| Waktu UTS terbatas | Scope tidak selesai | Prioritas P0/P1/P2; urutan milestone |

## 13. Milestone yang Disarankan

| # | Milestone | Isi | Hasil |
|:--|:---|:---|:---|
| M0 | Infrastruktur | Containerfile, compose Podman, RustFS terhubung, tes upload | Lingkungan lokal jalan |
| M1 | Auth | Fortify (ada), Google login, pengaturan kuota di user | Login email + Google |
| M2 | Skema | Migrasi, model, policy, factory, seeder | Skema sesuai dokumen |
| M3 | Kanban | Board, kolom, kartu, drag-drop, label, checklist | Kanban fungsional |
| M4 | Vault | Upload, kuota, download, thumbnail, hapus | Lampiran lengkap |
| M5 | DropLink | Buat, halaman publik, password, limit, revoke, log | Berbagi aman |
| M6 | Dashboard | Storage monitor, activity log, daftar link | Dashboard |
| M7 | Hardening | Test, rate limit, header, scheduler, cleanup | Siap demo |
| M8 | Deploy | Dokploy compose/template, domain, backup | Produksi |

## 14. Pertanyaan Terbuka

1. Apakah halaman publik link kartu menampilkan deskripsi kartu atau hanya daftar file? (**Usulan:** hanya judul + daftar file.)
2. Apakah akun yang dihapus memiliki masa tenggang pemulihan? (**Usulan MVP:** hapus langsung dengan konfirmasi password.)
3. Apakah perlu verifikasi email untuk login Google? (**Usulan:** tidak, email Google dianggap terverifikasi jika `email_verified=true`.)
4. Perlu halaman admin internal? (**Usulan:** tidak untuk MVP; pakai phpMyAdmin hanya di lokal.)

## 15. Glosarium

| Istilah | Arti |
|:---|:---|
| **DropLink** | Link publik berbatas untuk file atau kartu |
| **Vault** | Penyimpanan lampiran di RustFS |
| **Owner / Guest** | Pemilik akun / penerima link tanpa akun |
| **Aktif** | Link belum dicabut, belum kedaluwarsa, unduhan belum habis |
| **UMKM** | Konteks pengguna sasaran: usaha kecil dan freelancer |
