@extends('layouts.app')

@section('title', $room->exists ? 'Edit Kelas' : 'Tambah Kelas')
@section('header', $room->exists ? 'Edit Kelas' : 'Tambah Kelas')

@section('content')
<div class="page-head"><div><h2>{{ $room->exists ? 'Edit Ruang Kelas' : 'Tambah Ruang Kelas' }}</h2><p>Lengkapi identitas, lokasi, kapasitas, dan fasilitas ruangan.</p></div></div>

<section class="card form-card">
    <div class="card-body">
        <form method="POST" action="{{ $room->exists ? route('admin.rooms.update', $room) : route('admin.rooms.store') }}">
            @csrf @if($room->exists) @method('PUT') @endif
            <div class="form-grid">
                <div class="form-group"><label class="form-label">Kode Ruang <span class="required">*</span></label><input class="form-control" name="code" value="{{ old('code', $room->code) }}" placeholder="R-101" required></div>
                <div class="form-group"><label class="form-label">Nama Ruang <span class="required">*</span></label><input class="form-control" name="name" value="{{ old('name', $room->name) }}" placeholder="Ruang Kuliah 101" required></div>
                <div class="form-group"><label class="form-label">Gedung</label><input class="form-control" name="building" value="{{ old('building', $room->building) }}" placeholder="Gedung A"></div>
                <div class="form-group"><label class="form-label">Lantai</label><input class="form-control" name="floor" value="{{ old('floor', $room->floor) }}" placeholder="Lantai 1"></div>
                <div class="form-group"><label class="form-label">Kapasitas <span class="required">*</span></label><input class="form-control" type="number" min="1" name="capacity" value="{{ old('capacity', $room->capacity ?: 30) }}" required></div>
                <div class="form-group full"><label class="form-label">Fasilitas</label><textarea class="form-control" name="facilities" placeholder="AC, proyektor, papan tulis">{{ old('facilities', $room->facilities) }}</textarea></div>
                <div class="form-group full"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $room->exists ? $room->is_active : true))> Ruang aktif dan dapat digunakan</label></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:9px;border-top:1px solid var(--line);padding-top:18px"><a class="btn btn-light" href="{{ route('admin.rooms.index') }}">Batal</a><button class="btn btn-primary">{{ $room->exists ? 'Simpan Perubahan' : 'Tambah Kelas' }}</button></div>
        </form>
    </div>
</section>
@endsection
