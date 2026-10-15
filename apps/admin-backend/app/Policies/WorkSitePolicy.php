<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSite;

class WorkSitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.manage') || $user->hasPermission('attendance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }

    public function update(User $user, WorkSite $site): bool
    {
        return $user->hasPermission('settings.manage');
    }

    public function delete(User $user, WorkSite $site): bool
    {
        return $user->hasPermission('settings.manage');
    }
}
