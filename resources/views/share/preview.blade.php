<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $preview->title }} | {{ $siteName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Canonical page is the SPA — crawlers index that URL, not this one --}}
    <link rel="canonical" href="{{ $canonicalUrl }}">

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $preview->ogType }}">
    <meta property="og:title" content="{{ $preview->title }} | {{ $siteName }}">
    <meta property="og:description" content="{{ $preview->description }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $preview->image ?? $defaultImage }}">
    <meta property="og:image:secure_url" content="{{ $preview->image ?? $defaultImage }}">
    <meta property="og:image:alt" content="{{ $preview->title }}">
    <meta property="og:locale" content="fr_FR">

    {{-- Twitter / X --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@tunisiacamp">
    <meta name="twitter:title" content="{{ $preview->title }} | {{ $siteName }}">
    <meta name="twitter:description" content="{{ $preview->description }}">
    <meta name="twitter:image" content="{{ $preview->image ?? $defaultImage }}">

    {{-- Humans who land here go straight to the real page --}}
    <meta http-equiv="refresh" content="0;url={{ $canonicalUrl }}">
    <script>window.location.replace(@json($canonicalUrl));</script>
</head>
<body>
    <p>Redirection vers <a href="{{ $canonicalUrl }}">{{ $preview->title }} — {{ $siteName }}</a>…</p>
</body>
</html>
