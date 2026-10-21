<?php

declare(strict_types=1);

return [

    'new' => 'Kartu baru',
    'add' => 'Tambah kartu',
    'title' => 'Judul kartu',
    'description' => 'Deskripsi',
    'save' => 'Simpan',
    'cancel' => 'Batal',
    'edit' => 'Ubah kartu',
    'details' => 'Detail kartu',
    'delete' => 'Hapus kartu',
    'delete_confirm' => 'Hapus kartu ini?',
    'delete_hint' => 'Kartu beserta label, checklist, dan lampirannya akan dihapus permanen.',

    'empty' => 'Belum ada kartu',
    'placeholder' => 'Tulis judul kartu lalu Enter',
    'created' => 'Kartu ditambahkan.',
    'updated' => 'Kartu diperbarui.',
    'deleted' => 'Kartu dihapus.',
    'moved' => 'Kartu dipindahkan.',

    'labels' => 'Label',
    'labels_hint' => 'Pilih label untuk kartu ini.',
    'no_labels' => 'Belum ada label di papan ini.',
    'manage_labels' => 'Kelola label',

    'checklist' => 'Checklist',
    'checklist_empty' => 'Belum ada item checklist.',
    'checklist_placeholder' => 'Tambah item checklist',
    'checklist_add' => 'Tambah',
    'checklist_progress' => ':done dari :total selesai',

    'attachments' => 'Lampiran',
    'attachments_soon' => 'Lampiran tersedia pada milestone berikutnya.',

    'label_name' => 'Nama label',
    'label_color' => 'Warna label',
    'label_add' => 'Tambah label',
    'labels_created' => 'Label ditambahkan.',
    'checklist_added' => 'Item checklist ditambahkan.',

    'validation' => [
        'title_required' => 'Judul kartu wajib diisi.',
        'title_min' => 'Judul kartu minimal 2 karakter.',
        'title_max' => 'Judul kartu maksimal 120 karakter.',
        'description_max' => 'Deskripsi maksimal 5000 karakter.',
        'label_required' => 'Nama label wajib diisi.',
        'label_max' => 'Nama label maksimal 40 karakter.',
        'label_unique' => 'Label dengan nama ini sudah ada di papan.',
        'label_color_required' => 'Warna label wajib dipilih.',
        'label_color_format' => 'Format warna label harus #RRGGBB.',
        'checklist_required' => 'Isi checklist wajib diisi.',
        'checklist_max' => 'Isi checklist maksimal 200 karakter.',
    ],

    'errors' => [
        'cross_board' => 'Kartu dan kolom harus berada di papan yang sama.',
        'move_failed' => 'Kartu gagal dipindahkan. Susunan dikembalikan.',
    ],

];
