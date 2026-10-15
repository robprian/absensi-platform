<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkAssignment;
use App\Models\WorkSchedule;
use App\Models\WorkSite;
use App\Services\AuditService;
use Illuminate\Http\Request;

class WorkSiteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', WorkSite::class);
        return response()->json(WorkSite::orderBy('name')->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', WorkSite::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:10', 'max:10000'],
            'geofence_enabled' => ['boolean'],
        ]);

        $site = WorkSite::create($data);
        AuditService::log('worksite.created', $request->user(), 'work_sites', (string) $site->id);

        return response()->json($site, 201);
    }

    public function update(Request $request, WorkSite $workSite)
    {
        $this->authorize('update', $workSite);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:10', 'max:10000'],
            'geofence_enabled' => ['boolean'],
        ]);

        $before = $workSite->toArray();
        $workSite->update($data);
        AuditService::log('worksite.updated', $request->user(), 'work_sites', (string) $workSite->id, $before);

        return response()->json($workSite->fresh());
    }

    public function destroy(Request $request, WorkSite $workSite)
    {
        $this->authorize('delete', $workSite);
        $workSite->delete();
        AuditService::log('worksite.deleted', $request->user(), 'work_sites', (string) $workSite->id);

        return response()->json(['message' => 'Lokasi kerja dihapus.']);
    }

    // ---------------------------------------------------------------
    // Assignments & schedules
    // ---------------------------------------------------------------

    public function assign(Request $request)
    {
        $this->authorize('create', WorkSite::class);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_site_id' => ['nullable', 'exists:work_sites,id'],
            'notes' => ['nullable', 'string'],
        ]);

        // Close any open assignment first.
        WorkAssignment::where('employee_id', $data['employee_id'])
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        $assignment = null;
        if (!empty($data['work_site_id'])) {
            $assignment = WorkAssignment::create([
                'employee_id' => $data['employee_id'],
                'work_site_id' => $data['work_site_id'],
                'assigned_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
        }

        AuditService::log('worksite.assigned', $request->user(), 'work_assignments', (string) ($assignment?->id ?? 0));

        return response()->json(['message' => 'Penugasan diperbarui.', 'assignment' => $assignment]);
    }

    public function schedules(Request $request)
    {
        $this->authorize('viewAny', WorkSite::class);

        $q = WorkSchedule::with('employee')
            ->when($request->employee_id, fn ($qq, $id) => $qq->where('employee_id', $id))
            ->orderBy('day_of_week');
        return response()->json($q->paginate(30));
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
            'overnight' => ['boolean'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'active' => ['boolean'],
        ]);

        $schedule = WorkSchedule::updateOrCreate(
            ['employee_id' => $data['employee_id'] ?? null, 'day_of_week' => $data['day_of_week']],
            $data
        );

        AuditService::log('schedule.saved', $request->user(), 'work_schedules', (string) $schedule->id);

        return response()->json($schedule, 201);
    }
}
