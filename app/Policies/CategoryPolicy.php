<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_business_id !== null
            && $user->belongsToBusiness($user->current_business_id);
    }

    public function create(User $user): bool
    {
        return $user->current_business_id !== null
            && ($user->roleIn($user->current_business_id)?->canManage() ?? false);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->roleIn($category->business_id)?->canManage() ?? false;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->roleIn($category->business_id)?->canManage() ?? false;
    }
}
