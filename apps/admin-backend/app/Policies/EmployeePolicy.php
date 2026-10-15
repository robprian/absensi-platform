<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.view') || $user->hasPermission('employees.manage');
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->hasPermission('employees.view') || $user->hasPermission('employees.manage')) {
            return true;
        }
        // Employees may view their own record.
        return $user->employee?->id === $employee->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.manage');
    }
}
