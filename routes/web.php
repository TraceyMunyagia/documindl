<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\DocumentFileController;
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
    Route::livewire('review', 'pages::review.index')->name('review.index');
    Route::livewire('documents/{document}/review', 'pages::review.show')
        ->whereNumber('document')
        ->name('documents.review');
    Route::livewire('documents', 'pages::documents.index')->name('documents.index');
    Route::livewire('documents/upload', 'pages::documents.upload')->name('documents.upload');
    Route::livewire('documents/trash', 'pages::documents.trash')->name('documents.trash');
    Route::livewire('documents/{document}', 'pages::documents.show')
        ->whereNumber('document')
        ->name('documents.show');
    Route::get('documents/{document}/file', [DocumentFileController::class, 'show'])
        ->whereNumber('document')
        ->name('documents.file');
    Route::get('documents/{document}/download', [DocumentFileController::class, 'download'])
        ->whereNumber('document')
        ->name('documents.download');
    Route::livewire('categories', 'pages::categories.index')->name('categories.index');
});

require __DIR__.'/settings.php';
