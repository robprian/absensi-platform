<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'label'];

    public const SUPER_ADMIN = 'super_admin';
    public const HR_ADMIN = 'hr_admin';
    public const PAYROLL_OFFICER = 'payroll_officer';
    public const SUPERVISOR = 'supervisor';
    public const EMPLOYEE = 'employee';

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_role_assignments');
    }
}
