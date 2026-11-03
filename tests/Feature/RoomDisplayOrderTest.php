<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\ConnectionResolver;
use Tests\TestCase;

class RoomDisplayOrderTest extends TestCase
{
    public function test_postgres_room_queries_use_postgres_compatible_ordering(): void
    {
        $resolver = Room::getConnectionResolver();
        $postgres = new ConnectionResolver([
            'pgsql' => new PostgresConnection(null, '', '', ['driver' => 'pgsql']),
        ]);
        $postgres->setDefaultConnection('pgsql');

        try {
            Room::setConnectionResolver($postgres);

            $sql = Room::where('is_active', true)
                ->withCount('bookings')
                ->orderForDisplay()
                ->toSql();

            $this->assertStringContainsString("code ~ '^R-[0-9]+$'", $sql);
            $this->assertStringContainsString('CAST(SUBSTRING(code, 3) AS NUMERIC)', $sql);
            $this->assertStringNotContainsString('REGEXP', $sql);
            $this->assertStringNotContainsString('UNSIGNED', $sql);
        } finally {
            Room::setConnectionResolver($resolver);
        }
    }
}
