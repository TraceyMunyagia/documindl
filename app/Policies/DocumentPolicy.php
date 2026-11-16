<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_business_id !== null;
    }

    public function view(User $user, Document $document): bool
    {
        return $user->belongsToBusiness($document->business_id);
    }

    public function create(User $user): bool
    {
        return $user->current_business_id !== null
            && $user->belongsToBusiness($user->current_business_id);
    }

    /** Owners and managers can edit anything; employees only what they uploaded. */
    public function update(User $user, Document $document): bool
    {
        $role = $user->roleIn($document->business_id);

        if ($role === null) {
            return false;
        }

        return $role->canManage() || $document->uploaded_by === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->roleIn($document->business_id)?->canManage() ?? false;
    }

    public function viewTrash(User $user): bool
    {
        return $user->current_business_id !== null
            && ($user->roleIn($user->current_business_id)?->canManage() ?? false);
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->roleIn($document->business_id)?->canManage() ?? false;
    }

    /** Permanent deletion is owner-only and cannot be undone. */
    public function forceDelete(User $user, Document $document): bool
    {
        return $user->hasRoleIn($document->business_id, BusinessRole::Owner);
    }
}
