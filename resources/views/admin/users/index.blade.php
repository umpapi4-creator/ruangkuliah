@extends('layouts.app')

@section('title', 'Kelola Pengguna')
@section('header', 'Kelola Pengguna')

@section('content')
<div class="page-head">
    <div><h2>Akun Mahasiswa dan Dosen</h2><p>Kelola role dan akun yang dapat masuk serta menggunakan ruang kelas.</p></div>
    <div class="actions"><a href="{{ route('admin.users.create') }}" class="btn btn-primary">＋ Tambah Pengguna</a></div>
</div>

<section class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Username</th><th>Role</th><th>Program Studi</th><th>NIM / NIDN</th><th>Email</th><th>Total Penggunaan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><strong>{{ $user->username }}</strong></td>
                    <td><span class="badge {{ $user->isLecturer() ? 'badge-warning' : 'badge-blue' }}">{{ $user->role_label }}</span></td>
                    <td>{{ $user->study_program ?: 'Belum ditentukan' }}</td>
                    <td>{{ $user->nim }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="badge badge-blue">{{ $user->bookings_count }} penggunaan</span></td>
                    <td>@if($user->is_active)<span class="badge badge-success">● Aktif</span>@else<span class="badge badge-danger">● Nonaktif</span>@endif</td>
                    <td><a class="btn btn-light btn-sm" href="{{ route('admin.users.edit', $user) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty">Belum ada akun mahasiswa atau dosen.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('components.pagination', ['paginator' => $users])
</section>
@endsection
