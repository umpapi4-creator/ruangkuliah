<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\AdminAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomBlockController extends Controller
{
    public function index()
    {
        $blocks = RoomBlock::with(['room', 'creator'])->orderByDesc('start_at')->paginate(15);
        $rooms = Room::orderForDisplay()->get();
        return view('admin.blocks.index', compact('blocks', 'rooms'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'reason' => ['required', 'in:Renovasi,Ujian,Rapat,Kerusakan,Kegiatan Kampus,Lainnya'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],
        ]);
        $data['start_at'] = Carbon::createFromFormat('Y-m-d\TH:i', $data['start_at']);
        $data['end_at'] = Carbon::createFromFormat('Y-m-d\TH:i', $data['end_at']);
        if ($data['end_at']->lte(now())) throw ValidationException::withMessages(['end_at' => 'Waktu akhir blokir harus berada di masa depan.']);
        DB::transaction(function () use ($data, $request) {
            Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            $overlap = RoomBlock::active()->where('room_id', $data['room_id'])
                ->overlapping($data['start_at'], $data['end_at'])->exists();
            if ($overlap) throw ValidationException::withMessages(['start_at' => 'Ruangan sudah diblokir pada waktu yang beririsan.']);
            $block = RoomBlock::create($data + ['is_active' => true, 'created_by' => $request->user()->id]);
            AdminAudit::record('room.blocked', $block, $block->only(['room_id', 'reason', 'start_at', 'end_at']));
        }, 3);
        return back()->with('success', 'Ruangan diblokir. Semua akun tidak dapat membuat booking pada jam tersebut. Booking lama tidak dibatalkan otomatis.');
    }

    public function update(Request $request, RoomBlock $block)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $before = $block->is_active;
        if ($data['is_active'] && ! $before) {
            $duplicate = RoomBlock::active()->where('room_id', $block->room_id)->where('id', '!=', $block->id)
                ->overlapping($block->start_at, $block->end_at)->exists();
            if ($duplicate) throw ValidationException::withMessages(['is_active' => 'Ada blokir lain yang masih aktif pada rentang ini.']);
        }
        $block->update(['is_active' => (bool) $data['is_active']]);
        AdminAudit::record($block->is_active ? 'room.block.reactivated' : 'room.block.released', $block, ['previous' => $before, 'current' => $block->is_active]);
        return back()->with('success', $block->is_active ? 'Blokir diaktifkan kembali.' : 'Blokir dinonaktifkan.');
    }
}
