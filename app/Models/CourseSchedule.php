<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSchedule extends Model
{
    protected $fillable = ['room_id', 'prodi', 'course_name', 'lecturer_name', 'weekday', 'start_time', 'end_time', 'start_date', 'end_date', 'created_by'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'weekday' => 'integer'];

    public function room() { return $this->belongsTo(Room::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
