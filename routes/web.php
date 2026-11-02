<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\SwitchBusinessController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('businesses/create', 'pages::businesses.create')->name('businesses.create');
    Route::post('businesses/{business}/switch', SwitchBusinessController::class)->name('businesses.switch');
    Route::get('invitations/{token}', AcceptInvitationController::class)->name('invitations.accept');

    // Everything in here needs a current business.
    Route::middleware('business')->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
        Route::livewire('team', 'pages::team.index')->name('team.index');
    });
});

require __DIR__.'/settings.php';
