@extends('layouts.app')

@section('title', $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna')
@section('header', $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna')

@section('content')
<div class="page-head"><div><h2>{{ $user->exists ? 'Edit Akun Pengguna' : 'Tambah Akun Pengguna' }}</h2><p>Buat akun mahasiswa atau dosen yang dapat login dan menggunakan ruang kelas.</p></div></div>

<section class="card form-card">
    <div class="card-body">
        <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf @if($user->exists) @method('PUT') @endif
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label" for="user_role">Role Pengguna <span class="required">*</span></label>
                    <select class="form-control" id="user_role" name="role" required>
                        <option value="">Pilih role pengguna</option>
                        <option value="student" @selected(old('role', $user->role) === 'student')>Mahasiswa</option>
                        <option value="lecturer" @selected(old('role', $user->role) === 'lecturer')>Dosen</option>
                    </select>
                </div>
                <div class="form-group full"><label class="form-label">Username <span class="required">*</span></label><input class="form-control" name="username" value="{{ old('username', $user->username) }}" placeholder="Masukkan username atau nama pengguna" required></div>
                <div class="form-group full">
                    <label class="form-label" for="user_study_program">Program Studi <span class="required">*</span></label>
                    <select class="form-control" id="user_study_program" name="study_program" required>
                        <option value="">Pilih program studi akun</option>
                        @foreach($studyPrograms as $studyProgram)
                            <option value="{{ $studyProgram }}" @selected(old('study_program', $user->study_program) === $studyProgram)>{{ $studyProgram }}</option>
                        @endforeach
                    </select>
                    <div class="form-hint">Program studi menjadi identitas akun dan menentukan warna jadwal.</div>
                </div>
                <div class="form-group"><label class="form-label">NIM / NIDN / Nomor Identitas <span class="required">*</span></label><input class="form-control" name="nim" value="{{ old('nim', $user->nim) }}" placeholder="Isi NIM mahasiswa atau NIDN dosen" required></div>
                <div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                <div class="form-group full"><label class="form-label">Password {{ $user->exists ? '' : '*' }}</label><input class="form-control" type="password" name="password" {{ $user->exists ? '' : 'required' }}><div class="form-hint">{{ $user->exists ? 'Kosongkan jika password tidak ingin diubah.' : 'Masukkan password yang ingin digunakan.' }}</div></div>
                <div class="form-group full"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->exists ? $user->is_active : true))> Akun aktif dan dapat login</label></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:9px;border-top:1px solid var(--line);padding-top:18px"><a class="btn btn-light" href="{{ route('admin.users.index') }}">Batal</a><button class="btn btn-primary">Simpan Akun</button></div>
        </form>
    </div>
</section>
@endsection
