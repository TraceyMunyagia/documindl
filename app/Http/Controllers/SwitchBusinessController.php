<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SwitchBusinessController extends Controller
{
    public function __invoke(Request $request, Business $business): RedirectResponse
    {
        Gate::authorize('view', $business);

        $request->user()->switchBusiness($business);

        return redirect()->route('dashboard')->with('status', "Switched to {$business->name}.");
    }
}
