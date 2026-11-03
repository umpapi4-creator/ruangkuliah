<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrioritySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id', 'user_id', 'schedule_date', 'start_time', 'end_time',
        'notes', 'is_active', 'created_by',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOverlappingTimes($query, string $startTime, string $endTime)
    {
        return $query
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }

    public function getStartTimeLabelAttribute(): string
    {
        return str_replace(':', '.', substr((string) $this->start_time, 0, 5));
    }

    public function getEndTimeLabelAttribute(): string
    {
        return str_replace(':', '.', substr((string) $this->end_time, 0, 5));
    }
}
