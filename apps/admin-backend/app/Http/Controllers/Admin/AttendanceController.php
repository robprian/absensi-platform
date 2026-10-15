<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\StoredFile;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\TdocsException;
use App\Services\TdocsService;
use App\Services\WorkingHoursService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $sessions = AttendanceSession::with(['employee', 'events'])
            ->when($request->employee_id, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('work_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('work_date', '<=', $d))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('work_date')
            ->paginate(25)
            ->withQueryString();

        $employees = Employee::where('employment_status', 'aktif')->orderBy('name')->get();
        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');

        return view('admin.attendance.index', compact('sessions', 'employees', 'tz'));
    }

    public function show(AttendanceSession $session)
    {
        $this->authorize('view', $session);

        $session->load(['employee', 'events.evidence.storedFile', 'workSite', 'corrections']);
        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');

        $photos = [];
        foreach ($session->events as $event) {
            foreach ($event->evidence as $ev) {
                $file = $ev->storedFile;
                if ($file) {
                    $photos[] = [
                        'event_type' => $event->event_type,
                        'evidence_type' => $ev->evidence_type,
                        'url' => URL::temporarySignedRoute('admin.files.preview', now()->addMinutes(30), ['file' => $file->id]),
                        'status' => $file->status,
                    ];
                }
            }
        }

        return view('admin.attendance.show', compact('session', 'tz', 'photos'));
    }

    public function corrections(Request $request)
    {
        $this->authorize('review', AttendanceSession::class);

        $corrections = AttendanceCorrection::with(['session.employee', 'requester'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', 'pending'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.attendance.corrections', compact('corrections'));
    }

    public function approveCorrection(Request $request, AttendanceCorrection $correction)
    {
        $this->authorize('review', AttendanceSession::class);

        if ($correction->status !== 'pending') {
            return back()->with('err', 'Koreksi sudah diproses.');
        }

        $payload = $correction->payload ?? [];
        if ($correction->type === 'missing_clock_out' && !empty($payload['clock_out_at'])) {
            $session = $correction->session;
            if (!$session->events()->where('event_type', AttendanceEvent::TYPE_KELUAR)->exists()) {
                AttendanceEvent::create([
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

        return back()->with('ok', 'Koreksi disetujui.');
    }

    public function rejectCorrection(Request $request, AttendanceCorrection $correction)
    {
        $this->authorize('review', AttendanceSession::class);

        $correction->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditService::log('attendance.correction.rejected', $request->user(), 'attendance_corrections', (string) $correction->id);

        return back()->with('ok', 'Koreksi ditolak.');
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', AttendanceSession::class);

        AuditService::log('attendance.exported', $request->user());

        return Excel::download(
            new AttendanceExport($request->only(['employee_id', 'date_from', 'date_to', 'status'])),
            'absensi-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    /**
     * Secure file preview for the admin portal (session auth + signed URL).
     */
    public function previewFile(Request $request, StoredFile $file, TdocsService $tdocs)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Tautan tidak valid atau kedaluwarsa.');
        }

        $user = $request->user();
        if (!$user->hasPermission('documents.view')
            && !$user->hasPermission('attendance.view')
            && !$user->hasPermission('reimbursements.review')) {
            abort(403);
        }

        if (!$file->isUploaded() && $file->staged_path && Storage::disk('local')->exists($file->staged_path)) {
            return response()->file(Storage::disk('local')->path($file->staged_path), ['Content-Type' => $file->mime_type]);
        }

        try {
            $bytes = $tdocs->downloadBytes((string) $file->tdocs_file_id);
        } catch (TdocsException $e) {
            abort(502, 'Gagal mengambil file dari penyimpanan.');
        }

        return response($bytes, 200, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . $file->original_name . '"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
