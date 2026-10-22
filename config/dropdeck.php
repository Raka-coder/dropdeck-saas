<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Batas Bisnis
    |--------------------------------------------------------------------------
    |
    | Semua angka batas aplikasi berada di sini (PRD bagian 7). Jangan
    | menuliskan angka batas langsung di Action, komponen, atau view.
    |
    */

    'limits' => [
        'quota_mb' => (int) env('DROPDECK_QUOTA_MB', 2048),
        'max_file_mb' => (int) env('DROPDECK_MAX_FILE_MB', 100),
        'max_files_per_card' => (int) env('DROPDECK_MAX_FILES_PER_CARD', 20),
        'max_boards' => (int) env('DROPDECK_MAX_BOARDS', 20),
        'max_columns' => (int) env('DROPDECK_MAX_COLUMNS', 15),
        'max_cards_per_board' => (int) env('DROPDECK_MAX_CARDS_PER_BOARD', 500),
        'max_labels' => (int) env('DROPDECK_MAX_LABELS', 20),
        'max_checklist' => (int) env('DROPDECK_MAX_CHECKLIST', 50),
        'max_active_links' => (int) env('DROPDECK_MAX_ACTIVE_LINKS', 100),
        'link_max_days' => (int) env('DROPDECK_LINK_MAX_DAYS', 30),
        'link_max_downloads' => (int) env('DROPDECK_LINK_MAX_DL', 100),
        'log_retention_days' => (int) env('DROPDECK_LOG_RETENTION_DAYS', 90),
        'link_retention_days' => (int) env('DROPDECK_LINK_RETENTION_DAYS', 30),
        'upload_per_minute' => (int) env('DROPDECK_UPLOAD_PER_MINUTE', 30),
        'link_create_per_minute' => (int) env('DROPDECK_LINK_CREATE_PER_MINUTE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kolom Awal Papan
    |--------------------------------------------------------------------------
    |
    | Papan baru langsung dibuat dengan kolom alur kerja dasar agar pengguna
    | bisa mulai menambah kartu tanpa menyiapkan kolom secara manual.
    |
    */

    'default_columns' => ['To Do', 'In Progress', 'Done'],

    /*
    |--------------------------------------------------------------------------
    | Vault (Attachment)
    |--------------------------------------------------------------------------
    |
    | Disk privat tempat objek lampiran disimpan. Ekstensi dan MIME terlarang
    | dicek terhadap konten file, bukan hanya nama yang dikirim klien.
    |
    */

    'vault' => [
        'disk' => env('DROPDECK_VAULT_DISK', 'vault'),

        'forbidden_extensions' => [
            'php', 'phtml', 'phar', 'exe', 'msi', 'bat', 'cmd', 'com', 'scr',
            'dll', 'jar', 'vbs', 'ps1', 'sh', 'svg', 'html', 'htm', 'xhtml',
            'js', 'mjs', 'cjs',
        ],

        'forbidden_mime_types' => [
            'application/x-httpd-php',
            'application/x-php',
            'application/x-executable',
            'application/x-dosexec',
            'application/x-msdownload',
            'application/x-sh',
            'application/x-shellscript',
            'application/javascript',
            'application/xhtml+xml',
            'image/svg+xml',
            'text/html',
            'text/javascript',
            'text/x-php',
            'text/x-shellscript',
        ],

        'safe_mime_types' => [
            'application/pdf',
            'application/zip',
            'application/x-zip-compressed',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/rtf',
            'text/plain',
            'text/csv',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],

        'thumbnail' => [
            'max_dimension' => 480,
            'quality' => 80,
            'max_source_pixels' => 40_000_000,
        ],

        'pdf_thumbnail_enabled' => (bool) env('THUMBNAIL_PDF_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Ambang peringatan pemakaian kuota pada storage meter (PRD DASH-04).
    |
    */

    'dashboard' => [
        'quota_warning_percent' => (int) env('DROPDECK_QUOTA_WARNING_PERCENT', 80),
        'quota_critical_percent' => (int) env('DROPDECK_QUOTA_CRITICAL_PERCENT', 100),
    ],

];
