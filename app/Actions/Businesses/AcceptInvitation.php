<?php

namespace App\Actions\Businesses;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AcceptInvitation
{
    public function handle(BusinessInvitation $invitation, User $user): Business
    {
        abort_unless($invitation->isPending(), 410, 'This invitation has expired or has already been used.');
        abort_unless(Str::lower($user->email) === $invitation->email, 403, 'This invitation was sent to a different email address.');

        return DB::transaction(function () use ($invitation, $user): Business {
            $business = $invitation->business;

            $business->members()->syncWithoutDetaching([
                $user->id => ['role' => $invitation->role->value],
            ]);

            $invitation->update(['accepted_at' => now()]);
            $user->switchBusiness($business);

            AuditLog::record('member.joined', $business, ['role' => $invitation->role->value]);

            return $business;
        });
    }
}
