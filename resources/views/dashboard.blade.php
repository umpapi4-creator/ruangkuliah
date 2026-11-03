@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')
@section('body_class', 'dashboard-layout')

@section('content')
@php
    $usedRoomCount = $todayScheduleItems
        ->filter(fn ($item) => $item['start']->lte(now()) && $item['end']->gt(now()))
        ->map(fn ($item) => $item['room']->id)
        ->unique()
        ->count();
    $availableRoomCount = max(0, $stats['rooms'] - $usedRoomCount);
    $firstName = explode(' ', auth()->user()->name)[0];
@endphp

<div class="portal-dashboard">
    <section class="portal-alert">
        <span class="portal-alert-icon">✓</span>
        <div>
            <strong>Selamat datang kembali, {{ auth()->user()->name }}!</strong>
            <p>Sistem berjalan normal. {{ $stats['rooms'] }} ruang kuliah aktif dan siap digunakan hari ini.</p>
        </div>
    </section>

    <section class="portal-heading">
        <div>
            <div class="portal-kicker"><span>Portal Akademik</span><i>•</i><small>Tahun Akademik 2026/2027</small></div>
            <h1>Halo, {{ $firstName }} 👋</h1>
            <p>{{ auth()->user()->isAdmin() ? 'Pantau penggunaan ruang, pengguna aktif, dan pembaruan jadwal perkuliahan hari ini.' : 'Cek jadwal dan gunakan ruang kelas yang masih tersedia.' }}</p>
        </div>
        <div class="portal-heading-actions">
            <div class="portal-date-card"><span class="portal-date-icon">▣</span><div><small>Hari Ini</small><strong>{{ now()->translatedFormat('l, d F Y') }}</strong></div></div>
            @if(auth()->user()->canBookRooms())
                <a href="{{ route('bookings.create') }}" class="portal-primary-action">＋ Gunakan Ruang Kelas</a>
            @endif
        </div>
    </section>

    <section class="portal-stats">
        <article class="portal-stat portal-stat-indigo">
            <span class="portal-stat-accent"></span>
            <div><small>Ruang Aktif</small><strong>{{ $stats['rooms'] }} <em>ruang</em></strong><p>{{ $availableRoomCount }} ruang siap digunakan</p></div>
            <span class="portal-stat-icon">□</span>
        </article>
        @if(auth()->user()->isAdmin())
            <article class="portal-stat portal-stat-green">
                <span class="portal-stat-accent"></span>
                <div><small>Pengguna Aktif</small><strong>{{ $stats['users'] }} <em>akun</em></strong><p>Mahasiswa dan dosen</p></div>
                <span class="portal-stat-icon">♙</span>
            </article>
            <article class="portal-stat portal-stat-amber">
                <span class="portal-stat-accent"></span>
                <div><small>Jadwal Hari Ini</small><strong>{{ $stats['today'] }} <em>kelas</em></strong><p>Booking dan jadwal tetap</p></div>
                <span class="portal-stat-icon">◷</span>
            </article>
            <article class="portal-stat portal-stat-blue">
                <span class="portal-stat-accent"></span>
                <div><small>Penggunaan Mendatang</small><strong>{{ $stats['upcoming'] }} <em>booking</em></strong><p>Jadwal setelah waktu sekarang</p></div>
                <span class="portal-stat-icon">✓</span>
            </article>
        @else
            <article class="portal-stat portal-stat-green">
                <span class="portal-stat-accent"></span>
                <div><small>Penggunaan Mendatang</small><strong>{{ $stats['mine'] }} <em>booking</em></strong><p>Booking aktif milik Anda</p></div>
                <span class="portal-stat-icon">✓</span>
            </article>
            <article class="portal-stat portal-stat-amber">
                <span class="portal-stat-accent"></span>
                <div><small>Jadwal Hari Ini</small><strong>{{ $stats['today'] }} <em>kelas</em></strong><p>Seluruh penggunaan hari ini</p></div>
                <span class="portal-stat-icon">◷</span>
            </article>
            <article class="portal-stat portal-stat-blue">
                <span class="portal-stat-accent"></span>
                <div><small>Total Riwayat Saya</small><strong>{{ $stats['history'] }} <em>booking</em></strong><p>Seluruh catatan penggunaan</p></div>
                <span class="portal-stat-icon">↺</span>
            </article>
        @endif
    </section>

    <section class="portal-content-grid">
        <div class="portal-main-column">
            <article class="portal-card portal-schedule-card">
                <header class="portal-card-header">
                    <div><div class="portal-card-title"><h2>Jadwal Kuliah Sedang Aktif</h2><span>{{ $activeScheduleItems->count() }} Kelas Aktif</span></div><p>Kelas yang sedang berlangsung saat ini · {{ now()->format('H.i') }}</p></div>
                    <a href="{{ route('schedule') }}">Lihat semua →</a>
                </header>
                <div class="portal-card-body portal-schedule-list">
                    @forelse($activeScheduleItems as $item)
                        <article class="portal-schedule-item has-prodi-theme prodi-{{ $item['theme'] }} is-running">
                            <span class="portal-running-label"><i></i>Sedang Berlangsung</span>
                            <div class="portal-schedule-layout">
                                <div class="portal-schedule-main">
                                    <div class="portal-time-box"><strong>{{ $item['start']->format('H.i') }}</strong><small>s/d</small><strong>{{ $item['end']->format('H.i') }}</strong></div>
                                    <div class="portal-course-info">
                                        <div class="portal-course-title"><h3>{{ $item['course_name'] }}</h3><span class="portal-prodi-badge">{{ $item['prodi'] }}</span><span class="portal-schedule-status">Sedang Berlangsung</span></div>
                                        <div class="portal-course-meta"><span>⌖ {{ $item['room']->name }}</span><span>▣ {{ $item['source'] }}</span>@if($item['lecturer_name'])<span>◉ {{ $item['lecturer_name'] }}</span>@endif</div>
                                    </div>
                                </div>
                                <div class="portal-schedule-side">
                                    <span class="portal-room-tag">{{ $item['room']->code }}</span>
                                    <small class="portal-finish-time">Selesai pukul {{ $item['end']->format('H.i') }}</small>
                                    <span class="portal-live-countdown" data-end="{{ $item['end']->toIso8601String() }}">Menghitung waktu selesai...</span>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="portal-empty"><span>◇</span><strong>Tidak ada kelas yang sedang aktif</strong><p>Jadwal berikutnya dapat dilihat pada daftar lengkap di bawah.</p></div>
                    @endforelse
                </div>
            </article>

            <article class="portal-card portal-schedule-card portal-all-schedule-card">
                <header class="portal-card-header">
                    <div><div class="portal-card-title"><h2>Keseluruhan Jadwal Hari Ini</h2><span>{{ $todayScheduleItems->count() }} Jadwal</span></div><p>Seluruh booking dan jadwal tetap · {{ today()->translatedFormat('l, d F Y') }}</p></div>
                    <a href="{{ route('calendar.weekly') }}">Kalender →</a>
                </header>
                <div class="portal-card-body portal-schedule-list">
                    @forelse($todayScheduleItems as $item)
                        @php
                            $isRunning = $item['start']->lte(now()) && $item['end']->gt(now());
                            $isFinished = $item['end']->lte(now());
                            $status = $isRunning ? 'Sedang Berlangsung' : ($isFinished ? 'Selesai' : 'Akan Datang');
                        @endphp
                        <article class="portal-schedule-item portal-schedule-item-compact has-prodi-theme prodi-{{ $item['theme'] }} {{ $isRunning ? 'is-running' : ($isFinished ? 'is-finished' : 'is-upcoming') }}">
                            <div class="portal-schedule-layout">
                                <div class="portal-schedule-main">
                                    <div class="portal-time-box"><strong>{{ $item['start']->format('H.i') }}</strong><small>s/d</small><strong>{{ $item['end']->format('H.i') }}</strong></div>
                                    <div class="portal-course-info">
                                        <div class="portal-course-title"><h3>{{ $item['course_name'] }}</h3><span class="portal-prodi-badge">{{ $item['prodi'] }}</span><span class="portal-schedule-status">{{ $status }}</span></div>
                                        <div class="portal-course-meta"><span>⌖ {{ $item['room']->name }}</span><span>▣ {{ $item['source'] }}</span>@if($item['lecturer_name'])<span>◉ {{ $item['lecturer_name'] }}</span>@endif</div>
                                    </div>
                                </div>
                                <div class="portal-schedule-side"><span class="portal-room-tag">{{ $item['room']->code }}</span><small class="portal-finish-time">Selesai {{ $item['end']->format('H.i') }}</small></div>
                            </div>
                        </article>
                    @empty
                        <div class="portal-empty"><span>◇</span><strong>Belum ada jadwal hari ini</strong><p>Ruangan masih tersedia dan dapat digunakan.</p></div>
                    @endforelse
                </div>
            </article>
        </div>

        <aside class="portal-side-column">
            <article class="portal-card portal-upcoming-card">
                <header class="portal-card-header"><div><h2>{{ auth()->user()->isAdmin() ? 'Penggunaan Terdekat' : 'Jadwal Saya Berikutnya' }}</h2><p>Jadwal booking yang akan datang</p></div><span class="portal-header-icon">◷</span></header>
                <div class="portal-card-body">
                    @forelse($upcoming as $item)
                        <div class="portal-upcoming-item"><div><span>{{ $item->start_at->translatedFormat('d M') }}</span><strong>{{ $item->start_at->format('H.i') }}</strong></div><div><strong>{{ $item->course_name }}</strong><small>{{ $item->room->code }} · {{ auth()->user()->isAdmin() ? $item->user->name : $item->room->name }}</small></div></div>
                    @empty
                        <div class="portal-empty compact"><span>◇</span><strong>Tidak ada penggunaan mendatang</strong><p>Belum ada booking berikutnya.</p>@if(auth()->user()->canBookRooms())<a href="{{ route('bookings.create') }}">＋ Booking Baru</a>@endif</div>
                    @endforelse
                </div>
            </article>

            <article class="portal-card portal-room-summary">
                <header><h2>Ringkasan Status Ruang</h2><span>{{ $stats['rooms'] }} Total Ruang</span></header>
                <div class="portal-summary-row"><div><span>Ruang Terpakai</span><strong>{{ $usedRoomCount }} ruang</strong></div><div><i class="used" style="width:{{ $stats['rooms'] ? min(100, ($usedRoomCount / $stats['rooms']) * 100) : 0 }}%"></i></div></div>
                <div class="portal-summary-row"><div><span>Ruang Siap/Kosong</span><strong>{{ $availableRoomCount }} ruang</strong></div><div><i class="free" style="width:{{ $stats['rooms'] ? min(100, ($availableRoomCount / $stats['rooms']) * 100) : 0 }}%"></i></div></div>
                <footer><span>Gedung Siti Walidah & Siti Bariah</span><a href="{{ route('rooms.index') }}">Detail Ruangan →</a></footer>
            </article>
        </aside>
    </section>

    <footer class="portal-footer"><span>© 2026 Portal Akademik Terpadu Universitas</span><span>Sistem Penjadwalan Kuliah</span></footer>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const formatDuration = (seconds) => {
        seconds = Math.max(0, Math.floor(seconds));
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return (hours ? hours + 'j ' : '') + String(minutes).padStart(2, '0') + 'm ' + String(secs).padStart(2, '0') + 'd';
    };
    const updateCountdowns = () => {
        const now = Date.now();
        document.querySelectorAll('.portal-live-countdown').forEach((element) => {
            const end = new Date(element.dataset.end).getTime();
            if (now < end) element.textContent = 'Selesai dalam ' + formatDuration((end - now) / 1000);
            else element.textContent = 'Selesai';
        });
    };
    updateCountdowns();
    setInterval(updateCountdowns, 1000);
})();
</script>
@endpush
