<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\SystemSetting;
use App\Services\AttendanceService;
use App\Services\AttendanceValidationException;
use App\Services\WorkingHoursService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    protected function employeeOf(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee || !$employee->isActive()) {
            abort(403, 'Akun tidak terhubung ke data karyawan aktif.');
        }
        return $employee;
    }

    /**
     * POST /api/v1/attendance/clock-in
     *
     * Accepts the PWA field names (photo, latitude, longitude) as well as
     * legacy names (selfie, lat, lng).
     */
    public function clockIn(Request $request)
    {
        return $this->record($request, AttendanceEvent::TYPE_MASUK);
    }

    /**
     * POST /api/v1/attendance/clock-out
     */
    public function clockOut(Request $request)
    {
        return $this->record($request, AttendanceEvent::TYPE_KELUAR);
    }

    protected function record(Request $request, string $type)
    {
        // Normalize PWA field aliases before validation. Use the raw FileBag
        // (has/get) — Request::file() caches allFiles(), so a lookup before
        // the set() would leave the new key invisible to validation.
        if (!$request->files->has('selfie') && $request->files->has('photo')) {
            $request->files->set('selfie', $request->files->get('photo'));
        }
        if (!$request->input('lat') && $request->input('latitude') !== null) {
            $request->merge(['lat' => $request->input('latitude')]);
        }
        if (!$request->input('lng') && $request->input('longitude') !== null) {
            $request->merge(['lng' => $request->input('longitude')]);
        }

        // Accept the idempotency key via header OR body field.
        if (!$request->input('idempotency_key') && $request->header('Idempotency-Key')) {
            $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        }

                $request->validate([
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie_original' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'captured_at' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'device_info' => ['nullable', 'array'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);

        $employee = $this->employeeOf($request);

        // Namespace the idempotency key per user (also enforced in middleware).
        $key = 'u' . $request->user()->id . ':' . $request->input('idempotency_key');

        try {
            $event = $this->attendance->recordEvent($employee, $request->user(), $type, [
                'selfie' => $request->file('selfie'),
                'selfie_original' => $request->file('selfie_original'),
                'lat' => $request->input('lat'),
                'lng' => $request->input('lng'),
                'accuracy' => $request->input('accuracy'),
                'captured_at' => $request->input('captured_at'),
                'address' => $request->input('address'),
                'device_info' => $request->input('device_info'),
                'idempotency_key' => $key,
                'ip' => $request->ip(),
            ]);
        } catch (AttendanceValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], 422);
        }

        $session = $event->session;
        $masuk = $session->clockIn();
        $keluar = $session->clockOut();

        $payload = [
            'ok' => true,
            'masuk_at' => $masuk?->server_at?->toIso8601String(),
            'keluar_at' => $keluar?->server_at?->toIso8601String(),
            'total_menit' => $session->total_minutes !== null ? (int) $session->total_minutes : null,
            'verification_status' => $event->verification_status,
            'upload_pending' => $event->evidence->contains(
                fn ($ev) => $ev->storedFile && $ev->storedFile->status !== \App\Models\StoredFile::STATUS_UPLOADED
            ),
        ];

        return response()->json($payload, 201);
    }

    /**
     * GET /api/v1/attendance/today — today's state for the PWA home screen.
     * Matches the PWA TodayAttendance contract.
     */
    public function today(Request $request)
    {
        $employee = $this->employeeOf($request);
        $status = $this->attendance->todayStatus($employee);

        $session = $status['session'] ?? null;
        $masuk = $session?->events->firstWhere('event_type', AttendanceEvent::TYPE_MASUK);
        $keluar = $session?->events->firstWhere('event_type', AttendanceEvent::TYPE_KELUAR);

        $state = match ($status['state']) {
            'bekerja' => 'sedang_bekerja',
            'selesai' => 'selesai_bekerja',
            default => 'belum_absen',
        };

        return response()->json([
            'status' => $state,
            'masuk_at' => $masuk?->server_at?->toIso8601String(),
            'keluar_at' => $keluar?->server_at?->toIso8601String(),
            'total_menit' => $session?->total_minutes !== null ? (int) $session->total_minutes : null,
            'lokasi_masuk' => $masuk?->address,
            'ada_antrean' => false,
        ]);
    }

    /**
     * POST /api/v1/attendance/geocode — best-effort reverse geocode.
     * Never fails attendance: ANY failure returns {address: null} (HTTP 200).
     */
    public function geocode(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $address = null;
        try {
            $res = Http::timeout(3)
                ->withHeaders([
                    'User-Agent' => 'AbsensiPlatform/1.0 (reverse-geocode; contact: admin)',
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $data['latitude'],
                    'lon' => $data['longitude'],
                    'format' => 'jsonv2',
                    'zoom' => 16,
                    'addressdetails' => 0,
                ]);

            if ($res->successful()) {
                $display = $res->json('display_name');
                if (is_string($display) && $display !== '') {
                    $address = mb_substr($display, 0, 500);
                }
            }
        } catch (\Throwable) {
            // Best-effort only; swallow everything.
        }

        return response()->json(['address' => $address]);
    }

    /**
     * GET /api/v1/attendance/history?bulan=YYYY-MM — employee history.
     * Matches the PWA AttendanceHistoryItem contract.
     */
    public function history(Request $request)
    {
        $employee = $this->employeeOf($request);

        $data = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $tz = SystemSetting::get('app_timezone', 'Asia/Jakarta');
        $bulan = $data['bulan'] ?? now($tz)->format('Y-m');
        [$year, $month] = explode('-', $bulan);

        $sessions = AttendanceSession::where('employee_id', $employee->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->with(['events', 'workSite'])
            ->orderByDesc('work_date')
            ->get();

        $items = $sessions->map(function ($session) use ($employee) {
            $masuk = $session->events->firstWhere('event_type', AttendanceEvent::TYPE_MASUK);
            $keluar = $session->events->firstWhere('event_type', AttendanceEvent::TYPE_KELUAR);

            return [
                'id' => (string) $session->id,
                'tanggal' => $session->work_date->format('Y-m-d'),
                'masuk_at' => $masuk?->server_at?->toIso8601String(),
                'keluar_at' => $keluar?->server_at?->toIso8601String(),
                'total_menit' => $session->total_minutes !== null ? (int) $session->total_minutes : null,
                'status' => $this->mapHistoryStatus($session, $masuk, $keluar, $employee),
                'lokasi' => $masuk?->address ?? $session->workSite?->name,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Map an attendance session to the PWA history status enum:
     * hadir | terlambat | pulang_cepat | lembur | tidak_lengkap | libur
     */
    protected function mapHistoryStatus($session, $masuk, $keluar, $employee): string
    {
        if (!$masuk) {
            return 'tidak_lengkap';
        }
        if (!$keluar) {
            return 'tidak_lengkap';
        }

        $schedule = $this->attendance->scheduleFor($employee, $session->work_date->format('Y-m-d'));
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

        if ($hours['is_late']) {
            return 'terlambat';
        }
        if ($hours['is_early_departure']) {
            return 'pulang_cepat';
        }
        if ($hours['overtime_minutes'] > 0) {
            return 'lembur';
        }
        return 'hadir';
    }
}
