<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AdminAudit
{
    public static function record(string $action, ?Model $subject = null, array $details = []): void
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return;
        }
        AdminActivityLog::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : 'System',
            'subject_id' => $subject?->getKey(),
            'details' => $details,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
