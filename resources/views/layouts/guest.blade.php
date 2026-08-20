<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|libre-baskerville:400,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-body">
    <main class="auth-layout">
        <section class="auth-art">
            <a href="{{ route('home') }}" class="brand brand-light"><span class="brand-mark">A+</span><span>Content Maker<small>Author studio</small></span></a>
            <div class="auth-quote"><span class="folio">KDP · A+ · 01</span><blockquote>“Give every book a page that feels as considered as its cover.”</blockquote><p>Build thoughtful, image-led content without wrestling with the KDP editor.</p></div>
            <div class="book-stack" aria-hidden="true"><i></i><i></i><i></i></div>
        </section>
        <section class="auth-panel">
            <a href="{{ route('home') }}" class="auth-back">← Back to home</a>
            <div class="auth-card">@yield('content')</div>
        </section>
    </main>
</body>
</html>
