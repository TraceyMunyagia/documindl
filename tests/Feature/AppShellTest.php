<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected to the login page', function () {
    get(route('dashboard'))->assertRedirect(route('login'));
});

test('unverified users must verify their email first', function () {
    $user = User::factory()->unverified()->create();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('verified users see the DocuMind app shell', function () {
    $user = User::factory()->create(['name' => 'Amina Otieno']);

    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('DocuMind')
        ->assertSee('Documents')
        ->assertSee('Amina Otieno')
        ->assertSee('KES 0.00');
});

test('sections without routes yet are shown as coming soon', function () {
    actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('Wk 4')
        ->assertSee('Wk 14');
});

test('the landing page offers login and registration', function () {
    get('/')
        ->assertOk()
        ->assertSee('Turn invoices, receipts and contracts into answers')
        ->assertSee(route('login'), false);
});
