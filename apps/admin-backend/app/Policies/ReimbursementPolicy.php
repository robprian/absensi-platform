<?php

namespace App\Policies;

use App\Models\Reimbursement;
use App\Models\User;

class ReimbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // scoped per role in controller (admins see all, employees see own)
    }

    public function view(User $user, Reimbursement $reimbursement): bool
    {
        if ($user->hasPermission('reimbursements.review')) {
            return true;
        }
        return $user->employee?->id === $reimbursement->employee_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('employee') || $user->isAdmin();
    }

    public function review(User $user, Reimbursement $reimbursement): bool
    {
        return $user->hasPermission('reimbursements.review');
    }
}
