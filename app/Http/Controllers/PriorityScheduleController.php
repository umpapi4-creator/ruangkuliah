<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PrioritySchedule;
use App\Models\Room;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\ScheduleAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PriorityScheduleController extends Controller
{
    public function index()
    {
        $priorities = PrioritySchedule::with(['room', 'user', 'creator'])
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->paginate(12);

        return view('admin.priorities.index', compact('priorities'));
    }

    public function create()
    {
        return view('admin.priorities.form', $this->formData(new PrioritySchedule()));
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $this->lockTargetRoom($request);
            $data = $this->validated($request);
            $data['created_by'] = $request->user()->id;
            $priority = PrioritySchedule::create($data);
            AdminAudit::record('priority.created', $priority, $priority->only(['room_id', 'user_id', 'schedule_date', 'start_time', 'end_time']));
        }, 3);

        return redirect()->route('admin.priorities.index')
            ->with('success', 'Prioritas jadwal berhasil ditambahkan.');
    }

    public function edit(PrioritySchedule $priority)
    {
        return view('admin.priorities.form', $this->formData($priority));
    }

    public function update(Request $request, PrioritySchedule $priority)
    {
        DB::transaction(function () use ($request, $priority) {
            $this->lockTargetRoom($request);
            $before = $priority->only(['room_id', 'user_id', 'schedule_date', 'start_time', 'end_time', 'is_active']);
            $priority->update($this->validated($request, $priority));
            AdminAudit::record('priority.updated', $priority, ['before' => $before, 'after' => $priority->only(array_keys($before))]);
        }, 3);

        return redirect()->route('admin.priorities.index')
            ->with('success', 'Prioritas jadwal berhasil diperbarui.');
    }

    public function destroy(PrioritySchedule $priority)
    {
        AdminAudit::record('priority.deleted', $priority, $priority->only(['room_id', 'user_id', 'schedule_date', 'start_time', 'end_time']));
        $priority->delete();

        return back()->with('success', 'Prioritas jadwal berhasil dihapus.');
    }

    private function lockTargetRoom(Request $request): void
    {
        $request->validate(['room_id' => ['required', 'integer', 'exists:rooms,id']]);
        Room::whereKey($request->integer('room_id'))->lockForUpdate()->firstOrFail();
    }

    private function formData(PrioritySchedule $priority): array
    {
        return [
            'priority' => $priority,
            'rooms' => Room::where('is_active', true)->orderForDisplay()->get(),
            'users' => User::where('role', User::ROLE_STUDENT)
                ->where('is_active', true)
                ->orderForDisplay()
                ->get(),
        ];
    }

    private function validated(Request $request, ?PrioritySchedule $priority = null): array
    {
        $data = $request->validate([
            'room_id' => [
                'required',
                Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', User::ROLE_STUDENT)
                    ->where('is_active', true)),
            ],
            'schedule_date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'schedule_date.after' => 'Tanggal prioritas harus mulai besok atau setelahnya.',
            'end_time.after' => 'Jam selesai harus lebih besar dari jam mulai.',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if (! $data['is_active']) {
            return $data;
        }

        $priorityConflict = PrioritySchedule::active()
            ->where('room_id', $data['room_id'])
            ->whereDate('schedule_date', $data['schedule_date'])
            ->when($priority, fn ($query) => $query->where('id', '!=', $priority->id))
            ->overlappingTimes($data['start_time'], $data['end_time'])
            ->exists();

        if ($priorityConflict) {
            throw ValidationException::withMessages([
                'start_time' => 'Ruang tersebut sudah memiliki prioritas lain pada jam yang beririsan.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m-d H:i', $data['schedule_date'].' '.$data['start_time']);
        $end = Carbon::createFromFormat('Y-m-d H:i', $data['schedule_date'].' '.$data['end_time']);
        $block = ScheduleAvailability::blockFor((int) $data['room_id'], $start, $end);
        if ($block) {
            throw ValidationException::withMessages(['start_time' => 'Ruangan diblokir untuk '.$block->reason.' pada waktu tersebut.']);
        }
        $regular = ScheduleAvailability::recurringFor((int) $data['room_id'], $start, $end);
        if ($regular) {
            throw ValidationException::withMessages(['start_time' => 'Bentrok dengan jadwal kuliah tetap '.$regular->course_name.' ('.$regular->prodi.').']);
        }

        $bookingConflict = Booking::active()
            ->where('room_id', $data['room_id'])
            ->where('user_id', '!=', $data['user_id'])
            ->overlapping($start, $end)
            ->exists();

        if ($bookingConflict) {
            throw ValidationException::withMessages([
                'room_id' => 'Ruang dan jam tersebut sudah dibooking akun lain sehingga tidak dapat diberi prioritas.',
            ]);
        }

        return $data;
    }
}
