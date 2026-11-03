<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $rooms = [
            ['code' => 'R-1', 'name' => 'Ruang Kelas R-1', 'capacity' => 58, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Farmasi'],
            ['code' => 'R-2', 'name' => 'Ruang Kelas R-2', 'capacity' => 55, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Keperawatan'],
            ['code' => 'R-3', 'name' => 'Ruang Kelas R-3', 'capacity' => 20, 'facilities' => 'Gedung Siti Walidah; referensi denah: D3 Perawat'],
            ['code' => 'R-4', 'name' => 'Ruang Kelas R-4', 'capacity' => 20, 'facilities' => 'Gedung Siti Walidah; referensi denah: D3 Perawat'],
            ['code' => 'R-5', 'name' => 'Ruang Kelas R-5', 'capacity' => 30, 'facilities' => 'Gedung Siti Walidah; referensi denah: D4 MIKES'],
            ['code' => 'R-6', 'name' => 'Ruang Aula R-6', 'capacity' => 80, 'facilities' => 'Gedung Siti Walidah; aula gabungan'],
            ['code' => 'R-7', 'name' => 'Ruang Kelas R-7', 'capacity' => 35, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Keperawatan'],
            ['code' => 'R-8', 'name' => 'Ruang Kelas R-8', 'capacity' => 35, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Keperawatan'],
            ['code' => 'R-9', 'name' => 'Ruang Kelas R-9', 'capacity' => 15, 'facilities' => 'Gedung Siti Walidah; referensi denah: D3 Bidan'],
            ['code' => 'R-12', 'name' => 'Ruang Kelas R-12', 'capacity' => 45, 'facilities' => 'Gedung Siti Walidah; referensi denah: D4 MIKES'],
            ['code' => 'R-13', 'name' => 'Ruang Kelas R-13', 'capacity' => 53, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Farmasi'],
            ['code' => 'R-14', 'name' => 'Ruang Kelas R-14', 'capacity' => 40, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Farmasi'],
            ['code' => 'R-15', 'name' => 'Ruang Kelas R-15', 'capacity' => 20, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Teknologi Informasi'],
            ['code' => 'R-16', 'name' => 'Ruang Kelas R-16', 'capacity' => 55, 'facilities' => 'Gedung Siti Walidah; referensi denah: S1 Kebidanan'],
        ];

        foreach ($rooms as $room) {
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
