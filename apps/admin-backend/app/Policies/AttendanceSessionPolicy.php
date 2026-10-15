<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\User;

class AttendanceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view') || $user->hasPermission('attendance.review');
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        if ($user->hasPermission('attendance.view') || $user->hasPermission('attendance.review')) {
            return true;
        }
        return $user->employee?->id === $session->employee_id;
    }

    public function review(User $user): bool
    {
        return $user->hasPermission('attendance.review');
    }
}
