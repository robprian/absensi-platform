<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\Reimbursement;
use App\Models\SystemSetting;

class DashboardController extends Controller
{
    public function index()
    {
        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');
        $today = now($tz)->format('Y-m-d');

        $stats = [
            'active_employees' => Employee::where('employment_status', 'aktif')->count(),
            'currently_working' => AttendanceSession::where('work_date', $today)
                ->where('status', 'open')
                ->whereHas('events', fn ($q) => $q->where('event_type', AttendanceEvent::TYPE_MASUK))
                ->whereDoesntHave('events', fn ($q) => $q->where('event_type', AttendanceEvent::TYPE_KELUAR))
                ->count(),
            'completed_shifts' => AttendanceSession::where('work_date', $today)->where('status', 'closed')->count(),
            'needs_review' => AttendanceSession::where('status', 'exception')->count()
                + AttendanceCorrection::where('status', 'pending')->count(),
            'pending_reimbursements' => Reimbursement::where('status', Reimbursement::STATUS_MENUNGGU)->count(),
            'today_label' => now($tz)->locale('id')->isoFormat('dddd, D MMMM Y'),
        ];

        $recentSessions = AttendanceSession::with(['employee', 'events'])
            ->where('work_date', $today)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentSessions', 'tz'));
    }
}
