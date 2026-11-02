<?php

use App\Models\Business;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    Business::factory()->for($user, 'owner')->create();
    $this->actingAs($user->refresh());

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
