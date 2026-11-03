@extends('layouts.app')

@section('title', 'Jadwal Ruang')
@section('header', 'Jadwal Ruang')

@section('content')
<div class="page-head">
    <div><h2>Jadwal Penggunaan Ruang</h2><p>Lihat ruang yang sudah terpakai sebelum membuat jadwal penggunaan.</p></div>
    @if(auth()->user()->canBookRooms())
        <div class="actions">
            @if($selectedDate->betweenIncluded(today(), today()->addDay()))
                <a href="{{ route('bookings.create', ['date' => $selectedDate->toDateString()]) }}" class="btn btn-primary">＋ Gunakan pada Tanggal Ini</a>
            @else
                <button type="button" class="btn btn-light" disabled title="Booking hanya tersedia untuk hari ini atau besok">Booking Maksimal H+1</button>
            @endif
        </div>
    @endif
</div>

<section class="card" style="margin-bottom:18px">
    <form class="filter-bar" method="GET" action="{{ route('schedule') }}">
        <div class="form-group">
            <label class="form-label">Tanggal</label>
            @include('components.date-input', [
                'name' => 'date',
                'id' => 'schedule_date',
                'value' => $selectedDate->toDateString(),
            ])
        </div>
        <div class="form-group">
            <label class="form-label">Ruang</label>
            <select class="form-control" name="room_id">
                <option value="">Semua ruang</option>
                @foreach($rooms as $room)<option value="{{ $room->id }}" @selected(request('room_id') == $room->id)>{{ $room->code }} — {{ $room->name }}</option>@endforeach
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
        <a class="btn btn-light" href="{{ route('schedule') }}">Hari Ini</a>
    </form>
    <div style="padding:0 0 4px"><a href="{{ route('calendar.weekly', ['week' => $selectedDate->toDateString(), 'room_id' => request('room_id')]) }}" class="btn btn-light btn-sm">▥ Lihat Kalender Senin–Sabtu</a></div>
</section>

<div class="schedule-grid">
    @foreach($rooms->when(request('room_id'), fn($items) => $items->where('id', request('room_id'))) as $room)
        @php
            $roomBookings = $bookings->get($room->id, collect());
            $roomPriorities = $priorities->get($room->id, collect());
            $roomRegular = $regularSchedules->get($room->id, collect());
            $roomBlocked = $roomBlocks->get($room->id, collect());
        @endphp
        <section class="schedule-room">
            <header class="schedule-room-head"><h3>{{ $room->name }}</h3><span class="badge badge-blue">{{ $room->code }}</span></header>
            <div class="schedule-room-body">
                @foreach($roomBlocked as $block)
                    <div class="schedule-entry schedule-entry-blocked"><strong>⊘ DIBLOKIR · {{ $block->reason }} · {{ max($block->start_at, $selectedDate->copy()->startOfDay())->format('H:i') }}–{{ min($block->end_at, $selectedDate->copy()->endOfDay())->format('H:i') }}</strong><span>{{ $block->notes ?: 'Tidak menerima booking pada jam ini.' }}</span></div>
                @endforeach
                @foreach($roomRegular as $regular)
                    <div class="schedule-entry schedule-entry-regular"><strong>{{ substr($regular->start_time, 0, 5) }}–{{ substr($regular->end_time, 0, 5) }} · {{ $regular->course_name }}</strong><span>Jadwal tetap · {{ $regular->prodi }} · {{ $regular->lecturer_name }}</span></div>
                @endforeach
                @foreach($roomPriorities as $priority)
                    <div class="schedule-entry schedule-entry-priority">
                        <strong>{{ $priority->start_time_label }}–{{ $priority->end_time_label }} · Prioritas {{ $priority->user->username }}</strong>
                        <span>Jika belum dipakai, slot terbuka untuk akun lain pada {{ $priority->schedule_date->format('d/m/Y') }} pukul 00.00.</span>
                    </div>
                @endforeach
                @foreach($roomBookings as $booking)
                    <div class="schedule-entry">
                        <strong>{{ $booking->start_at->format('H:i') }}–{{ $booking->end_at->format('H:i') }} · {{ $booking->course_name }}</strong>
                        <span>{{ $booking->user->study_program ?: $booking->class_group }} · digunakan oleh {{ $booking->user->username }}</span>
                    </div>
                @endforeach
                @if($roomPriorities->isEmpty() && $roomBookings->isEmpty() && $roomRegular->isEmpty() && $roomBlocked->isEmpty())
                    <div class="empty" style="padding:14px">✓ Belum digunakan — tersedia</div>
                @endif
            </div>
        </section>
    @endforeach
</div>

<section class="card schedule-history">
    <div class="card-header">
        <div>
            <h3>Riwayat Kelas</h3>
            <span class="card-subtitle">Kelas yang telah selesai atau dibatalkan pada {{ $selectedDate->format('d/m/Y') }}.</span>
        </div>
        <span class="badge badge-gray">{{ $history->count() }} riwayat</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Jam</th><th>Ruang</th><th>Mata Kuliah / Kelas</th><th>Digunakan Oleh</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($history as $booking)
                <tr>
                    <td><strong>{{ $booking->start_at->format('H.i') }}–{{ $booking->end_at->format('H.i') }}</strong><small>{{ $booking->start_at->format('d/m/Y') }}</small></td>
                    <td><strong>{{ $booking->room->code }}</strong><small>{{ $booking->room->name }}</small></td>
                    <td><strong>{{ $booking->course_name }}</strong><small>{{ $booking->class_group }}{{ $booking->lecturer_name ? ' · '.$booking->lecturer_name : '' }}</small></td>
                    <td><strong>{{ $booking->user->username }}</strong><small>{{ $booking->user->study_program ?: $booking->user->email }}</small></td>
                    <td>
                        @if($booking->status === \App\Models\Booking::STATUS_CANCELLED)
                            <span class="badge badge-danger">● Dibatalkan</span>
                        @else
                            <span class="badge badge-gray">✓ Selesai</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><div class="empty-icon">↺</div>Belum ada riwayat kelas pada tanggal dan ruang yang dipilih.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
