# 📊 Laporan Analisis Gap dan Saran Pengembangan DropDeck SaaS

**Nama Proyek:** DropDeck  
**Stack Teknologi:** Laravel 11/12, Livewire 3, Alpine.js, Tailwind CSS, RustFS (S3), MariaDB, Docker/Podman  
**Tanggal Analisis:** 22 Oktober 2026  
**Status:** Pre-Demo UTS (Tahap M7 Hardening & M8 Deployment)

---

## 📑 Daftar Isi
1. [Isu Kritis & Bug (Segera Diperbaiki)](#1-isu-kritis--bug-segera-diperbaiki)
2. [Analisis Gap Fitur (Perbandingan Industri)](#2-analisis-gap-fitur-perbandingan-industri)
3. [Saran Pengembangan Teknis (Roadmap)](#3-saran-pengembangan-teknis-roadmap)
4. [Strategi Deployment & Demo (Tahap M8)](#4-strategi-deployment--demo-tahap-m8)

---

## 1. Isu Kritis & Bug (Segera Diperbaiki)

Bagian ini merangkum *bug* fungsional dan masalah UI/UX yang menghambat alur utama (core workflow) aplikasi.

### 🔴 Isu #1: DropLink Publik Tidak Menampilkan File (CRITICAL)
*   **Lokasi:** Halaman publik `/d/{token}`
*   **Gejala:** Setelah memasukkan password (jika ada), halaman hanya menampilkan metadata (tanggal expired, batas unduhan) tanpa menampilkan daftar file, preview, atau tombol download.
*   **Akar Masalah (Root Cause):** 
    *   `PublicDropLinkController` tidak melakukan *eager-loading* relationship `attachments` dari `targetable` (Card).
    *   Variabel `$attachments` tidak di-*pass* ke view `droplinks/show.blade.php`.
    *   View tidak memiliki logika *looping* (`@foreach`) untuk merender daftar file.
*   **Dampak:** Fitur unggulan utama (Secure Sharing) tidak berfungsi. Demo UTS akan gagal total jika ini tidak diperbaiki.

### 🟡 Isu #2: UI Upload Attachment Kurang Intuitif (HIGH)
*   **Lokasi:** Modal Detail Kartu → Section Lampiran
*   **Gejala:** Menggunakan input file native browser (`<input type="file">`) yang terlihat kaku, tidak ada *drag-and-drop area*, tidak ada *progress bar*, dan tidak ada *preview* file yang dipilih sebelum diunggah.
*   **Dampak:** *User experience* terasa ketinggalan zaman dan tidak memberikan *feedback* visual yang jelas saat proses upload berlangsung.

### 🟡 Isu #3: Form Board Kurang *Feedback* & Validasi (HIGH)
*   **Lokasi:** Modal "Papan baru" (`/boards`)
*   **Gejala:** 
    *   Validasi hanya muncul setelah tombol "Simpan" ditekan (tidak *real-time*).
    *   *Color picker* hanya berupa kotak input warna standar tanpa opsi cepat (*quick select*).
    *   Tidak ada *loading state* pada tombol saat proses penyimpanan.
*   **Dampak:** Pengguna bingung apakah sistem sedang memproses atau macet, serta kurang terbantu dalam memilih warna label.

---

## 2. Analisis Gap Fitur (Perbandingan Industri)

Analisis perbandingan antara DropDeck saat ini dengan standar aplikasi *productivity* dan *file sharing* modern (Google Keep, Notion, WeTransfer).

| Kategori Fitur | Standar Industri (Google Keep / Notion / WeTransfer) | Status DropDeck Saat Ini | Level Gap | Prioritas |
| :--- | :--- | :--- | :---: | :---: |
| **Visual Preview** | Menampilkan *thumbnail* gambar/PDF langsung di dalam kartu Kanban. | Hanya menampilkan nama file atau ikon generik. | 🔴 Tinggi | **P0** |
| **Direct Drag-Drop** | File dari desktop bisa di-*drag* langsung ke area kartu di board. | Harus buka modal → scroll ke bawah → klik Browse. |  Sedang | **P1** |
| **Rich Text Editor** | Mendukung *Bold*, *Italic*, *List*, dan *Hyperlink* pada deskripsi. | Masih menggunakan *plain text* (`<textarea>`). | 🟡 Sedang | **P1** |
| **File Versioning** | Opsi "Replace" file dengan menyimpan riwayat versi lama. | *Overwrite* diam-diam atau membuat duplikat tanpa jejak. |  Rendah | **P2** |
| **Search/Filter** | Pencarian teks di dalam isi kartu dan filter berdasarkan label. | Belum ada fitur pencarian mendalam. |  Rendah | **P2** |

---

## 3. Saran Pengembangan Teknis (Roadmap)

Berikut adalah rekomendasi solusi teknis untuk menutup gap dan memperbaiki isu di atas, diurutkan berdasarkan prioritas pengerjaan.

### 🚀 Fase 1: Perbaikan Kritis (Sebelum Demo)
**Target:** Memastikan alur inti (Upload → Share → Download) berjalan 100%.

1.  **Perbaiki `PublicDropLinkController`:**
    *   Tambahkan eager loading: `DropLink::with(['targetable', 'targetable.attachments', 'user'])`.
    *   Buat helper method `getAttachments()` untuk menangani target berupa Card (koleksi) atau Attachment (tunggal).
    *   Pastikan view `droplinks/show.blade.php` melakukan *looping* `$attachments` dan menyediakan tombol download yang mengarah ke route `droplink.download`.
2.  **Implementasi Thumbnail Preview di Card:**
    *   Gunakan package `intervention/image` atau fitur bawaan RustFS untuk men-generate thumbnail saat upload.
    *   Tampilkan `<img>` atau PDF preview icon di komponen Livewire `Card.php` jika attachment bertipe gambar/dokumen.

### 🛠️ Fase 2: Peningkatan UX (Pasca Demo / Pengembangan Lanjutan)
**Target:** Membuat aplikasi terasa modern, responsif, dan mudah digunakan.

1.  **Upgrade Upload Zone dengan Alpine.js:**
    *   Buat area *drag-and-drop* menggunakan event `@dragover` dan `@drop` dari Alpine.js.
    *   Tambahkan *progress bar* menggunakan event `wire:upload.progress` dari Livewire.
    *   Tampilkan *preview* file yang dipilih sebelum tombol "Upload" ditekan.
2.  **Integrasi Rich Text Editor (Trix / Quill):**
    *   Ganti `<textarea>` deskripsi kartu dengan **Trix Editor** (sudah terintegrasi baik dengan Laravel/Livewire) atau **Quill.js**.
    *   Simpan output sebagai HTML yang di-*sanitize* (gunakan `HTMLPurifier` atau `strip_tags` terbatas) untuk mencegah XSS.
3.  **Direct Drop to Card:**
    *   Tambahkan *event listener* `x-on:drop` pada elemen kartu Kanban.
    *   Tangkap `$event.dataTransfer.files` dan kirim ke Livewire via `$wire.uploadToCard(files, cardId)`.

### 🛡️ Fase 3: Hardening & Safety (Opsional)
**Target:** Keamanan data dan ketahanan sistem.

1.  **File Versioning & Replace Logic:**
    *   Tambahkan validasi di `UploadZone.php`: jika nama file sudah ada, tampilkan modal konfirmasi "Timpa (Replace)" atau "Simpan sebagai Versi Baru".
    *   Jika "Versi Baru", simpan file lama di folder `archive/` dan update path di database.
2.  **Rate Limiting & Security Headers:**
    *   Pastikan route publik `/d/{token}` memiliki *rate limit* ketat (misal: 10 request/menit per IP) untuk mencegah *brute-force* token.
    *   Tambahkan header keamanan `X-Content-Type-Options: nosniff` pada response download.

---

## 4. Strategi Deployment & Demo (Tahap M8)

Untuk membuktikan bahwa DropDeck adalah layanan SaaS yang *manageable* dan *production-ready*, deployment tidak boleh hanya dilakukan via CLI (terminal).

### 📝 Checklist Pra-Deploy (M8)
- [ ] Semua *Pest Test* lulus di lingkungan lokal (`php artisan test`).
- [ ] File `compose.prod.yaml` sudah di-*push* ke branch `main` GitHub.
- [ ] Variabel environment produksi (APP_KEY, DB credentials, AWS/RustFS keys) sudah disiapkan.
- [ ] Redirect URI Google OAuth di Google Cloud Console sudah diperbarui ke domain produksi (`https://.../auth/google/callback`).
- [ ] Domain sudah di-*pointing* (A Record) ke IP publik server Proxmox.

---

## 📌 Kesimpulan

DropDeck telah memiliki fondasi arsitektur SaaS yang sangat solid (Multi-user isolation, Object Storage terintegrasi, dan Livewire untuk interaktivitas). Namun, untuk mencapai standar *user experience* aplikasi modern dan memastikan keberhasilan demo UTS, **perbaikan kritis pada DropLink Public Page** dan **penambahan visual preview pada Kanban Card** adalah hal yang mutlak harus diselesaikan terlebih dahulu. 

Dengan menutup gap fitur utama dan melakukan deployment via GUI Dokploy, DropDeck akan bertransformasi dari sekadar "tugas kuliah" menjadi portofolio SaaS *self-hosted* yang impresif dan layak produksi.