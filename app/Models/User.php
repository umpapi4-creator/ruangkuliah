<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STUDENT = 'student';
    public const ROLE_LECTURER = 'lecturer';

    public const STUDY_PROGRAMS = [
        'D4 Manajemen Informasi Kesehatan',
        'D3 Kebidanan',
        'S1 Kebidanan',
        'D3 Keperawatan',
        'S1 Keperawatan',
        'S1 Teknologi Informasi',
        'S1 Farmasi',
        'S1 Manajemen',
        'S1 Hukum',
    ];

    protected $fillable = ['name', 'username', 'nim', 'email', 'password', 'role', 'study_program', 'is_active'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function prioritySchedules()
    {
        return $this->hasMany(PrioritySchedule::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isLecturer(): bool
    {
        return $this->role === self::ROLE_LECTURER;
    }

    public function canBookRooms(): bool
    {
        return $this->isStudent() || $this->isLecturer();
    }

    public function isManagedUser(): bool
    {
        return in_array($this->role, [self::ROLE_STUDENT, self::ROLE_LECTURER], true);
    }

    public function scopeOrderForDisplay($query)
    {
        return $query->orderBy('study_program')->orderBy('username');
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_LECTURER => 'Dosen',
            default => 'Mahasiswa',
        };
    }
}
