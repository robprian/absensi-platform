<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceEvidence;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\StoredFile;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Server-side attendance orchestration.
 *
 * Trust model: client timestamp/GPS are recorded as REPORTED EVIDENCE only.
 * server_at (receipt time, UTC) is authoritative. Watermark on the photo is
 * informational, never proof.
 */
class AttendanceService
{
    public function __construct(
        protected TdocsService $tdocs,
    ) {}

    // ------------------------------------------------------------------
    // Clock in / out
    // ------------------------------------------------------------------

    /**
     * @param array{lat: float, lng: float, accuracy: float, captured_at?: string, device_info?: array, selfie: UploadedFile, selfie_original?: UploadedFile, idempotency_key: string, ip?: string} $input
     * @throws AttendanceValidationException
     */
    public function recordEvent(Employee $employee, User $actor, string $type, array $input): AttendanceEvent
    {
        $type = strtoupper($type);
        if (!in_array($type, [AttendanceEvent::TYPE_MASUK, AttendanceEvent::TYPE_KELUAR], true)) {
            throw new AttendanceValidationException('invalid_type', 'Tipe absensi tidak valid.');
        }

        $lat = (float) $input['lat'];
        $lng = (float) $input['lng'];
        $accuracy = (float) $input['accuracy'];

        // 1. Coordinate sanity (never trust client blindly).
        if (!GeofenceService::validCoordinates($lat, $lng)) {
            throw new AttendanceValidationException('invalid_coordinates', 'Koordinat GPS tidak valid.');
        }

        // 2. Accuracy threshold.
        $maxAccuracy = (int) SystemSetting::get('gps_accuracy_threshold_m', 50);
        if ($accuracy <= 0 || $accuracy > $maxAccuracy) {
            throw new AttendanceValidationException(
                'low_accuracy',
                "Akurasi GPS terlalu rendah (±{$accuracy} m, maksimal ±{$maxAccuracy} m). Coba lagi di area terbuka."
            );
        }

        // 3. Stale GPS fix rejection.
        $staleSeconds = (int) SystemSetting::get('gps_stale_threshold_seconds', 300);
        $clientCapturedAt = null;
        if (!empty($input['captured_at'])) {
            try {
                $clientCapturedAt = CarbonImmutable::parse($input['captured_at'])->utc();
            } catch (\Throwable) {
                $clientCapturedAt = null;
            }
        }
        if ($clientCapturedAt && now()->utc()->diffInSeconds($clientCapturedAt) > $staleSeconds) {
            throw new AttendanceValidationException(
                'stale_fix',
                'Data lokasi sudah kedaluwarsa. Ambil lokasi baru dan coba lagi.'
            );
        }

        $selfie = $input['selfie'];
        $this->validatePhoto($selfie);

        return DB::transaction(function () use ($employee, $actor, $type, $input, $lat, $lng, $accuracy, $clientCapturedAt, $selfie) {
            $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');
            $nowJakarta = now($tz);
            $workDate = $nowJakarta->format('Y-m-d');

            // Session: one per employee per workday. whereDate keeps the lookup
            // format-independent; the unique constraint + catch guards races.
            $session = AttendanceSession::where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->first();
            if (!$session) {
                try {
                    $session = AttendanceSession::create([
                        'employee_id' => $employee->id,
                        'work_date' => $workDate,
                        'status' => AttendanceSession::STATUS_OPEN,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    $session = AttendanceSession::where('employee_id', $employee->id)
                        ->whereDate('work_date', $workDate)
                        ->firstOrFail();
                }
            }

            // Duplicate / sequence guards.
            $existingMasuk = $session->events()->where('event_type', AttendanceEvent::TYPE_MASUK)->exists();
            $existingKeluar = $session->events()->where('event_type', AttendanceEvent::TYPE_KELUAR)->exists();

            if ($type === AttendanceEvent::TYPE_MASUK && $existingMasuk) {
                throw new AttendanceValidationException('duplicate_clock_in', 'Anda sudah absen masuk hari ini.');
            }
            if ($type === AttendanceEvent::TYPE_KELUAR) {
                if (!$existingMasuk) {
                    throw new AttendanceValidationException('missing_clock_in', 'Belum ada absen masuk. Lakukan "Mulai Kerja" terlebih dahulu.');
                }
                if ($existingKeluar) {
                    throw new AttendanceValidationException('duplicate_clock_out', 'Anda sudah absen keluar hari ini.');
                }
            }

            // 4. Geofence policy.
            $verification = $this->checkGeofence($employee, $lat, $lng);

            // 5. Stage the photo durably (private staging; never public).
            $storedFile = $this->stageAndUploadPhoto($selfie, $employee, $type, $actor);

            // Optional separate original (client may send un-watermarked original).
            $originalFile = null;
            if (!empty($input['selfie_original']) && $input['selfie_original'] instanceof UploadedFile) {
                $this->validatePhoto($input['selfie_original']);
                $originalFile = $this->stageAndUploadPhoto($input['selfie_original'], $employee, $type . '_ORIG', $actor);
            }

            $event = AttendanceEvent::create([
                'attendance_session_id' => $session->id,
                'employee_id' => $employee->id,
                'event_type' => $type,
                'server_at' => now()->utc(),
                'client_captured_at' => $clientCapturedAt?->utc(),
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy_meters' => $accuracy,
                'address' => $input['address'] ?? null,
                'device_info' => $input['device_info'] ?? null,
                'ip_address' => $input['ip'] ?? request()?->ip(),
                'idempotency_key' => $input['idempotency_key'],
                'verification_status' => $verification['status'],
                'rejection_reason' => $verification['reason'],
            ]);

            $event->evidence()->create([
                'evidence_type' => AttendanceEvidence::TYPE_WATERMARKED,
                'stored_file_id' => $storedFile->id,
                'client_metadata' => ['accuracy_meters' => $accuracy],
            ]);
            if ($originalFile) {
                $event->evidence()->create([
                    'evidence_type' => AttendanceEvidence::TYPE_ORIGINAL,
                    'stored_file_id' => $originalFile->id,
                ]);
            }

            // 6. Close-out math on KELUAR (server-side truth).
            if ($type === AttendanceEvent::TYPE_KELUAR) {
                $masuk = $session->events()->where('event_type', AttendanceEvent::TYPE_MASUK)->first();
                $schedule = $this->scheduleFor($employee, $workDate);
                $hours = WorkingHoursService::compute(
                    CarbonImmutable::parse($masuk->server_at)->utc(),
                    CarbonImmutable::parse($event->server_at)->utc(),
                    $schedule?->break_minutes ?? (int) SystemSetting::get('break_minutes_default', 60),
                    $schedule ? [
                        'start' => substr($schedule->start_time, 0, 5),
                        'end' => substr($schedule->end_time, 0, 5),
                        'overnight' => (bool) $schedule->overnight,
                        'late_tolerance_min' => (int) SystemSetting::get('late_tolerance_minutes', 15),
                    ] : null,
                    (int) SystemSetting::get('overtime_threshold_minutes', 480),
                );
                $session->update([
                    'total_minutes' => $hours['total_minutes'],
                    'status' => AttendanceSession::STATUS_CLOSED,
                ]);
            }

            AuditService::log(
                $type === AttendanceEvent::TYPE_MASUK ? 'attendance.clock_in' : 'attendance.clock_out',
                $actor, 'attendance_events', (string) $event->id, null,
                ['session_id' => $session->id, 'verification' => $verification['status']]
            );

            return $event->fresh();
        });
    }

    protected function validatePhoto(UploadedFile $file): void
    {
        $maxKb = (int) SystemSetting::get('attendance_photo_max_kb', 5120);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            throw new AttendanceValidationException('invalid_photo', 'Foto harus berformat JPG, PNG, atau WebP.');
        }
        if ($file->getSize() > $maxKb * 1024) {
            throw new AttendanceValidationException('photo_too_large', 'Ukuran foto melebihi batas ' . $maxKb . ' KB.');
        }
    }

    /**
     * Stage photo in the private staging area, then upload to tDocs.
     * On tDocs failure the event is still recorded: the file stays durably
     * staged (private, access-controlled) and is marked pending for retry.
     * We NEVER claim success before tDocs confirms it.
     */
    protected function stageAndUploadPhoto(UploadedFile $file, Employee $employee, string $type, User $actor): StoredFile
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $stagedName = 'staging/' . date('Y/m') . '/' . Str::uuid() . '.' . $ext;
        $contents = file_get_contents($file->getRealPath());

        Storage::disk('local')->put($stagedName, $contents);

        $storedFile = StoredFile::create([
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'folder' => 'attendance',
            'status' => StoredFile::STATUS_PENDING,
            'uploaded_by' => $actor->id,
            'tdocs_folder_path' => "attendance/{$employee->employee_code}/" . date('Y/m'),
            'staged_path' => $stagedName,
        ]);

        try {
            $tmp = tempnam(sys_get_temp_dir(), 'absen');
            file_put_contents($tmp, $contents);
            // Fast inline attempt (short timeout, no retries): a dead tDocs
            // must never block the attendance request; the queue worker retries.
            $result = $this->tdocs->withTimeout(8)->withoutRetries()->uploadFile(
                $tmp,
                $storedFile->tdocs_folder_path,
                $employee->employee_code . '-' . strtolower($type) . '-' . time() . '.' . $ext
            );
            @unlink($tmp);
            $storedFile->update([
                'tdocs_file_id' => $result['file_id'],
                'status' => StoredFile::STATUS_UPLOADED,
                'attempts' => 1,
                'staged_path' => null,
            ]);
            // Evidence durably stored in tDocs: staging copy can be removed.
            Storage::disk('local')->delete($stagedName);
        } catch (\Throwable $e) {
            report($e);
            $storedFile->update([
                'status' => StoredFile::STATUS_PENDING,
                'attempts' => 1,
                'last_error' => substr($e->getMessage(), 0, 500),
            ]);
            // Keep the staged copy for the retry worker. Schedule retry.
            \App\Jobs\RetryTdocsUpload::dispatch($storedFile->id)->delay(now()->addMinutes(2));
        }

        return $storedFile->fresh();
    }

