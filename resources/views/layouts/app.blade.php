<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Studio') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|libre-baskerville:400,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="app-body">
    <div class="app-shell">
        <aside class="app-sidebar" id="app-sidebar">
            <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark">A+</span><span>Content Maker<small>Author studio</small></span></a>
            <nav class="side-nav" aria-label="Application">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Overview</a>
                <a href="{{ route('projects.index') }}" class="{{ request()->routeIs('projects.*') ? 'active' : '' }}">My projects</a>
                <a href="{{ route('templates.index') }}" class="{{ request()->routeIs('templates.*') ? 'active' : '' }}">Template gallery</a>
                @if(auth()->user()->isAdmin())
                    <span class="nav-label">Administration</span>
                    <a href="{{ route('admin.templates.index') }}" class="{{ request()->routeIs('admin.*') ? 'active' : '' }}">Manage templates</a>
                @endif
            </nav>
            <div class="sidebar-foot">
                <a href="{{ route('profile.edit') }}" class="user-chip"><span>{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><strong>{{ auth()->user()->name }}<small>{{ auth()->user()->email }}</small></strong></a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-button">Sign out</button></form>
            </div>
        </aside>
        <div class="app-main">
            <header class="app-topbar">
                <button class="menu-button" type="button" data-sidebar-toggle aria-label="Toggle navigation">☰</button>
                <div><p class="eyebrow">A+ Content Maker</p><h1>@yield('page-title', 'Author studio')</h1></div>
                <a class="button button-primary" href="{{ route('projects.create') }}">New project</a>
            </header>
            <main class="app-content">
                @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="flash flash-error" role="alert">{{ session('error') }}</div>@endif
                @yield('content')
            </main>
        </div>
    </div>
    <div id="toast-region" class="toast-region" aria-live="polite"></div>
    @stack('scripts')
</body>
</html>
