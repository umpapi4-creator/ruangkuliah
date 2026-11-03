@extends('layouts.app')

@section('title', 'Daftar Kelas')
@section('header', 'Daftar Kelas')

@section('content')
<div class="page-head">
    <div><h2>Ruang Kelas Tersedia</h2><p>Pilih ruangan yang sesuai dengan kapasitas dan fasilitas perkuliahan.</p></div>
    @if(auth()->user()->canBookRooms())<div class="actions"><a href="{{ route('bookings.create') }}" class="btn btn-primary">＋ Gunakan Ruang Kelas</a></div>@endif
</div>

<div class="room-grid">
    @forelse($rooms as $room)
        <article class="room-card {{ $room->current_blocks_count > 0 ? 'room-card-blocked' : (($room->active_bookings_count > 0 || $room->current_classes_count > 0) ? 'room-card-booked' : '') }}">
            <div class="room-card-status-row">
                <span class="room-code">{{ $room->code }}</span>
                @if($room->current_blocks_count > 0)
                    <span class="room-usage-status room-usage-booked">⊘ Sedang Diblokir</span>
                @elseif($room->active_bookings_count > 0 || $room->current_classes_count > 0)
                    <span class="room-usage-status room-usage-booked">● Ruang Kelas Terpakai</span>
                @else
                    <span class="room-usage-status room-usage-free">● Tersedia</span>
                @endif
            </div>
            <h3>{{ $room->name }}</h3>
            <div class="room-info">
                <span>⌖ {{ $room->building ?: 'Gedung belum diisi' }}{{ $room->floor ? ' · '.$room->floor : '' }}</span>
                <span>♙ Kapasitas {{ $room->capacity }} orang</span>
                <span>⚙ {{ $room->facilities ?: 'Fasilitas belum diisi' }}</span>
            </div>
            <div class="room-actions">
                <a href="{{ route('schedule', ['room_id' => $room->id]) }}" class="btn btn-light btn-sm">Lihat Jadwal</a>
                @if(auth()->user()->canBookRooms())<a href="{{ route('bookings.create', ['room_id' => $room->id]) }}" class="btn btn-primary btn-sm">Gunakan Ruang</a>@endif
            </div>
        </article>
    @empty
        <div class="card empty" style="grid-column:1/-1">Belum ada ruang kelas aktif.</div>
    @endforelse
</div>
@endsection
