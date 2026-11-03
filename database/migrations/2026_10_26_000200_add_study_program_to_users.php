<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'study_program')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('study_program', 150)->nullable()->after('role')->index();
            });
        }

        $programs = [
            'mik' => 'D4 Manajemen Informasi Kesehatan',
            'd3bidan' => 'D3 Kebidanan',
            's1bidan' => 'S1 Kebidanan',
            'd3keperawatan' => 'D3 Keperawatan',
            's1keperawatan' => 'S1 Keperawatan',
            'ti' => 'S1 Teknologi Informasi',
            'farmasi' => 'S1 Farmasi',
            's1manajemen' => 'S1 Manajemen',
            's1hukum' => 'S1 Hukum',
        ];

        foreach ($programs as $username => $studyProgram) {
            DB::table('users')->where('username', $username)->update(['study_program' => $studyProgram]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'study_program')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('study_program');
            });
        }
    }
};
