<?php

namespace App\Http\Controllers;

use App\Models\CourseSchedule;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\ScheduleAvailability;
use App\Services\SimpleExcel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportScheduleController extends Controller
{
    private const HEADER = ['prodi', 'mata kuliah', 'dosen', 'ruangan', 'hari', 'jam mulai', 'jam selesai', 'tanggal mulai', 'tanggal berakhir'];
    private const DAYS = ['senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'sabtu' => 6];

    public function index()
    {
        $schedules = CourseSchedule::with('room')->orderByDesc('created_at')->paginate(15);
        return view('admin.imports.index', compact('schedules'));
    }

    public function lecturerIndex(Request $request)
    {
        $schedules = CourseSchedule::with('room')
            ->where(function ($query) use ($request) {
                $query->where('created_by', $request->user()->id)
                    ->orWhere('lecturer_name', $request->user()->name);
            })
            ->orderBy('start_date')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->paginate(15);

        $teachingBookings = Booking::with(['room', 'user'])
            ->active()
            ->where('lecturer_name', $request->user()->name)
            ->where('end_at', '>', now())
            ->orderBy('start_at')
            ->paginate(15, ['*'], 'bookings_page');

        return view('lecturer.schedules.index', compact('schedules', 'teachingBookings'));
    }

    public function create()
    {
        $isLecturer = auth()->user()->isLecturer();

        return view('admin.imports.create', [
            'prodis' => User::where('role', User::ROLE_STUDENT)
                ->where('is_active', true)
                ->orderForDisplay()
                ->get(),
            'rooms' => Room::where('is_active', true)
                ->orderForDisplay()
                ->get(),
            'days' => array_flip(self::DAYS),
            'isLecturer' => $isLecturer,
            'storeRoute' => $isLecturer ? route('lecturer.schedules.store') : route('admin.schedules.store'),
            'cancelRoute' => $isLecturer ? route('lecturer.schedules.index') : route('admin.imports.index'),
        ]);
    }

    public function manualStore(Request $request)
    {
        if ($request->user()->isLecturer()) {
            $request->merge(['lecturer_name' => $request->user()->name]);
        }

        $data = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', User::ROLE_STUDENT)
                    ->where('is_active', true)),
            ],
            'room_id' => [
                'required',
                Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'course_name' => ['required', 'string', 'max:255'],
            'lecturer_name' => ['required', 'string', 'max:255'],
            'weekday' => ['required', 'integer', Rule::in(array_values(self::DAYS))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'end_date.after_or_equal' => 'Tanggal berakhir tidak boleh mendahului tanggal mulai.',
        ]);

        $from = Carbon::createFromFormat('!Y-m-d', $data['start_date']);
        $until = Carbon::createFromFormat('!Y-m-d', $data['end_date']);
        if ($from->diffInDays($until) > 366) {
            throw ValidationException::withMessages([
                'end_date' => 'Rentang jadwal maksimal 366 hari.',
            ]);
        }
        if (! ScheduleAvailability::firstWeekday($from, $until, (int) $data['weekday'])) {
            throw ValidationException::withMessages([
                'weekday' => 'Hari yang dipilih tidak terdapat dalam rentang tanggal.',
            ]);
        }

        $schedule = DB::transaction(function () use ($data, $request) {
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            $prodi = User::whereKey($data['user_id'])
                ->where('role', User::ROLE_STUDENT)
                ->where('is_active', true)
                ->firstOrFail();
            $item = [
                'prodi' => $prodi->study_program ?: $prodi->username,
                'course_name' => $data['course_name'],
                'lecturer_name' => $data['lecturer_name'],
                'weekday' => (int) $data['weekday'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ];

            $reason = ScheduleAvailability::importConflict($item, $room);
            if ($reason) {
                throw ValidationException::withMessages(['start_time' => $reason]);
            }

            $item['room_id'] = $room->id;
            $item['created_by'] = $request->user()->id;
            $schedule = CourseSchedule::create($item);
            AdminAudit::record('schedule.created', $schedule, $schedule->only([
                'prodi', 'course_name', 'lecturer_name', 'room_id', 'weekday',
                'start_time', 'end_time', 'start_date', 'end_date',
            ]));

            return $schedule;
        }, 3);

        $redirectRoute = $request->user()->isLecturer()
            ? 'lecturer.schedules.index'
            : 'admin.imports.index';

        return redirect()->route($redirectRoute)
            ->with('success', 'Jadwal '.$schedule->course_name.' berhasil ditambahkan.');
    }

    public function template()
    {
        $prodis = User::STUDY_PROGRAMS;
        $rooms = Room::where('is_active', true)
            ->orderForDisplay()
            ->pluck('code')
            ->all();

        return SimpleExcel::downloadScheduleTemplate(
            self::HEADER,
            $prodis,
            $rooms,
            'template_import_jadwal.xlsx'
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120']]);
        try {
            $rows = SimpleExcel::read($data['file']->getRealPath(), strtolower($data['file']->getClientOriginalExtension()));
        } catch (\Throwable $error) {
            throw ValidationException::withMessages(['file' => $error->getMessage()]);
        }
        if (count($rows) < 2) throw ValidationException::withMessages(['file' => 'File harus berisi judul kolom dan minimal satu data.']);
        if (count($rows) > 501) throw ValidationException::withMessages(['file' => 'Maksimal 500 baris jadwal per impor.']);
        $header = array_map(fn ($v) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $v))), array_slice($rows[0], 0, 9));
        if ($header !== self::HEADER) {
            throw ValidationException::withMessages(['file' => 'Urutan 9 header harus: '.implode(', ', self::HEADER).'. Unduh template agar format sesuai.']);
        }

        $rooms = Room::all();
        $success = 0;
        $errors = [];
        foreach (array_slice($rows, 1) as $offset => $row) {
            if (! collect($row)->contains(fn ($value) => trim((string) $value) !== '')) continue;
            $line = $offset + 2;
            try {
                $parsed = $this->parseRow($row);
                $room = $rooms->first(fn (Room $r) => mb_strtolower($r->code) === mb_strtolower($parsed['room_lookup']) || mb_strtolower($r->name) === mb_strtolower($parsed['room_lookup']));
                if (! $room) throw new \RuntimeException('Kode/nama ruangan tidak ditemukan: '.$parsed['room_lookup']);
                unset($parsed['room_lookup']);
                // Setiap baris dikunci dan disimpan atomik. Baris yang ditolak tidak membatalkan baris valid.
                DB::transaction(function () use ($room, $parsed, $request) {
                    $lockedRoom = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
                    $parsed['room_id'] = $lockedRoom->id;
                    $reason = ScheduleAvailability::importConflict($parsed, $lockedRoom);
                    if ($reason) throw new \RuntimeException($reason);
                    $parsed['created_by'] = $request->user()->id;
                    CourseSchedule::create($parsed);
                }, 3);
                $success++;
            } catch (\Throwable $error) {
                $errors[] = 'Baris '.$line.': '.$error->getMessage();
            }
        }
        AdminAudit::record('schedule.imported', null, [
            'file' => basename($request->file('file')->getClientOriginalName()),
            'accepted' => $success, 'rejected' => count($errors),
        ]);
        return redirect()->route('admin.imports.index')->with('success', $success.' jadwal berhasil diimpor; '.count($errors).' ditolak karena data tidak valid/bentrok.')
            ->with('import_errors', array_slice($errors, 0, 80));
    }

    private function parseRow(array $row): array
    {
        $row = array_pad(array_map(fn ($v) => trim((string) $v), $row), 9, '');
        foreach (array_slice($row, 0, 9) as $index => $value) {
            if ($value === '') throw new \RuntimeException('Kolom '.self::HEADER[$index].' wajib diisi.');
        }
        foreach ([0, 1, 2, 3] as $index) {
            if (mb_strlen($row[$index]) > ($index === 0 ? 150 : 255)) throw new \RuntimeException('Teks kolom '.self::HEADER[$index].' terlalu panjang.');
        }
        $day = mb_strtolower(str_replace(["'", '’', ' '], '', $row[4]));
        if (! isset(self::DAYS[$day])) throw new \RuntimeException('Hari harus Senin sampai Sabtu.');
        $start = $this->time($row[5]);
        $end = $this->time($row[6]);
        if ($end <= $start) throw new \RuntimeException('Jam selesai harus setelah jam mulai.');
        $from = $this->date($row[7]);
        $until = $this->date($row[8]);
        if ($from->gt($until)) throw new \RuntimeException('Tanggal berakhir mendahului tanggal mulai.');
        if ($from->diffInDays($until) > 366) throw new \RuntimeException('Rentang jadwal maksimal 366 hari per baris.');
        if (! ScheduleAvailability::firstWeekday($from, $until, self::DAYS[$day])) throw new \RuntimeException('Hari tidak ada di dalam rentang tanggal tersebut.');
        return [
            'prodi' => $row[0], 'course_name' => $row[1], 'lecturer_name' => $row[2],
            'room_lookup' => $row[3], 'weekday' => self::DAYS[$day], 'start_time' => $start,
            'end_time' => $end, 'start_date' => $from->toDateString(), 'end_date' => $until->toDateString(),
        ];
    }

    private function time(string $value): string
    {
        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            $minutes = (int) round(((float) $value) * 1440);
            if ($minutes >= 1440) throw new \RuntimeException('Format jam Excel tidak valid.');
            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }
        $value = str_replace('.', ':', trim($value));
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::00)?$/', $value, $match)) return sprintf('%02d:%02d', (int) $match[1], (int) $match[2]);
        throw new \RuntimeException('Format jam gunakan HH:MM, misalnya 08:00.');
    }

    private function date(string $value): Carbon
    {
        try {
            if (is_numeric($value) && (float) $value > 1000 && (float) $value < 200000) {
                return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->startOfDay();
            }
            $format = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? 'Y-m-d' : 'd/m/Y';
            $date = Carbon::createFromFormat('!'.$format, $value);
            if (! $date || $date->format($format) !== $value) throw new \RuntimeException('Tanggal tidak valid.');
            return $date->startOfDay();
        } catch (\Throwable) {
            throw new \RuntimeException('Format tanggal gunakan YYYY-MM-DD atau DD/MM/YYYY.');
        }
    }

    public function destroy(CourseSchedule $schedule)
    {
        $details = $schedule->only(['prodi', 'course_name', 'room_id', 'weekday', 'start_date', 'end_date']);
        AdminAudit::record('schedule.deleted', $schedule, $details);
        $schedule->delete();
        return back()->with('success', 'Jadwal tetap berhasil dihapus.');
    }

    public function lecturerDestroy(Request $request, CourseSchedule $schedule)
    {
        abort_unless($schedule->created_by === $request->user()->id, 403);

        $schedule->delete();

        return back()->with('success', 'Jadwal berhasil dihapus.');
    }
}
