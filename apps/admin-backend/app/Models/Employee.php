<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_code', 'user_id', 'department_id', 'name', 'phone', 'email',
        'position', 'employment_status', 'hire_date', 'notes',
    ];

    protected function casts(): array
    {
        return ['hire_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function compensationRules(): HasMany
    {
        return $this->hasMany(EmployeeCompensationRule::class);
    }

    public function workAssignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function reimbursements(): HasMany
    {
        return $this->hasMany(Reimbursement::class);
    }

    public function isActive(): bool
    {
        return $this->employment_status === 'aktif';
    }
}
