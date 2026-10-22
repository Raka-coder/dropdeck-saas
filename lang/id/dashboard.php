<?php

declare(strict_types=1);

return [
    'title' => 'Dashboard',
    'subtitle' => 'Ringkasan papan, berkas, dan aktivitas DropLink Anda.',
    'greeting' => 'Selamat datang kembali, :name.',

    'stats' => [
        'boards' => 'Papan aktif',
        'cards' => 'Kartu',
        'files' => 'Berkas',
        'active_links' => 'Link aktif',
    ],

    'storage' => [
        'title' => 'Penyimpanan',
        'used_of' => ':used dari :quota terpakai',
        'remaining' => 'Sisa :remaining',
        'levels' => [
            'ok' => 'Aman',
            'warning' => 'Hampir penuh',
            'full' => 'Penuh',
        ],
        'warning' => 'Pemakaian kuota sudah di atas :percent%. Hapus berkas yang tidak terpakai.',
        'full' => 'Kuota penyimpanan penuh. Unggahan baru akan ditolak sampai ruang tersedia.',
    ],

    'links' => [
        'title' => 'DropLink terbaru',
        'empty' => 'Belum ada DropLink.',
        'manage' => 'Kelola semua',
        'untitled' => 'Tanpa nama',
        'expires' => 'Kedaluwarsa :date',
    ],

    'activity' => [
        'title' => 'Aktivitas DropLink',
        'subtitle' => 'Riwayat akses terhadap link milik Anda.',
        'event' => 'Event',
        'date' => 'Tanggal',
        'all_events' => 'Semua event',
        'reset' => 'Reset',
        'empty' => 'Belum ada aktivitas.',
        'time' => 'Waktu',
        'link' => 'Link',
        'ip' => 'IP',
        'user_agent' => 'User agent',
        'view_all' => 'Lihat semua aktivitas',
    ],

    'events' => [
        'view' => 'Dibuka',
        'password_ok' => 'Password benar',
        'password_failed' => 'Password salah',
        'download' => 'Diunduh',
        'blocked' => 'Diblokir',
    ],
];
