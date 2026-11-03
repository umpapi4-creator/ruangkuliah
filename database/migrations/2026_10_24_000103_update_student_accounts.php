<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration {
    public function up(): void
    {
        $students = [
            ['username' => 'mik', 'name' => 'D4 Manajemen Informasi Kesehatan', 'nim' => 'PRODI-D4-MIK'],
            ['username' => 'd3bidan', 'name' => 'D3 Kebidanan', 'nim' => 'PRODI-D3-BIDAN'],
            ['username' => 's1bidan', 'name' => 'S1 Kebidanan', 'nim' => 'PRODI-S1-BIDAN'],
            ['username' => 'd3keperawatan', 'name' => 'D3 Keperawatan', 'nim' => 'PRODI-D3-KEP'],
            ['username' => 's1keperawatan', 'name' => 'S1 Keperawatan', 'nim' => 'PRODI-S1-KEP'],
            ['username' => 'ti', 'name' => 'S1 Teknologi Informasi', 'nim' => 'PRODI-TI'],
            ['username' => 'farmasi', 'name' => 'S1 Farmasi', 'nim' => 'PRODI-S1-FAR'],
            ['username' => 's1manajemen', 'name' => 'S1 Manajemen', 'nim' => 'PRODI-S1-MANAJEMEN'],
            ['username' => 's1hukum', 'name' => 'S1 Hukum', 'nim' => 'PRODI-S1-HUKUM'],
        ];

        foreach ($students as $student) {
            $existing = DB::table('users')
                ->where('username', $student['username'])
                ->orWhere('nim', $student['nim'])
                ->first();
            $values = $student + [
                'email' => $student['username'].'@kampus.test',
                'password' => Hash::make('123456'),
                'role' => 'student',
                'is_active' => true,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update($values);
            } else {
                DB::table('users')->insert($values + ['created_at' => now()]);
            }
        }

        // Keep historical bookings intact; the foreign key prevents deleting a user
        // referenced by bookings. Deactivate the demo account so it cannot log in.
        DB::table('users')->where('username', 'mahasiswa')->where('role', 'student')->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep accounts and their related history intact when rolling back.
    }
};
