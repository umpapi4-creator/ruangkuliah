@extends('layouts.app')

@section('title', 'Prioritas Jadwal')
@section('header', 'Prioritas Jadwal')

@section('content')
<div class="page-head">
    <div>
        <h2>Prioritas Jadwal Kelas</h2>
        <p>Tetapkan akun kelas/prodi yang mendapat hak pertama untuk memakai ruang dan jam tertentu.</p>
    </div>
    <div class="actions"><a href="{{ route('admin.priorities.create') }}" class="btn btn-primary">＋ Tambah Prioritas</a></div>
</div>

<div class="alert alert-warning">
    ◷ <div>Hak prioritas berlaku sampai pukul <strong>00.00 pada tanggal jadwal</strong>. Jika belum dibooking, slot otomatis terbuka untuk akun lain.</div>
</div>

<section class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tanggal / Jam</th><th>Ruang</th><th>Kelas/Prodi Prioritas</th><th>Batas Prioritas</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($priorities as $priority)
                <tr>
                    <td><strong>{{ $priority->schedule_date->format('d/m/Y') }}</strong><small>{{ $priority->start_time_label }}–{{ $priority->end_time_label }}</small></td>
                    <td><strong>{{ $priority->room->code }}</strong><small>{{ $priority->room->name }}</small></td>
                    <td><strong>{{ $priority->user->username }}</strong><small>{{ $priority->notes ?: ($priority->user->study_program ?: 'Belum ada prodi') }}</small></td>
                    <td><strong>{{ $priority->schedule_date->format('d/m/Y') }} · 00.00</strong><small>Setelah batas ini terbuka untuk akun lain</small></td>
                    <td>
                        @if(! $priority->is_active)
                            <span class="badge badge-gray">● Nonaktif</span>
                        @elseif($priority->schedule_date->isFuture())
                            <span class="badge badge-warning">● Prioritas Aktif</span>
                        @else
                            <span class="badge badge-gray">● Sudah Terbuka</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:6px">
                            @if($priority->schedule_date->isFuture())
                                <a class="btn btn-light btn-sm" href="{{ route('admin.priorities.edit', $priority) }}">Edit</a>
                            @endif
                            <form action="{{ route('admin.priorities.destroy', $priority) }}" method="POST" onsubmit="return confirm('Hapus prioritas jadwal ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><div class="empty-icon">◷</div>Belum ada prioritas jadwal.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('components.pagination', ['paginator' => $priorities])
</section>
@endsection
