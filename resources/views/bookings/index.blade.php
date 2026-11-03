@extends('layouts.app')

@section('title', 'Ruang Kelas Terpakai')
@section('header', 'Ruang Kelas Terpakai')

@section('content')
<div class="page-head">
    <div><h2>Ruang Kelas Terpakai</h2><p>{{ auth()->user()->isAdmin() ? 'Pantau seluruh penggunaan ruang kelas yang masih berlangsung atau akan datang.' : 'Kelola ruang kelas yang sedang atau akan Anda gunakan.' }}</p></div>
    @if(auth()->user()->canBookRooms())<div class="actions"><a href="{{ route('bookings.create') }}" class="btn btn-primary">＋ Gunakan Ruang</a></div>@endif
</div>

<section class="card">
    <form class="filter-bar" method="GET">
        <div class="form-group">
            <label class="form-label">Tanggal</label>
            @include('components.date-input', [
                'name' => 'date',
                'id' => 'usage_filter_date',
                'value' => request('date'),
            ])
        </div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn btn-light" href="{{ route('bookings.index') }}">Reset</a>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Jadwal</th><th>Mata Kuliah / Kelas</th><th>Ruang</th>@if(auth()->user()->isAdmin())<th>Pemesan</th>@endif<th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($bookings as $booking)
                <tr>
                    <td><strong>{{ $booking->start_at->translatedFormat('d M Y') }}</strong><small>{{ $booking->start_at->format('H:i') }}–{{ $booking->end_at->format('H:i') }}</small></td>
                    <td><strong>{{ $booking->course_name }}</strong><small>{{ $booking->class_group }}{{ $booking->lecturer_name ? ' · '.$booking->lecturer_name : '' }}</small></td>
                    <td><strong>{{ $booking->room->code }}</strong><small>{{ $booking->room->name }}</small></td>
                    @if(auth()->user()->isAdmin())<td><strong>{{ $booking->user->username }}</strong><small>{{ $booking->user->study_program ?: ($booking->user->nim ?: $booking->user->email) }}</small></td>@endif
                    <td>
                        @if($booking->start_at->lte(now()))<span class="badge badge-danger">● Sedang Terpakai</span>@else<span class="badge badge-success">● Terjadwal</span>@endif
                    </td>
                    <td>
                        @if($booking->status === 'active')
                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Batalkan penggunaan ruang ini? Jadwal ruang akan tersedia kembali.')">@csrf @method('PATCH')<button class="btn btn-danger btn-sm" type="submit">Batalkan</button></form>
                        @else<span class="card-subtitle">—</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}"><div class="empty"><div class="empty-icon">▦</div>Belum ada ruang kelas yang terpakai.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('components.pagination', ['paginator' => $bookings])
</section>
@endsection
