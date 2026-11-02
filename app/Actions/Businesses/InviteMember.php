<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessRole;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Notifications\BusinessInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InviteMember
{
    public function handle(Business $business, User $inviter, string $email, BusinessRole $role): BusinessInvitation
    {
        $email = Str::lower(trim($email));

        if ($business->members()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the business.',
            ]);
        }

        // Re-inviting the same email replaces the old pending invite with a fresh token.
        $invitation = $business->invitations()->updateOrCreate(
            ['email' => $email, 'accepted_at' => null],
            [
                'role' => $role,
                'invited_by' => $inviter->id,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
            ],
        );

        Notification::route('mail', $email)->notify(new BusinessInvitationNotification($invitation));

        AuditLog::record('member.invited', $business, ['email' => $email, 'role' => $role->value]);

        return $invitation;
    }
}
