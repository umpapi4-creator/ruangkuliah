@extends('layouts.app')

@section('title', 'Kelola Kelas')
@section('header', 'Kelola Kelas')

@section('content')
<div class="page-head">
    <div><h2>Kelola Ruang Kelas</h2><p>Tambah, ubah, nonaktifkan, atau hapus ruang yang belum pernah digunakan.</p></div>
    <div class="actions"><a href="{{ route('admin.rooms.create') }}" class="btn btn-primary">＋ Tambah Kelas</a></div>
</div>

<section class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kode / Nama</th><th>Lokasi</th><th>Kapasitas</th><th>Jadwal Mendatang</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($rooms as $room)
                <tr>
                    <td><strong>{{ $room->code }} · {{ $room->name }}</strong><small>{{ $room->facilities ?: 'Fasilitas belum diisi' }}</small></td>
                    <td><strong>{{ $room->building ?: '—' }}</strong><small>{{ $room->floor ?: 'Lantai belum diisi' }}</small></td>
                    <td>{{ $room->capacity }} orang</td>
                    <td><span class="badge badge-blue">{{ $room->bookings_count }} penggunaan</span></td>
                    <td>@if($room->is_active)<span class="badge badge-success">● Aktif</span>@else<span class="badge badge-danger">● Nonaktif</span>@endif</td>
                    <td><div style="display:flex;gap:6px">
                        <a class="btn btn-light btn-sm" href="{{ route('admin.rooms.edit', $room) }}">Edit</a>
                        <form action="{{ route('admin.rooms.destroy', $room) }}" method="POST" onsubmit="return confirm('Hapus ruang ini secara permanen?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Hapus</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">Belum ada ruang kelas.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('components.pagination', ['paginator' => $rooms])
</section>
@endsection
