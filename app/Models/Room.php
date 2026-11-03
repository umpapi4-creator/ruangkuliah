<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'building', 'floor', 'capacity', 'facilities', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'capacity' => 'integer'];

    public function scopeOrderForDisplay($query)
    {
        if ($query->getModel()->getConnection()->getDriverName() === 'pgsql') {
            return $query
                ->orderByRaw("CASE WHEN code ~ '^R-[0-9]+$' THEN CAST(SUBSTRING(code, 3) AS NUMERIC) ELSE 99999 END")
                ->orderBy('code');
        }

        if ($query->getModel()->getConnection()->getDriverName() === 'sqlite') {
            return $query
                ->orderByRaw("CASE WHEN code GLOB 'R-[0-9]*' AND code NOT GLOB 'R-*[^0-9]*' THEN CAST(SUBSTR(code, 3) AS INTEGER) ELSE 99999 END")
                ->orderBy('code');
        }

        return $query
            ->orderByRaw("CASE WHEN code REGEXP '^R-[0-9]+$' THEN CAST(SUBSTRING(code, 3) AS UNSIGNED) ELSE 99999 END")
            ->orderBy('code');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function prioritySchedules()
    {
        return $this->hasMany(PrioritySchedule::class);
    }

    public function courseSchedules()
    {
        return $this->hasMany(CourseSchedule::class);
    }

    public function roomBlocks()
    {
        return $this->hasMany(RoomBlock::class);
    }
}
