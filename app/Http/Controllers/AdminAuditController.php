<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['action' => ['nullable', 'string', 'max:90'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $logs = AdminActivityLog::with('admin')->when(! empty($data['action']), fn ($q) => $q->where('action', $data['action']))
            ->when(! empty($data['date']), fn ($q) => $q->whereDate('created_at', $data['date']))
            ->orderByDesc('id')->paginate(25)->withQueryString();
        $actions = AdminActivityLog::distinct()->orderBy('action')->pluck('action');
        return view('admin.audits.index', compact('logs', 'actions'));
    }
}
