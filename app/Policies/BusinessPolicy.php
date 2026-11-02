<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $user->belongsToBusiness($business);
    }

    public function update(User $user, Business $business): bool
    {
        return $user->hasRoleIn($business, BusinessRole::Owner);
    }

    public function inviteMembers(User $user, Business $business): bool
    {
        return $user->roleIn($business)?->canManage() ?? false;
    }

    public function manageMembers(User $user, Business $business): bool
    {
        return $user->hasRoleIn($business, BusinessRole::Owner);
    }

    public function delete(User $user, Business $business): bool
    {
        return $user->hasRoleIn($business, BusinessRole::Owner);
    }
}
