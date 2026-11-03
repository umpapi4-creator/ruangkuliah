<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            ['code' => 'R-10', 'name' => 'Ruang Kelas R-10', 'capacity' => 20, 'facilities' => 'Gedung Siti Walidah; referensi denah: Prodi Hukum'],
            ['code' => 'R-11', 'name' => 'Ruang Kelas R-11', 'capacity' => 30, 'facilities' => 'Gedung Siti Walidah; referensi denah: Prodi Hukum'],
            ['code' => 'R-17', 'name' => 'Ruang Kelas R-17', 'capacity' => 7, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Kebidanan'],
        ] as $room) {
            DB::table('rooms')->updateOrInsert(
                ['code' => $room['code']],
                $room + ['building' => 'Gedung Siti Walidah', 'floor' => null, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // Do not remove rooms or their history when rolling back.
    }
};
