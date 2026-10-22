# Checklist Perbaikan UI

- [x] Pada komponen form detail kartu, pindahkan button share dan delete kartu agar tidak menabrak close button.
  - Status: selesai. Aksi dipindah ke footer modal, header diberi ruang `pr-8`.
- [x] Button unggah pada form detail kartu berfungsi; file otomatis diunggah setelah dipilih tanpa button manual, sehingga lampiran dan storage terisi.
  - Status: selesai. `UploadZone::updatedFiles()` memanggil upload otomatis; button unggah manual dihapus.
- [x] Hindari icon emoji; gunakan icon Lucide.
  - Status: selesai. Emoji di upload zone dihapus; icon Lucide diimpor lewat `php artisan flux:icon`.
- [x] Tambahkan icon pada setiap button yang mengandung aktivitas.
  - Status: selesai. Audit seluruh `<flux:button>` dengan teks memastikan setiap button memiliki `icon`.
- [x] Gunakan komponen breadcrumbs pada halaman board/papan atau halaman multi-route.
  - Status: selesai. Breadcrumbs ditambahkan di daftar papan, detail papan, DropLink, dan aktivitas DropLink.
- [x] Perbaiki button agar hanya memakai varian `primary`, `filled`, `danger`, atau `ghost`.
  - Status: selesai. Tidak ada button tanpa varian; varian `outline` pada setup 2FA diganti `ghost`.
- [x] Tidak ada button bertipe `default` di seluruh halaman dashboard.
  - Status: selesai. Audit `<flux:button>` tidak menemukan varian default maupun button tanpa varian.

## Verifikasi

- [x] `vendor/bin/pint --dirty --format agent` — passed.
- [x] `vendor/bin/phpstan analyse --memory-limit=1G` — 0 error.
- [x] `php artisan test --compact` — 206 tes, 560 assertion, passed.
