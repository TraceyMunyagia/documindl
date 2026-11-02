<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_business_id !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->belongsToBusiness($invoice->business_id);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->roleIn($invoice->business_id)?->canManage() ?? false;
    }

    /** Used by the approval workflow in Week 11. */
    public function approve(User $user, Invoice $invoice): bool
    {
        return $user->roleIn($invoice->business_id)?->canManage() ?? false;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRoleIn($invoice->business_id, BusinessRole::Owner);
    }
}
