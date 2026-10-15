<?php

namespace App\Policies;

use App\Models\User;

class SystemSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }
}
