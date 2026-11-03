<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="@yield('body_class')">
<div class="app-shell">
    <aside class="sidebar" id="app-sidebar" aria-label="Navigasi utama">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-mark">J</span>
            <span><strong>JadwalKampus</strong><small>Sistem Penjadwalan</small></span>
        </a>

        <nav class="sidebar-nav">
            <div class="nav-label">Menu Utama</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <i class="nav-icon" data-lucide="layout-dashboard"></i><span>Dashboard</span>
            </a>
            <a href="{{ route('schedule') }}" class="nav-link {{ request()->routeIs('schedule') ? 'active' : '' }}" @if(request()->routeIs('schedule')) aria-current="page" @endif>
                <i class="nav-icon" data-lucide="calendar-range"></i><span>Jadwal Ruang</span>
            </a>
            <a href="{{ route('calendar.weekly') }}" class="nav-link {{ request()->routeIs('calendar.weekly') ? 'active' : '' }}" @if(request()->routeIs('calendar.weekly')) aria-current="page" @endif>
                <i class="nav-icon" data-lucide="calendar-days"></i><span>Kalender Mingguan</span>
            </a>
            <a href="{{ route('rooms.index') }}" class="nav-link {{ request()->routeIs('rooms.index') ? 'active' : '' }}" @if(request()->routeIs('rooms.index')) aria-current="page" @endif>
                <i class="nav-icon" data-lucide="door-open"></i><span>Daftar Kelas</span>
            </a>
            <a href="{{ route('bookings.index') }}" class="nav-link {{ request()->routeIs('bookings.*') ? 'active' : '' }}" @if(request()->routeIs('bookings.*')) aria-current="page" @endif>
                <i class="nav-icon" data-lucide="clipboard-check"></i><span>Booking Saya</span>
            </a>

            @if(auth()->user()->isLecturer())
                <div class="nav-label">Menu Dosen</div>
                <a href="{{ route('lecturer.schedules.index') }}" class="nav-link {{ request()->routeIs('lecturer.schedules.index') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="book-open-check"></i><span>Jadwal Saya</span>
                </a>
                <a href="{{ route('lecturer.schedules.create') }}" class="nav-link {{ request()->routeIs('lecturer.schedules.create') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="calendar-plus"></i><span>Tambah Jadwal</span>
                </a>
            @endif

            @if(auth()->user()->isAdmin())
                <div class="nav-label">Administrasi</div>
                <a href="{{ route('admin.rooms.index') }}" class="nav-link {{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="school"></i><span>Kelola Kelas</span>
                </a>
                <a href="{{ route('admin.priorities.index') }}" class="nav-link {{ request()->routeIs('admin.priorities.*') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="clock-3"></i><span>Prioritas Jadwal</span>
                </a>
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="users"></i><span>Kelola Pengguna</span>
                </a>
                <a href="{{ route('admin.imports.index') }}" class="nav-link {{ request()->routeIs('admin.imports.*') || request()->routeIs('admin.schedules.*') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="file-spreadsheet"></i><span>Import &amp; Jadwal</span>
                </a>
                <a href="{{ route('admin.blocks.index') }}" class="nav-link {{ request()->routeIs('admin.blocks.*') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="ban"></i><span>Blokir Ruangan</span>
                </a>
                <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="chart-no-axes-column"></i><span>Laporan Pemakaian</span>
                </a>
                <a href="{{ route('admin.audits.index') }}" class="nav-link {{ request()->routeIs('admin.audits.*') ? 'active' : '' }}">
                    <i class="nav-icon" data-lucide="history"></i><span>Log Aktivitas</span>
                </a>
            @endif
        </nav>

        <div class="sidebar-account">
            <div class="sidebar-user">
                <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role_label }}</small></span>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="logout-btn"><i data-lucide="log-out"></i><span>Keluar</span></button>
            </form>
        </div>
    </aside>

    <button class="sidebar-backdrop" id="sidebar-backdrop" type="button" aria-label="Tutup menu"></button>

    <div class="main">
        <header class="topbar">
            <div class="topbar-heading">
                <button type="button" class="menu-button" id="sidebar-toggle" aria-label="Buka menu" aria-controls="app-sidebar" aria-expanded="false"><i data-lucide="menu"></i></button>
                <div><small>Portal Akademik</small><h1>@yield('header', 'Sistem Penjadwalan')</h1></div>
            </div>
            <span class="topbar-date">{{ now()->translatedFormat('l, d F Y') }}</span>
        </header>

        <main class="content">
            @if(session('success'))<div class="alert alert-success"><span>✓</span><div>{{ session('success') }}</div></div>@endif
            @if(session('error'))<div class="alert alert-danger"><span>!</span><div>{{ session('error') }}</div></div>@endif
            @if($errors->any())<div class="alert alert-danger"><span>!</span><div><strong>Periksa kembali data yang diisi:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
<script>
if (window.lucide) lucide.createIcons();
(() => {
    const sidebar = document.getElementById('app-sidebar');
    const toggle = document.getElementById('sidebar-toggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar || !toggle || !backdrop) return;

    const closeSidebar = () => {
        sidebar.classList.remove('open');
        document.body.classList.remove('sidebar-is-open');
        toggle.setAttribute('aria-expanded', 'false');
    };
    const openSidebar = () => {
        sidebar.classList.add('open');
        document.body.classList.add('sidebar-is-open');
        toggle.setAttribute('aria-expanded', 'true');
    };

    toggle.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
    backdrop.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSidebar(); });
})();
</script>
</body>
</html>
