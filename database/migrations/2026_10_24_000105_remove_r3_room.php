<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $room = DB::table('rooms')->where('code', 'R-3')->first();
        if (! $room) return;

        $hasHistory = DB::table('bookings')->where('room_id', $room->id)->exists()
            || DB::table('priority_schedules')->where('room_id', $room->id)->exists()
            || DB::table('course_schedules')->where('room_id', $room->id)->exists()
            || DB::table('room_blocks')->where('room_id', $room->id)->exists();

        if ($hasHistory) {
            DB::table('rooms')->where('id', $room->id)->update(['is_active' => false, 'updated_at' => now()]);
        } else {
            DB::table('rooms')->where('id', $room->id)->delete();
        }
    }

    public function down(): void
    {
        // Do not recreate a room that was intentionally removed from the denah.
    }
};
