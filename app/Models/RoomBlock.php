<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBlock extends Model
{
    protected $fillable = ['room_id', 'reason', 'notes', 'start_at', 'end_at', 'is_active', 'created_by'];
    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime', 'is_active' => 'boolean'];
    public function room() { return $this->belongsTo(Room::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeOverlapping($query, $start, $end)
    {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }
}
