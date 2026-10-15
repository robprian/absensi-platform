<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\StoredFile;
use App\Models\SystemSetting;
use App\Models\WorkAssignment;
use App\Models\WorkSchedule;
use App\Models\WorkSite;
use App\Services\AuditService;
use App\Services\TdocsException;
use App\Services\TdocsService;
use App\Services\WorkingHoursService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    // ---------------------------------------------------------------
    // Work sites & schedules (Jadwal)
    // ---------------------------------------------------------------

    public function sites()
    {
        $this->authorize('viewAny', WorkSite::class);

        $sites = WorkSite::orderBy('name')->get();
        $schedules = WorkSchedule::with('employee')->orderBy('day_of_week')->get();
        $employees = Employee::where('employment_status', 'aktif')->orderBy('name')->get();
        $assignments = WorkAssignment::with(['employee', 'workSite'])
            ->whereNull('unassigned_at')->orderByDesc('assigned_at')->get();

        return view('admin.sites.index', compact('sites', 'schedules', 'employees', 'assignments'));
    }

    public function storeSite(Request $request)
    {
        $this->authorize('create', WorkSite::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:10', 'max:10000'],
            'geofence_enabled' => ['nullable', 'boolean'],
        ]);

        $site = WorkSite::create($data + [
            'radius_meters' => $data['radius_meters'] ?? 100,
            'geofence_enabled' => $request->boolean('geofence_enabled', true),
        ]);
        AuditService::log('worksite.created', $request->user(), 'work_sites', (string) $site->id);

        return back()->with('ok', 'Lokasi kerja ditambahkan.');
    }

    public function updateSite(Request $request, WorkSite $workSite)
    {
        $this->authorize('update', $workSite);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:10', 'max:10000'],
        ]);

        $before = $workSite->toArray();
        $workSite->update($data + ['geofence_enabled' => $request->boolean('geofence_enabled')]);
        AuditService::log('worksite.updated', $request->user(), 'work_sites', (string) $workSite->id, $before);

        return back()->with('ok', 'Lokasi kerja diperbarui.');
    }

    public function destroySite(Request $request, WorkSite $workSite)
    {
        $this->authorize('delete', $workSite);
        $workSite->delete();
        AuditService::log('worksite.deleted', $request->user(), 'work_sites', (string) $workSite->id);

        return back()->with('ok', 'Lokasi kerja dihapus.');
    }

    public function assign(Request $request)
    {
        $this->authorize('create', WorkSite::class);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_site_id' => ['nullable', 'exists:work_sites,id'],
        ]);

        WorkAssignment::where('employee_id', $data['employee_id'])
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        if (!empty($data['work_site_id'])) {
            WorkAssignment::create([
                'employee_id' => $data['employee_id'],
                'work_site_id' => $data['work_site_id'],
                'assigned_at' => now(),
            ]);
        }

        AuditService::log('worksite.assigned', $request->user(), 'work_assignments', (string) $data['employee_id']);

        return back()->with('ok', 'Penugasan diperbarui.');
    }

    public function storeSchedule(Request $request)
    {
        $this->authorize('create', WorkSite::class);

        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'shift_name' => ['nullable', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
        ]);

        $schedule = WorkSchedule::updateOrCreate(
            ['employee_id' => $data['employee_id'] ?? null, 'day_of_week' => $data['day_of_week']],
            $data + [
                'overnight' => $request->boolean('overnight'),
                'break_minutes' => $data['break_minutes'] ?? 60,
                'active' => true,
            ]
        );

        AuditService::log('schedule.saved', $request->user(), 'work_schedules', (string) $schedule->id);

        return back()->with('ok', 'Jadwal disimpan.');
    }

    // ---------------------------------------------------------------
    // System settings (Pengaturan)
    // ---------------------------------------------------------------

    public function settings(Request $request)
    {
        $this->authorize('viewAny', SystemSetting::class);
        $grouped = SystemSetting::orderBy('group_name')->orderBy('key')->get()->groupBy('group_name');

        return view('admin.settings.index', compact('grouped'));
    }

    public function updateSettings(Request $request)
    {
        $this->authorize('update', SystemSetting::class);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'exists:system_settings,key'],
            'settings.*.value' => ['nullable'],
        ]);

        foreach ($data['settings'] as $item) {
            $setting = SystemSetting::where('key', $item['key'])->firstOrFail();
            $before = $setting->value;

            $value = match ($setting->value_type) {
                'integer' => (string) (int) $item['value'],
                'boolean' => isset($item['value']) && $item['value'] ? '1' : '0',
                'json' => json_encode($item['value']),
                default => (string) $item['value'],
            };

            if ($setting->key === 'gps_policy' && !in_array($value, ['enforce', 'warn', 'off'], true)) {
                return back()->with('err', 'Nilai gps_policy tidak valid.');
            }

            $setting->update(['value' => $value]);
            Cache::forget("setting:{$setting->key}");

            AuditService::log(
                'setting.updated', $request->user(), 'system_settings', (string) $setting->id,
                ['key' => $setting->key, 'value' => $before],
                ['key' => $setting->key, 'value' => $value]
            );
        }

        return back()->with('ok', 'Pengaturan disimpan.');
    }

    // ---------------------------------------------------------------
    // Audit log
    // ---------------------------------------------------------------

    public function auditLogs(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::with('actor')
            ->when($request->action, fn ($q, $a) => $q->where('action', 'like', "%$a%"))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }

    // ---------------------------------------------------------------
    // Documents & storage
    // ---------------------------------------------------------------

    public function documents()
    {
        $files = StoredFile::with('uploader')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.documents.index', compact('files'));
    }

    public function tdocsHealth(TdocsService $tdocs)
    {
        try {
            $health = $tdocs->healthCheck();
            return back()->with('ok', 'tDocs sehat: ' . json_encode($health));
        } catch (TdocsException $e) {
            return back()->with('err', 'tDocs tidak sehat: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Reports (Laporan)
    // ---------------------------------------------------------------

    public function reports(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

        $rows = AttendanceSession::with('employee')
            ->whereDate('work_date', '>=', $dateFrom)
            ->whereDate('work_date', '<=', $dateTo)
            ->get()
            ->groupBy('employee_id')
            ->map(function ($sessions, $empId) {
                $employee = $sessions->first()->employee;
                $closed = $sessions->where('status', 'closed');
                return [
                    'employee' => $employee,
                    'days' => $sessions->count(),
                    'closed_days' => $closed->count(),
                    'exceptions' => $sessions->where('status', 'exception')->count(),
                    'total_minutes' => (int) $closed->sum('total_minutes'),
                ];
            })
            ->sortBy('employee.name');

        $fmt = fn ($m) => WorkingHoursService::formatMinutes((int) $m);

        return view('admin.reports.index', compact('rows', 'dateFrom', 'dateTo', 'fmt'));
    }
}
