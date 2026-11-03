<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'student', 'lecturer') NOT NULL DEFAULT 'student'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('users')->where('role', 'lecturer')->update(['role' => 'student']);
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'student') NOT NULL DEFAULT 'student'");
    }
};
