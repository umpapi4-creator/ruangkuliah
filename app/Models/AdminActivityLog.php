<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    public $timestamps = false;
    const UPDATED_AT = null;
    protected $fillable = ['admin_id', 'action', 'subject_type', 'subject_id', 'details', 'ip_address', 'created_at'];
    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];
    public function admin() { return $this->belongsTo(User::class, 'admin_id'); }
}
