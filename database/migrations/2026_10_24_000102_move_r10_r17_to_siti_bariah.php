<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('rooms')
            ->whereIn('code', ['R-10', 'R-11', 'R-12', 'R-13', 'R-14', 'R-15', 'R-16', 'R-17'])
            ->update(['building' => 'Gedung Siti Bariah', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep room location data intact when rolling back.
    }
};
