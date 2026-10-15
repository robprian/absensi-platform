<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $q = AttendanceSession::with(['employee', 'events', 'workSite'])
            ->when($request->employee_id, fn ($qq, $id) => $qq->where('employee_id', $id))
            ->when($request->date_from, fn ($qq, $d) => $qq->where('work_date', '>=', $d))
            ->when($request->date_to, fn ($qq, $d) => $qq->where('work_date', '<=', $d))
            ->when($request->status, fn ($qq, $s) => $qq->where('status', $s))
            ->orderByDesc('work_date')
            ->orderBy('employee_id');

        return response()->json($q->paginate(25));
    }

    public function show(Request $request, AttendanceSession $session)
    {
        $this->authorize('view', $session);

        $session->load(['employee', 'events.evidence.storedFile', 'workSite', 'corrections.requester']);

        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');
        $events = $session->events->map(function ($e) use ($tz) {
            return [
                'id' => $e->id,
                'type' => $e->event_type,
                'server_at' => $e->server_at->setTimezone($tz)->format('Y-m-d H:i:s'),
                'latitude' => $e->latitude,
                'longitude' => $e->longitude,
                'accuracy_meters' => $e->accuracy_meters,
                'address' => $e->address,
                'verification_status' => $e->verification_status,
                'rejection_reason' => $e->rejection_reason,
                'map_url' => "https://www.google.com/maps?q={$e->latitude},{$e->longitude}",
                'photos' => $e->evidence->map(fn ($ev) => [
                    'type' => $ev->evidence_type,
                    'preview_url' => $ev->storedFile?->isUploaded()
                        ? URL::temporarySignedRoute('api.v1.admin.files.preview', now()->addMinutes(10), ['file' => $ev->storedFile->id])
                        : null,
                    'status' => $ev->storedFile?->status,
                ]),
            ];
        });

        return response()->json([
            'session' => $session,
            'events' => $events,
        ]);
    }

    /**
     * GET /api/v1/admin/attendance/corrections — pending review queue.
     */
    public function corrections(Request $request)
    {
        $this->authorize('review', AttendanceSession::class);

        $q = AttendanceCorrection::with(['session.employee', 'event', 'requester'])
            ->when($request->status, fn ($qq, $s) => $qq->where('status', $s), fn ($qq) => $qq->where('status', 'pending'))
            ->orderByDesc('created_at');

        return response()->json($q->paginate(20));
    }

    /**
     * POST /api/v1/admin/attendance/corrections/{id}/approve
     */
    public function approveCorrection(Request $request, AttendanceCorrection $correction)
    {
        $this->authorize('review', AttendanceSession::class);

        if ($correction->status !== 'pending') {
            return response()->json(['message' => 'Koreksi sudah diproses.'], 422);
        }

        $payload = $correction->payload ?? [];

        // Apply the approved correction by creating an immutable event record
        // (for missing clock-out) — never mutating existing events.
        if ($correction->type === 'missing_clock_out' && !empty($payload['clock_out_at'])) {
            $session = $correction->session;
            $existing = $session->events()->where('event_type', AttendanceEvent::TYPE_KELUAR)->exists();
            if (!$existing) {
                $event = AttendanceEvent::create([
                    'attendance_session_id' => $session->id,
                    'employee_id' => $session->employee_id,
                    'event_type' => AttendanceEvent::TYPE_KELUAR,
                    'server_at' => $payload['clock_out_at'],
                    'latitude' => $payload['latitude'] ?? 0,
                    'longitude' => $payload['longitude'] ?? 0,
                    'accuracy_meters' => 0,
                    'device_info' => ['source' => 'admin_correction', 'correction_id' => $correction->id],
                    'ip_address' => $request->ip(),
                    'idempotency_key' => 'correction:' . $correction->id,
                    'verification_status' => 'verified',
                ]);
                $session->update([
                    'status' => AttendanceSession::STATUS_CLOSED,
                    'total_minutes' => $payload['total_minutes'] ?? null,
                ]);
            }
        }

        $correction->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditService::log('attendance.correction.approved', $request->user(), 'attendance_corrections', (string) $correction->id);

        return response()->json(['message' => 'Koreksi disetujui.']);
    }

    public function rejectCorrection(Request $request, AttendanceCorrection $correction)
    {
        $this->authorize('review', AttendanceSession::class);

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        if ($correction->status !== 'pending') {
            return response()->json(['message' => 'Koreksi sudah diproses.'], 422);
        }

        $correction->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditService::log('attendance.correction.rejected', $request->user(), 'attendance_corrections', (string) $correction->id);

        return response()->json(['message' => 'Koreksi ditolak.']);
    }

    /**
     * GET /api/v1/admin/attendance/export — CSV/XLSX export.
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $request->validate([
            'format' => ['nullable', 'in:csv,xlsx'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $format = $request->input('format', 'xlsx');
        $filename = 'absensi-' . now()->format('Ymd-His') . '.' . $format;

        AuditService::log('attendance.exported', $request->user());

        return Excel::download(
            new AttendanceExport($request->only(['employee_id', 'date_from', 'date_to', 'status'])),
            $filename
        );
    }
}
