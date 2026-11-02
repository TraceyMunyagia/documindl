<?php

namespace App\Http\Controllers;

use App\Actions\Businesses\AcceptInvitation;
use App\Models\BusinessInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptInvitationController extends Controller
{
    public function __invoke(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = BusinessInvitation::query()->where('token', $token)->firstOrFail();

        $business = $acceptInvitation->handle($invitation, $request->user());

        return redirect()->route('dashboard')->with('status', "Welcome to {$business->name}!");
    }
}
