<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PrioritySchedule;
use App\Models\Room;
use App\Models\CourseSchedule;
use App\Models\RoomBlock;
use App\Models\User;
use App\Services\ScheduleAvailability;
use App\Services\AdminAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function schedule(Request $request)
    {
        $date = $request->input('date');

        // Jika jadwal dibuka dari tombol "Lihat Jadwal" suatu ruang tanpa memilih
        // tanggal, langsung arahkan ke tanggal penggunaan terdekat ruang tersebut.
        if (! $date && $request->filled('room_id')) {
            $nextBooking = Booking::active()
                ->where('room_id', $request->integer('room_id'))
                ->where('end_at', '>', now())
                ->orderBy('start_at')
                ->first();

            $date = $nextBooking?->start_at->toDateString();
        }

        $date ??= today()->toDateString();
        try {
            $selectedDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            $selectedDate = today();
        }

        $rooms = Room::where('is_active', true)->orderForDisplay()->get();
        $bookings = Booking::with(['room', 'user'])
            ->active()
            ->where('end_at', '>', now())
            ->whereDate('start_at', $selectedDate)
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->orderBy('start_at')
            ->get()
            ->groupBy('room_id');

        $priorities = PrioritySchedule::with(['room', 'user'])
            ->active()
            ->whereDate('schedule_date', $selectedDate)
            ->whereDate('schedule_date', '>', today()->toDateString())
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->orderBy('start_time')
            ->get()
            ->groupBy('room_id');

        $history = Booking::with(['room', 'user'])
            ->whereDate('start_at', $selectedDate)
            ->where(function ($query) {
                $query->where('status', Booking::STATUS_CANCELLED)
                    ->orWhere('end_at', '<=', now());
            })
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->orderByDesc('start_at')
            ->get();

        // Tambahan informasi jadwal kuliah tetap dan blokir, tanpa mengubah jadwal booking lama.
        $regularSchedules = CourseSchedule::where('weekday', $selectedDate->dayOfWeekIso)
            ->whereDate('start_date', '<=', $selectedDate)->whereDate('end_date', '>=', $selectedDate)
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->orderBy('start_time')->get()->groupBy('room_id');
        $dayStart = $selectedDate->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();
        $roomBlocks = RoomBlock::active()->where('start_at', '<', $dayEnd)->where('end_at', '>', $dayStart)
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->orderBy('start_at')->get()->groupBy('room_id');

        return view('bookings.schedule', compact('rooms', 'bookings', 'priorities', 'history', 'selectedDate', 'regularSchedules', 'roomBlocks'));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        // Hanya tampilkan ruang yang sedang atau akan digunakan. Setelah jam selesai,
        // data otomatis hilang dari halaman tanpa perlu dihapus manual.
        $query = Booking::with(['room', 'user', 'cancelledBy'])
            ->active()
            ->where('end_at', '>', now())
            ->orderBy('start_at');

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $query->when($request->filled('date'), fn ($q) => $q->whereDate('start_at', $request->date));

        $bookings = $query->paginate(12)->withQueryString();
        return view('bookings.index', compact('bookings'));
    }

    public function create(Request $request)
    {
        $rooms = Room::where('is_active', true)->orderForDisplay()->get();
        $lecturers = User::where('role', User::ROLE_LECTURER)->orderBy('name')->get(['id', 'name']);
        $selectedRoom = $request->integer('room_id') ?: null;
        $bookingStartDate = today();
        $bookingEndDate = today()->addDay();
        $selectedDate = $request->input('date', $bookingEndDate->toDateString());

        try {
            $requestedDate = Carbon::createFromFormat('Y-m-d', $selectedDate)->startOfDay();

            if (! $requestedDate->betweenIncluded($bookingStartDate, $bookingEndDate)) {
                $selectedDate = $bookingEndDate->toDateString();
            }
        } catch (\Throwable) {
            $selectedDate = $bookingEndDate->toDateString();
        }

        return view('bookings.create', compact('rooms', 'lecturers', 'selectedRoom', 'selectedDate', 'bookingStartDate', 'bookingEndDate'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'course_name' => ['required', 'string', 'max:255'],
            'class_group' => ['required', 'string', 'max:100'],
            'lecturer_name' => ['nullable', 'string', 'max:255'],
            'other_lecturer_name' => ['required_if:lecturer_name,__other__', 'nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addDay()->toDateString()],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'other_lecturer_name.required_if' => 'Isi nama dosen jika memilih Dosen lainnya.',
            'end_time.after' => 'Jam selesai harus lebih besar dari jam mulai.',
            'date.after_or_equal' => 'Tanggal penggunaan tidak boleh di masa lalu.',
            'date.before_or_equal' => 'Booking hanya dapat dibuat untuk hari ini atau besok.',
        ]);

        if (($data['lecturer_name'] ?? null) === '__other__') {
            $data['lecturer_name'] = $data['other_lecturer_name'];
        }

        $start = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['start_time']);
        $end = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['end_time']);

        if ($start->lte(now())) {
            throw ValidationException::withMessages(['start_time' => 'Waktu mulai harus berada di masa mendatang.']);
        }

        $booking = DB::transaction(function () use ($data, $start, $end, $request) {
            // Mengunci baris ruang: permintaan serentak untuk ruang yang sama diproses bergantian.
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            if (! $room->is_active) {
                throw ValidationException::withMessages(['room_id' => 'Ruang ini sedang tidak aktif.']);
            }

            $blocked = ScheduleAvailability::blockFor($room->id, $start, $end);
            if ($blocked) {
                throw ValidationException::withMessages(['start_time' => 'Ruang sedang diblokir untuk '.$blocked->reason.' pada rentang waktu tersebut.']);
            }
            $regular = ScheduleAvailability::recurringFor($room->id, $start, $end);
            if ($regular) {
                throw ValidationException::withMessages(['start_time' => 'Bentrok dengan jadwal kuliah tetap: '.$regular->course_name.' ('.$regular->prodi.').']);
            }

            // Sebelum pukul 00.00 pada tanggal jadwal, slot hanya dapat diambil
            // oleh akun prodi/kelas yang telah diberi prioritas oleh admin.
            $priorityConflict = PrioritySchedule::with('user')
                ->active()
                ->where('room_id', $room->id)
                ->whereDate('schedule_date', $start->toDateString())
                ->whereDate('schedule_date', '>', today()->toDateString())
                ->where('user_id', '!=', $request->user()->id)
                ->overlappingTimes($start->format('H:i:s'), $end->format('H:i:s'))
                ->first();

            if ($priorityConflict) {
                throw ValidationException::withMessages([
                    'start_time' => 'Ruang dan jam ini diprioritaskan untuk '.$priorityConflict->user->name
                        .' sampai '.$priorityConflict->schedule_date->format('d/m/Y').' pukul 00.00.',
                ]);
            }

            $roomConflict = Booking::active()
                ->where('room_id', $room->id)
                ->overlapping($start, $end)
                ->exists();

            if ($roomConflict) {
                throw ValidationException::withMessages(['start_time' => 'Ruang sudah terpakai pada rentang waktu tersebut. Silakan pilih jam atau ruang lain.']);
            }

            return Booking::create([
                'room_id' => $room->id,
                'user_id' => $request->user()->id,
                'course_name' => $data['course_name'],
                'class_group' => $data['class_group'],
                'lecturer_name' => $data['lecturer_name'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'start_at' => $start,
                'end_at' => $end,
                'status' => Booking::STATUS_ACTIVE,
            ]);
        }, 3);

        return redirect()->route('bookings.index')->with('success', 'Penggunaan ruang berhasil dicatat untuk '.$booking->start_at->translatedFormat('l, d F Y H:i').'.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        if (! $request->user()->isAdmin() && $booking->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($booking->status === Booking::STATUS_CANCELLED) {
            return back()->with('error', 'Penggunaan ruang ini sudah dibatalkan sebelumnya.');
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);
        AdminAudit::record('booking.cancelled', $booking, [
            'room_id' => $booking->room_id, 'user_id' => $booking->user_id,
            'course' => $booking->course_name, 'start_at' => $booking->start_at->toDateTimeString(),
        ]);

        return back()->with('success', 'Penggunaan ruang berhasil dibatalkan. Jadwal ruang kini tersedia kembali.');
    }
}
