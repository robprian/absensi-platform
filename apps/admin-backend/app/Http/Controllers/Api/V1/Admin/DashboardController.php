<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\Reimbursement;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');
        $today = now($tz)->format('Y-m-d');

        $activeEmployees = Employee::where('employment_status', 'aktif')->count();

        $todaySessions = AttendanceSession::where('work_date', $today)->with('events');

        $currentlyWorking = (clone $todaySessions)->where('status', 'open')
            ->whereHas('events', fn ($q) => $q->where('event_type', AttendanceEvent::TYPE_MASUK))
            ->whereDoesntHave('events', fn ($q) => $q->where('event_type', AttendanceEvent::TYPE_KELUAR))
            ->count();

        $completedShifts = (clone $todaySessions)->where('status', 'closed')->count();

        $lateEmployees = (clone $todaySessions)
            ->whereHas('events', function ($q) use ($tz) {
                $q->where('event_type', AttendanceEvent::TYPE_MASUK)
                    ->whereTime('server_at', '>', '08:15:00');
            })
            ->count();

        $needsReview = AttendanceSession::where('status', 'exception')->count()
            + AttendanceCorrection::where('status', 'pending')->count()
            + AttendanceEvent::whereDate('server_at', $today)
                ->where('verification_status', 'flagged')->count();

        $pendingReimbursements = Reimbursement::where('status', Reimbursement::STATUS_MENUNGGU)->count();

        return response()->json([
            'active_employees' => $activeEmployees,
            'currently_working' => $currentlyWorking,
            'completed_shifts' => $completedShifts,
            'late_employees' => $lateEmployees,
            'needs_review' => $needsReview,
            'pending_reimbursements' => $pendingReimbursements,
            'today_label' => now($tz)->locale('id')->isoFormat('dddd, D MMMM Y'),
        ]);
    }
}
