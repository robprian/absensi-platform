<?php

namespace App\Console\Commands;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\SystemSetting;
use Illuminate\Console\Command;

/**
 * Flags sessions from previous days that never got a clock-out.
 * Creates an admin-reviewable correction record for each.
 */
class MarkAttendanceExceptions extends Command
{
    protected $signature = 'attendance:mark-exceptions';
    protected $description = 'Tandai sesi absen hari sebelumnya yang tidak ada absen keluar';

    public function handle(): int
    {
        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');
        $today = now($tz)->format('Y-m-d');

        $sessions = AttendanceSession::where('status', AttendanceSession::STATUS_OPEN)
            ->where('work_date', '<', $today)
            ->with('events')
            ->get();

        $count = 0;
        foreach ($sessions as $session) {
            $hasMasuk = $session->events->contains('event_type', AttendanceEvent::TYPE_MASUK);
            $hasKeluar = $session->events->contains('event_type', AttendanceEvent::TYPE_KELUAR);
            if ($hasMasuk && !$hasKeluar) {
                $session->update(['status' => AttendanceSession::STATUS_EXCEPTION]);
                AttendanceCorrection::firstOrCreate(
                    [
                        'attendance_session_id' => $session->id,
                        'type' => 'missing_clock_out',
                        'status' => 'pending',
                    ],
                    [
                        'requested_by' => null, // system-generated
                        'payload' => [],
                        'reason' => 'Tidak ada absen keluar (otomatis).',
                    ]
                );
                $count++;
            }
        }

        $this->info("Marked $count session(s) as exception.");
        return self::SUCCESS;
    }
}
