@php
    $siteName = 'WebGIS Sông Hậu';
    $pageDescription = $description ?? 'Bản đồ biến động đường bờ sông Hậu tại xã Bình Mỹ, An Giang giai đoạn 2018–2026: tốc độ lùi bờ, hotspot sạt lở và ảnh vệ tinh Sentinel-2.';
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $pageDescription }}">
<meta name="theme-color" content="#2855b8">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="vi_VN">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ url('/icon-512.png') }}">
<meta name="twitter:card" content="summary">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/icon-32.png" type="image/png" sizes="32x32">
<link rel="icon" href="/icon-192.png" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