    /**
     * @return array{status: string, reason: string|null}
     */
    protected function checkGeofence(Employee $employee, float $lat, float $lng): array
    {
        $policy = SystemSetting::get('gps_policy', 'enforce'); // enforce | warn | off
        if ($policy === 'off') {
            return ['status' => 'verified', 'reason' => null];
        }

        $sites = $employee->workAssignments()
            ->whereNull('unassigned_at')
            ->with('workSite')
            ->get()
            ->map(fn ($a) => $a->workSite)
            ->filter(fn ($s) => $s && $s->geofence_enabled);

        if ($sites->isEmpty()) {
            // No location restriction for this employee (mobile field assignment).
            return ['status' => 'verified', 'reason' => null];
        }

        foreach ($sites as $site) {
            $check = GeofenceService::checkSite($lat, $lng, [
                'latitude' => (float) $site->latitude,
                'longitude' => (float) $site->longitude,
                'radius_meters' => $site->radius_meters,
                'geofence_enabled' => true,
            ]);
            if ($check['inside']) {
                return ['status' => 'verified', 'reason' => null];
            }
        }

        $reason = 'Di luar radius lokasi kerja yang diizinkan.';
        if ($policy === 'enforce') {
            throw new AttendanceValidationException('geofence_rejected', $reason . ' Absensi ditolak.');
        }
        return ['status' => 'flagged', 'reason' => $reason];
    }

