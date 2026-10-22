<?php

declare(strict_types=1);

return [

    'title' => 'Papan',
    'subheading' => 'Kelola papan dan alur kerja Anda.',

    'new' => 'Papan baru',
    'create' => 'Buat papan',
    'edit' => 'Ubah papan',
    'save' => 'Simpan',
    'cancel' => 'Batal',
    'open' => 'Buka',
    'back' => 'Kembali',
    'name' => 'Nama papan',
    'description' => 'Deskripsi',
    'color' => 'Warna',

    'empty' => 'Belum ada papan',
    'empty_hint' => 'Buat papan pertama untuk mulai mengatur pekerjaan.',
    'archived_empty' => 'Tidak ada papan yang diarsipkan.',
    'search_placeholder' => 'Cari papan...',
    'show_archived' => 'Lihat arsip',
    'show_active' => 'Lihat papan aktif',
    'archived_badge' => 'Diarsipkan',
    'archive' => 'Arsipkan',
    'restore' => 'Pulihkan',
    'delete' => 'Hapus papan',
    'delete_confirm' => 'Hapus papan ini?',
    'delete_hint' => 'Semua kolom, kartu, dan lampiran di dalamnya akan dihapus permanen.',

    'cards_count' => ':count kartu',
    'created' => 'Papan berhasil dibuat.',
    'updated' => 'Papan berhasil diperbarui.',
    'archived' => 'Papan diarsipkan.',
    'restored' => 'Papan dipulihkan.',
    'deleted' => 'Papan dihapus.',

    'validation' => [
        'title_required' => 'Nama papan wajib diisi.',
        'title_min' => 'Nama papan minimal 2 karakter.',
        'title_max' => 'Nama papan maksimal 80 karakter.',
        'description_max' => 'Deskripsi maksimal 500 karakter.',
        'color_required' => 'Warna wajib dipilih.',
        'color_format' => 'Format warna harus #RRGGBB.',
        'limit' => 'Maksimal :max papan per akun.',
    ],

];
