@extends('layouts.app')

@section('title', 'Gunakan Ruang Kelas')
@section('header', 'Gunakan Ruang Kelas')

@section('content')
<div class="page-head">
    <div><h2>Gunakan Ruang Kelas</h2><p>Isi jadwal penggunaan. Sistem akan otomatis memeriksa bentrok ruang dan jadwal Anda.</p></div>
    <div class="actions"><a href="{{ route('schedule', ['date' => old('date', $selectedDate)]) }}" class="btn btn-light">▦ Cek Jadwal</a></div>
</div>

<section class="card form-card">
    <div class="card-header"><div><h3>Informasi Perkuliahan</h3><span class="card-subtitle">Kolom bertanda * wajib diisi</span></div></div>
    <div class="card-body">
        <form action="{{ route('bookings.store') }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label" for="room_id">Ruang Kelas <span class="required">*</span></label>
                    <select class="form-control" name="room_id" id="room_id" required>
                        <option value="">Pilih ruang kelas</option>
                        @foreach($rooms as $room)<option value="{{ $room->id }}" @selected(old('room_id', $selectedRoom) == $room->id)>{{ $room->code }} — {{ $room->name }} ({{ $room->capacity }} orang)</option>@endforeach
                    </select>
                    @error('room_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="course_name">Mata Kuliah/Kegiatan <span class="required">*</span></label>
                    <input class="form-control" id="course_name" name="course_name" value="{{ old('course_name') }}" placeholder="Contoh: Klasifikasi Penyakit" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="class_group">Kelas/Program Studi <span class="required">*</span></label>
                    <input class="form-control" id="class_group" name="class_group" value="{{ old('class_group', auth()->user()->study_program) }}" placeholder="Contoh: D4 MIK Semester 3" required>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="lecturer_name">Nama Dosen</label>
                    <select class="form-control" id="lecturer_name" name="lecturer_name">
                        <option value="">Pilih dosen pengampu</option>
                        @foreach($lecturers as $lecturer)
                            <option value="{{ $lecturer->name }}" @selected(old('lecturer_name') === $lecturer->name)>{{ $lecturer->name }}</option>
                        @endforeach
                        <option value="__other__" @selected(old('lecturer_name') === '__other__')>Dosen lainnya</option>
                    </select>
                    @error('lecturer_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group full" id="other_lecturer_group" @if(old('lecturer_name') !== '__other__') hidden @endif>
                    <label class="form-label" for="other_lecturer_name">Nama Dosen Lainnya <span class="required">*</span></label>
                    <input class="form-control" id="other_lecturer_name" name="other_lecturer_name" value="{{ old('other_lecturer_name') }}" maxlength="255" placeholder="Nama dosen pengampu" @required(old('lecturer_name') === '__other__') @disabled(old('lecturer_name') !== '__other__')>
                    @error('other_lecturer_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="usage_date_display">Tanggal <span class="required">*</span></label>
                    @include('components.date-input', [
                        'name' => 'date',
                        'id' => 'usage_date',
                        'value' => old('date', $selectedDate),
                        'min' => $bookingStartDate->toDateString(),
                        'max' => $bookingEndDate->toDateString(),
                        'required' => true,
                    ])
                    <span class="card-subtitle">Booking hanya dapat dibuat untuk hari ini atau besok.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Jam Mulai–Selesai <span class="required">*</span></label>
                    <div class="time-range-grid">
                        <div>
                            @include('components.time-input', [
                                'name' => 'start_time',
                                'id' => 'booking_start_time',
                                'label' => 'Jam mulai',
                                'value' => old('start_time', '08:00'),
                                'required' => true,
                            ])
                            <span class="form-hint">Mulai</span>
                        </div>
                        <div>
                            @include('components.time-input', [
                                'name' => 'end_time',
                                'id' => 'booking_end_time',
                                'label' => 'Jam selesai',
                                'value' => old('end_time', '10:00'),
                                'required' => true,
                            ])
                            <span class="form-hint">Selesai</span>
                        </div>
                    </div>
                    <span class="form-hint">Format 24 jam: 00.00 sampai 23.59.</span>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="purpose">Catatan/Keperluan</label>
                    <textarea class="form-control" id="purpose" name="purpose" placeholder="Catatan tambahan (opsional)">{{ old('purpose') }}</textarea>
                </div>
            </div>
            <div style="display:flex;gap:9px;justify-content:flex-end;border-top:1px solid var(--line);padding-top:18px">
                <a class="btn btn-light" href="{{ route('bookings.index') }}">Batal</a>
                <button class="btn btn-primary" type="submit">✓ Simpan Penggunaan</button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const select = document.getElementById('lecturer_name');
    const group = document.getElementById('other_lecturer_group');
    const input = document.getElementById('other_lecturer_name');
    const update = () => {
        const isOther = select.value === '__other__';
        group.hidden = !isOther;
        input.disabled = !isOther;
        input.required = isOther;
    };
    select.addEventListener('change', update);
    update();
})();
</script>
@endpush