    // ------------------------------------------------------------------
    // Summaries & status
    // ------------------------------------------------------------------

    public function scheduleFor(Employee $employee, string $workDate): ?WorkSchedule
    {
        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');
        $dow = (int) CarbonImmutable::parse($workDate, $tz)->dayOfWeek;
        return WorkSchedule::where('employee_id', $employee->id)
                ->where('day_of_week', $dow)->where('active', true)->first()
            ?? WorkSchedule::whereNull('employee_id')
                ->where('day_of_week', $dow)->where('active', true)->first();
    }

    /**
     * Per-session payroll summaries for a period. Only closed sessions count.
     * @return array<int, array{work_date: string, total_minutes: int, overtime_minutes: int, is_late: bool, has_clock_out: bool}>
     */
    public function sessionSummaries(Employee $employee, PayrollPeriod $period): array
    {
        $sessions = AttendanceSession::where('employee_id', $employee->id)
            ->where('status', AttendanceSession::STATUS_CLOSED)
            ->whereDate('work_date', '>=', $period->starts_on->format('Y-m-d'))
            ->whereDate('work_date', '<=', $period->ends_on->format('Y-m-d'))
            ->with('events')
            ->get();

        $out = [];
        foreach ($sessions as $session) {
            $masuk = $session->events->firstWhere('event_type', AttendanceEvent::TYPE_MASUK);
            $keluar = $session->events->firstWhere('event_type', AttendanceEvent::TYPE_KELUAR);
            if (!$masuk || !$keluar) {
                continue;
            }
            $schedule = $this->scheduleFor($employee, $session->work_date->format('Y-m-d'));
            $hours = WorkingHoursService::compute(
                CarbonImmutable::parse($masuk->server_at)->utc(),
                CarbonImmutable::parse($keluar->server_at)->utc(),
                $schedule?->break_minutes ?? (int) SystemSetting::get('break_minutes_default', 60),
                $schedule ? [
                    'start' => substr($schedule->start_time, 0, 5),
                    'end' => substr($schedule->end_time, 0, 5),
                    'overnight' => (bool) $schedule->overnight,
                    'late_tolerance_min' => (int) SystemSetting::get('late_tolerance_minutes', 15),
                ] : null,
                (int) SystemSetting::get('overtime_threshold_minutes', 480),
            );
            $out[] = [
                'work_date' => $session->work_date->format('Y-m-d'),
                'total_minutes' => $hours['total_minutes'],
                'overtime_minutes' => $hours['overtime_minutes'],
                'is_late' => $hours['is_late'],
                'has_clock_out' => true,
            ];
        }
        return $out;
    }

    /**
     * Today's state for the PWA home screen.
     */
    public function todayStatus(Employee $employee): array
    {
        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');
        $today = now($tz)->format('Y-m-d');

        $session = AttendanceSession::where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->with('events')
            ->first();

        if (!$session) {
            return ['state' => 'belum', 'session' => null];
        }

        $masuk = $session->events->firstWhere('event_type', AttendanceEvent::TYPE_MASUK);
        $keluar = $session->events->firstWhere('event_type', AttendanceEvent::TYPE_KELUAR);

        if ($masuk && !$keluar) {
            $elapsed = (int) CarbonImmutable::parse($masuk->server_at)->utc()
                ->diffInMinutes(CarbonImmutable::now('UTC'), false);
            return [
                'state' => 'bekerja',
                'session' => $session,
                'clock_in_at' => $masuk->server_at,
                'elapsed_minutes' => max(0, $elapsed),
            ];
        }

        if ($masuk && $keluar) {
            return [
                'state' => 'selesai',
                'session' => $session,
                'clock_in_at' => $masuk->server_at,
                'clock_out_at' => $keluar->server_at,
                'total_minutes' => $session->total_minutes,
            ];
        }

        return ['state' => 'belum', 'session' => $session];
    }
}
