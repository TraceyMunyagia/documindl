<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Notifications\BusinessInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Kilimani Bakery Ltd']);

    $this->manager = User::factory()->create();
    $this->business->members()->attach($this->manager->id, ['role' => BusinessRole::Manager->value]);
    $this->manager->switchBusiness($this->business);

    $this->employee = User::factory()->create();
    $this->business->members()->attach($this->employee->id, ['role' => BusinessRole::Employee->value]);
    $this->employee->switchBusiness($this->business);
});

test('managers can invite employees and an email is sent', function () {
    Notification::fake();

    Livewire::actingAs($this->manager)
        ->test('pages::team.index')
        ->set('email', 'New.Hire@Example.com')
        ->set('role', 'employee')
        ->call('invite')
        ->assertHasNoErrors();

    Notification::assertSentOnDemand(BusinessInvitationNotification::class);

    expect(BusinessInvitation::where('email', 'new.hire@example.com')->exists())->toBeTrue();
});

test('managers cannot invite other managers', function () {
    Notification::fake();

    Livewire::actingAs($this->manager)
        ->test('pages::team.index')
        ->set('email', 'boss@example.com')
        ->set('role', 'manager')
        ->call('invite')
        ->assertHasErrors('role');

    Notification::assertNothingSent();
});

test('employees cannot invite anyone', function () {
    Livewire::actingAs($this->employee)
        ->test('pages::team.index')
        ->set('email', 'someone@example.com')
        ->call('invite')
        ->assertForbidden();
});

test('only the owner can change roles', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::team.index')
        ->call('changeRole', $this->employee->id, 'manager')
        ->assertForbidden();

    Livewire::actingAs($this->business->owner)
        ->test('pages::team.index')
        ->call('changeRole', $this->employee->id, 'manager');

    expect($this->employee->roleIn($this->business))->toBe(BusinessRole::Manager);
});

test('an invited user can accept and joins with the invited role', function () {
    $invitation = BusinessInvitation::create([
        'business_id' => $this->business->id,
        'email' => 'new.hire@example.com',
        'role' => BusinessRole::Employee,
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
    ]);

    $newHire = User::factory()->create(['email' => 'new.hire@example.com']);

    $this->actingAs($newHire)
        ->get(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard'));

    expect($newHire->roleIn($this->business))->toBe(BusinessRole::Employee)
        ->and($newHire->fresh()->current_business_id)->toBe($this->business->id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('an invitation cannot be used by a different email address', function () {
    $invitation = BusinessInvitation::create([
        'business_id' => $this->business->id,
        'email' => 'new.hire@example.com',
        'role' => BusinessRole::Employee,
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs(User::factory()->create(['email' => 'someone.else@example.com']))
        ->get(route('invitations.accept', $invitation->token))
        ->assertForbidden();
});

test('expired invitations are rejected', function () {
    $invitation = BusinessInvitation::create([
        'business_id' => $this->business->id,
        'email' => 'late@example.com',
        'role' => BusinessRole::Employee,
        'token' => Str::random(64),
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs(User::factory()->create(['email' => 'late@example.com']))
        ->get(route('invitations.accept', $invitation->token))
        ->assertStatus(410);
});
