<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;

class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.generate')
            || $user->hasPermission('payroll.finalize')
            || $user->hasPermission('reports.view');
    }

    public function view(User $user, PayrollRun $run): bool
    {
        return $this->viewAny($user);
    }

    public function configure(User $user): bool
    {
        return $user->hasPermission('payroll.configure');
    }

    public function generate(User $user): bool
    {
        return $user->hasPermission('payroll.generate');
    }

    public function finalize(User $user, PayrollRun $run): bool
    {
        return $user->hasPermission('payroll.finalize') && $run->isEditable();
    }

    public function publish(User $user, PayrollRun $run): bool
    {
        return $user->hasPermission('payroll.publish')
            && $run->status === PayrollRun::STATUS_FINALIZED;
    }

    public function lock(User $user, PayrollRun $run): bool
    {
        return $user->hasPermission('payroll.lock')
            && $run->status === PayrollRun::STATUS_FINALIZED;
    }
}
