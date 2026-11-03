@extends('layouts.app')

@section('title', $priority->exists ? 'Edit Prioritas Jadwal' : 'Tambah Prioritas Jadwal')
@section('header', $priority->exists ? 'Edit Prioritas Jadwal' : 'Tambah Prioritas Jadwal')

@section('content')
<div class="page-head">
    <div>
        <h2>{{ $priority->exists ? 'Edit Prioritas Jadwal' : 'Tambah Prioritas Jadwal' }}</h2>
        <p>Akun yang dipilih mendapat kesempatan pertama untuk membooking ruang sebelum tanggal jadwal dimulai.</p>
    </div>
</div>

<section class="card form-card">
    <div class="card-body">
        <form method="POST" action="{{ $priority->exists ? route('admin.priorities.update', $priority) : route('admin.priorities.store') }}">
            @csrf @if($priority->exists) @method('PUT') @endif
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="priority_user_id">Kelas/Program Studi <span class="required">*</span></label>
                    <select class="form-control" id="priority_user_id" name="user_id" required>
                        <option value="">Pilih akun kelas/prodi</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id', $priority->user_id) == $user->id)>{{ $user->study_program ?: 'Belum ada prodi' }} — {{ $user->username }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="priority_room_id">Ruang Kelas <span class="required">*</span></label>
                    <select class="form-control" id="priority_room_id" name="room_id" required>
                        <option value="">Pilih ruang kelas</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}" @selected(old('room_id', $priority->room_id) == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="priority_date_display">Tanggal Jadwal <span class="required">*</span></label>
                    @include('components.date-input', [
                        'name' => 'schedule_date',
                        'id' => 'priority_date',
                        'value' => old('schedule_date', $priority->schedule_date?->toDateString() ?? today()->addDay()->toDateString()),
                        'min' => today()->addDay()->toDateString(),
                        'required' => true,
                    ])
                    <span class="form-hint">Prioritas otomatis berakhir pada pukul 00.00 di tanggal ini.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Jam Prioritas <span class="required">*</span></label>
                    <div class="time-range-grid">
                        <div>
                            @include('components.time-input', [
                                'name' => 'start_time',
                                'id' => 'priority_start_time',
                                'label' => 'Jam mulai prioritas',
                                'value' => old('start_time', $priority->start_time ? substr($priority->start_time, 0, 5) : '00:00'),
                                'required' => true,
                            ])
                            <span class="form-hint">Mulai</span>
                        </div>
                        <div>
                            @include('components.time-input', [
                                'name' => 'end_time',
                                'id' => 'priority_end_time',
                                'label' => 'Jam selesai prioritas',
                                'value' => old('end_time', $priority->end_time ? substr($priority->end_time, 0, 5) : '23:59'),
                                'required' => true,
                            ])
                            <span class="form-hint">Selesai</span>
                        </div>
                    </div>
                    <span class="form-hint">Gunakan 00.00–23.59 bila prioritas berlaku satu hari penuh.</span>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="priority_notes">Keterangan</label>
                    <input class="form-control" id="priority_notes" name="notes" value="{{ old('notes', $priority->notes) }}" placeholder="Contoh: Jadwal reguler semester 3">
                </div>
                <div class="form-group full">
                    <label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $priority->exists ? $priority->is_active : true))> Prioritas aktif</label>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:9px;border-top:1px solid var(--line);padding-top:18px">
                <a class="btn btn-light" href="{{ route('admin.priorities.index') }}">Batal</a>
                <button class="btn btn-primary" type="submit">{{ $priority->exists ? 'Simpan Perubahan' : 'Tambah Prioritas' }}</button>
            </div>
        </form>
    </div>
</section>
@endsection
