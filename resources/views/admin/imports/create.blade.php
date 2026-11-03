@extends('layouts.app')

@section('title', 'Tambah Jadwal Tetap')
@section('header', 'Tambah Jadwal Tetap')

@section('content')
<div class="page-head">
    <div>
        <h2>Tambah Jadwal Kuliah Langsung</h2>
        <p>Jadwal akan berulang setiap minggu pada hari dan periode yang ditentukan.</p>
    </div>
</div>

<section class="card form-card">
    <div class="card-body">
        <form method="POST" action="{{ $storeRoute }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="schedule_user_id">Program Studi <span class="required">*</span></label>
                    <select class="form-control" id="schedule_user_id" name="user_id" required>
                        <option value="">Pilih program studi</option>
                        @foreach($prodis as $prodi)
                            <option value="{{ $prodi->id }}" @selected(old('user_id') == $prodi->id)>{{ $prodi->study_program ?: 'Belum ada prodi' }} — {{ $prodi->username }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="schedule_room_id">Ruangan <span class="required">*</span></label>
                    <select class="form-control" id="schedule_room_id" name="room_id" required>
                        <option value="">Pilih ruangan</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="schedule_course_name">Mata Kuliah <span class="required">*</span></label>
                    <input class="form-control" id="schedule_course_name" name="course_name" value="{{ old('course_name') }}" maxlength="255" placeholder="Contoh: Pemrograman Web" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="schedule_lecturer_name">Dosen Pengampu <span class="required">*</span></label>
                    <input class="form-control" id="schedule_lecturer_name" name="lecturer_name" value="{{ old('lecturer_name', $isLecturer ? auth()->user()->name : '') }}" maxlength="255" placeholder="Nama dosen" @readonly($isLecturer) required>
                    @if($isLecturer)<span class="form-hint">Nama dosen otomatis menggunakan akun Anda.</span>@endif
                </div>
                <div class="form-group">
                    <label class="form-label" for="schedule_weekday">Hari <span class="required">*</span></label>
                    <select class="form-control" id="schedule_weekday" name="weekday" required>
                        <option value="">Pilih hari</option>
                        @foreach($days as $number => $day)
                            <option value="{{ $number }}" @selected((string) old('weekday') === (string) $number)>{{ ucfirst($day) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Jam Kuliah <span class="required">*</span></label>
                    <div class="time-range-grid">
                        <div>
                            @include('components.time-input', [
                                'name' => 'start_time',
                                'id' => 'schedule_start_time',
                                'label' => 'Jam mulai kuliah',
                                'value' => old('start_time', '08:00'),
                                'required' => true,
                            ])
                            <span class="form-hint">Mulai</span>
                        </div>
                        <div>
                            @include('components.time-input', [
                                'name' => 'end_time',
                                'id' => 'schedule_end_time',
                                'label' => 'Jam selesai kuliah',
                                'value' => old('end_time', '10:00'),
                                'required' => true,
                            ])
                            <span class="form-hint">Selesai</span>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="schedule_start_date_display">Tanggal Mulai <span class="required">*</span></label>
                    @include('components.date-input', [
                        'name' => 'start_date',
                        'id' => 'schedule_start_date',
                        'value' => old('start_date', today()->toDateString()),
                        'required' => true,
                    ])
                </div>
                <div class="form-group">
                    <label class="form-label" for="schedule_end_date_display">Tanggal Berakhir <span class="required">*</span></label>
                    @include('components.date-input', [
                        'name' => 'end_date',
                        'id' => 'schedule_end_date',
                        'value' => old('end_date', today()->addMonths(4)->toDateString()),
                        'required' => true,
                    ])
                    <span class="form-hint">Rentang maksimal 366 hari.</span>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:9px;border-top:1px solid var(--line);padding-top:18px">
                <a class="btn btn-light" href="{{ $cancelRoute }}">Batal</a>
                <button class="btn btn-primary" type="submit">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</section>
@endsection
