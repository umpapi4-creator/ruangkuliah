<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function publicIndex()
    {
        $rooms = Room::where('is_active', true)
            ->withCount([
                'roomBlocks as current_blocks_count' => fn ($query) => $query->active()->where('start_at', '<=', now())->where('end_at', '>', now()),
                'courseSchedules as current_classes_count' => fn ($query) => $query->where('weekday', now()->dayOfWeekIso)
                    ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())
                    ->where('start_time', '<=', now()->format('H:i:s'))->where('end_time', '>', now()->format('H:i:s')),
                'bookings as active_bookings_count' => fn ($query) => $query
                    ->active()
                    ->where('end_at', '>', now()),
            ])
            ->orderForDisplay()
            ->get();

        return view('rooms.index', compact('rooms'));
    }

    public function index()
    {
        $rooms = Room::withCount(['bookings' => fn ($q) => $q->active()->where('end_at', '>', now())])->orderForDisplay()->paginate(10);
        return view('admin.rooms.index', compact('rooms'));
    }

    public function create()
    {
        return view('admin.rooms.form', ['room' => new Room()]);
    }

    public function store(Request $request)
    {
        $room = Room::create($this->validated($request));
        AdminAudit::record('room.created', $room, $room->only(['code', 'name', 'capacity', 'is_active']));
        return redirect()->route('admin.rooms.index')->with('success', 'Ruang kelas berhasil ditambahkan.');
    }

    public function edit(Room $room)
    {
        return view('admin.rooms.form', compact('room'));
    }

    public function update(Request $request, Room $room)
    {
        $before = $room->only(['code', 'name', 'building', 'floor', 'capacity', 'facilities', 'is_active']);
        $room->update($this->validated($request, $room));
        AdminAudit::record('room.updated', $room, ['before' => $before, 'after' => $room->only(array_keys($before))]);
        return redirect()->route('admin.rooms.index')->with('success', 'Data ruang kelas berhasil diperbarui.');
    }

    public function destroy(Room $room)
    {
        if ($room->bookings()->exists() || $room->prioritySchedules()->exists() || $room->courseSchedules()->exists() || $room->roomBlocks()->exists()) {
            return back()->with('error', 'Ruang tidak dapat dihapus karena sudah memiliki riwayat penggunaan atau prioritas jadwal. Nonaktifkan ruang sebagai gantinya.');
        }

        AdminAudit::record('room.deleted', $room, $room->only(['code', 'name']));
        $room->delete();
        return back()->with('success', 'Ruang kelas berhasil dihapus.');
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('rooms')->ignore($room?->id)],
            'name' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:30'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'facilities' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
