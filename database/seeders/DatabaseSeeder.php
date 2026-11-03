<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@kampus.test'],
            ['name' => 'Administrator', 'username' => 'admin', 'nim' => null, 'password' => Hash::make('123456'), 'role' => 'admin', 'is_active' => true]
        );

        $studyPrograms = [
            ['name' => 'D4 Manajemen Informasi Kesehatan', 'username' => 'mik', 'nim' => 'PRODI-D4-MIK'],
            ['name' => 'D3 Kebidanan', 'username' => 'd3bidan', 'nim' => 'PRODI-D3-BIDAN'],
            ['name' => 'S1 Kebidanan', 'username' => 's1bidan', 'nim' => 'PRODI-S1-BIDAN'],
            ['name' => 'D3 Keperawatan', 'username' => 'd3keperawatan', 'nim' => 'PRODI-D3-KEP'],
            ['name' => 'S1 Keperawatan', 'username' => 's1keperawatan', 'nim' => 'PRODI-S1-KEP'],
            ['name' => 'S1 Teknologi Informasi', 'username' => 'ti', 'nim' => 'PRODI-TI'],
            ['name' => 'S1 Farmasi', 'username' => 'farmasi', 'nim' => 'PRODI-S1-FAR'],
            ['name' => 'S1 Manajemen', 'username' => 's1manajemen', 'nim' => 'PRODI-S1-MANAJEMEN'],
            ['name' => 'S1 Hukum', 'username' => 's1hukum', 'nim' => 'PRODI-S1-HUKUM'],
        ];

        foreach ($studyPrograms as $program) {
            User::updateOrCreate(
                ['username' => $program['username']],
                [
                    'name' => $program['name'],
                    'study_program' => $program['name'],
                    'email' => $program['username'].'@kampus.test',
                    'nim' => $program['nim'],
                    'password' => Hash::make('123456'),
                    'role' => 'student',
                    'is_active' => true,
                ]
            );
        }

        $rooms = [
            ['code' => 'R-1', 'name' => 'Ruang Kelas R-1', 'building' => 'Gedung Siti Walidah', 'capacity' => 58, 'facilities' => 'Referensi denah: S1 Farmasi'],
            ['code' => 'R-2', 'name' => 'Ruang Kelas R-2', 'building' => 'Gedung Siti Walidah', 'capacity' => 55, 'facilities' => 'Referensi denah: S1 Keperawatan'],
            ['code' => 'R-4', 'name' => 'Ruang Kelas R-4', 'building' => 'Gedung Siti Walidah', 'capacity' => 20, 'facilities' => 'Referensi denah: D3 Perawat'],
            ['code' => 'R-5', 'name' => 'Ruang Kelas R-5', 'building' => 'Gedung Siti Walidah', 'capacity' => 30, 'facilities' => 'Referensi denah: D4 MIKES'],
            ['code' => 'R-6', 'name' => 'Ruang Aula R-6', 'building' => 'Gedung Siti Walidah', 'capacity' => 80, 'facilities' => 'Aula gabungan'],
            ['code' => 'R-7', 'name' => 'Ruang Kelas R-7', 'building' => 'Gedung Siti Walidah', 'capacity' => 35, 'facilities' => 'Referensi denah: S1 Keperawatan'],
            ['code' => 'R-8', 'name' => 'Ruang Kelas R-8', 'building' => 'Gedung Siti Walidah', 'capacity' => 35, 'facilities' => 'Referensi denah: S1 Keperawatan'],
            ['code' => 'R-9', 'name' => 'Ruang Kelas R-9', 'building' => 'Gedung Siti Walidah', 'capacity' => 15, 'facilities' => 'Referensi denah: D3 Bidan'],
            ['code' => 'R-10', 'name' => 'Ruang Kelas R-10', 'building' => 'Gedung Siti Bariah', 'capacity' => 20, 'facilities' => 'Referensi denah: Prodi Hukum'],
            ['code' => 'R-11', 'name' => 'Ruang Kelas R-11', 'building' => 'Gedung Siti Bariah', 'capacity' => 30, 'facilities' => 'Referensi denah: Prodi Hukum'],
            ['code' => 'R-12', 'name' => 'Ruang Kelas R-12', 'building' => 'Gedung Siti Bariah', 'capacity' => 45, 'facilities' => 'Referensi denah: D4 MIKES'],
            ['code' => 'R-13', 'name' => 'Ruang Kelas R-13', 'building' => 'Gedung Siti Bariah', 'capacity' => 53, 'facilities' => 'Referensi denah: S1 Farmasi'],
            ['code' => 'R-14', 'name' => 'Ruang Kelas R-14', 'building' => 'Gedung Siti Bariah', 'capacity' => 40, 'facilities' => 'Referensi denah: S1 Farmasi'],
            ['code' => 'R-15', 'name' => 'Ruang Kelas R-15', 'building' => 'Gedung Siti Bariah', 'capacity' => 20, 'facilities' => 'Referensi denah: S1 Teknologi Informasi'],
            ['code' => 'R-16', 'name' => 'Ruang Kelas R-16', 'building' => 'Gedung Siti Bariah', 'capacity' => 55, 'facilities' => 'Referensi denah: S1 Kebidanan'],
            ['code' => 'R-17', 'name' => 'Ruang Kelas R-17', 'building' => 'Gedung Siti Bariah', 'capacity' => 7, 'facilities' => 'Referensi denah: S1 Kebidanan'],
        ];

        foreach ($rooms as $room) {
            Room::updateOrCreate(['code' => $room['code']], $room + ['is_active' => true]);
        }

    }
}
