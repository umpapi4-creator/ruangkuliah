@extends('layouts.app')

@section('title', 'Jadwal Saya')
@section('header', 'Jadwal Saya')

@section('content')
<div class="page-head">
    <div>
        <h2>Jadwal Kuliah Saya</h2>
        <p>Lihat jadwal mengajar Anda dari jadwal tetap dan booking ruang mahasiswa. Jadwal tetap berulang setiap minggu selama periode yang ditentukan.</p>
    </div>
    <div class="actions"><a href="{{ route('lecturer.schedules.create') }}" class="btn btn-primary">+ Tambah Jadwal</a></div>
</div>

<section class="card">
    <div class="card-header"><h3>Daftar Jadwal Saya</h3><span class="badge badge-blue">{{ $schedules->total() }} jadwal</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mata Kuliah / Prodi</th><th>Ruangan</th><th>Hari, Jam</th><th>Periode</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($schedules as $schedule)
                <tr>
                    <td><strong>{{ $schedule->course_name }}</strong><small>{{ $schedule->prodi }}</small></td>
                    <td><strong>{{ $schedule->room->code }}</strong><small>{{ $schedule->room->name }}</small></td>
                    <td><strong>{{ [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu'][$schedule->weekday] }}</strong><small>{{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }}</small></td>
                    <td>{{ $schedule->start_date->format('d/m/Y') }}<small>s.d. {{ $schedule->end_date->format('d/m/Y') }}</small></td>
                    <td>@if($schedule->created_by === auth()->id())<form method="POST" action="{{ route('lecturer.schedules.destroy', $schedule) }}" onsubmit="return confirm('Hapus jadwal ini? Slot ruang akan kembali tersedia.');">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" type="submit">Hapus</button></form>@else<span class="card-subtitle">Ditambahkan admin</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty">Belum ada jadwal tetap untuk Anda.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('components.pagination', ['paginator' => $schedules])
</section>

<section class="card">
    <div class="card-header"><h3>Jadwal Mengajar dari Booking Ruang</h3><span class="badge badge-blue">{{ $teachingBookings->total() }} jadwal</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mata Kuliah / Kelas</th><th>Ruangan</th><th>Hari, Tanggal / Jam</th><th>Pemesan</th></tr></thead>
            <tbody>
            @forelse($teachingBookings as $booking)
                <tr>
                    <td><strong>{{ $booking->course_name }}</strong><small>{{ $booking->class_group }}</small></td>
                    <td><strong>{{ $booking->room->code }}</strong><small>{{ $booking->room->name }}</small></td>
                    <td><strong>{{ $booking->start_at->translatedFormat('l, d/m/Y') }}</strong><small>{{ $booking->start_at->format('H:i') }}–{{ $booking->end_at->format('H:i') }}</small></td>
                    <td><strong>{{ $booking->user->name }}</strong><small>{{ $booking->user->study_program }}</small></td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty">Belum ada booking ruang yang mencantumkan Anda sebagai dosen pengampu.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('components.pagination', ['paginator' => $teachingBookings])
</section>
@endsection
