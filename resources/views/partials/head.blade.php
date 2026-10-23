@php
    $appName = config('app.name', 'DropDeck');
    $metaTitle = filled($title ?? null) ? $title.' — '.$appName : config('seo.title', $appName);
    $metaDescription = filled($description ?? null) ? $description : config('seo.description');
    $metaRobots = $robots ?? 'index, follow';
    $canonicalUrl = $canonical ?? url()->current();
    $metaImage = $image ?? config('seo.image', 'og-image.png');
    $metaImageUrl = str_starts_with($metaImage, 'http://') || str_starts_with($metaImage, 'https://')
        ? $metaImage
        : asset($metaImage);
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<meta name="keywords" content="{{ config('seo.keywords') }}">
<meta name="robots" content="{{ $metaRobots }}">
<meta name="author" content="{{ $appName }}">
<meta name="application-name" content="{{ $appName }}">
<meta name="theme-color" content="#4F46E5">
<meta name="format-detection" content="telephone=no">

<link rel="canonical" href="{{ $canonicalUrl }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $appName }}">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:image" content="{{ $metaImageUrl }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $appName }} — kanban dan berbagi berkas">
<meta property="og:locale" content="id_ID">

<meta name="twitter:card" content="summary_large_image">
@if (filled(config('seo.twitter')))
<meta name="twitter:site" content="{{ config('seo.twitter') }}">
@endif
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $metaImageUrl }}">

<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@stack('head')
