<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SEO Defaults
    |--------------------------------------------------------------------------
    |
    | These values are used by the shared head partial when a page does not
    | provide its own title, description, or social image. Override them via
    | environment variables when deploying to a different domain or brand.
    |
    */

    'title' => env('SEO_TITLE', 'DropDeck — Kanban & Berbagi Berkas'),

    'description' => env(
        'SEO_DESCRIPTION',
        'DropDeck adalah ruang kerja kanban untuk mengatur kartu, label, checklist, dan berbagi berkas lewat tautan yang aman.'
    ),

    'keywords' => env(
        'SEO_KEYWORDS',
        'DropDeck, kanban, manajemen proyek, berbagi berkas, drop link, produktivitas'
    ),

    'image' => env('SEO_IMAGE', 'og-image.png'),

    'twitter' => env('SEO_TWITTER'),

];
