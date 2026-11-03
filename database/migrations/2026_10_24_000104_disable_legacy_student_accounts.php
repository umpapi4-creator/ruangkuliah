<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'student')
            ->whereIn('username', ['d4mik', 'd3kebidanan', 's1kebidanan', 's1farmasi'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep account status changes intact when rolling back.
    }
};
