<?php

use App\Actions\Businesses\CreateBusiness;
use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;

beforeEach(function () {
    $this->bakery = Business::factory()->create(['name' => 'Kilimani Bakery Ltd']);
    $this->logistics = Business::factory()->create(['name' => 'Savannah Logistics Ltd']);

    Invoice::factory()->count(3)->for($this->bakery)->create();
    Invoice::factory()->count(2)->for($this->logistics)->create();
    Document::factory()->count(4)->for($this->bakery)->create();
    Document::factory()->count(1)->for($this->logistics)->create();
});

test('queries only return records from the current business', function () {
    $this->actingAs($this->bakery->owner);
    expect(Invoice::count())->toBe(3)
        ->and(Document::count())->toBe(4);

    $this->actingAs($this->logistics->owner);
    expect(Invoice::count())->toBe(2)
        ->and(Document::count())->toBe(1);
});

test('new records are stamped with the current business automatically', function () {
    $this->actingAs($this->bakery->owner);

    $document = Document::create(['original_name' => 'receipt.pdf']);

    expect($document->business_id)->toBe($this->bakery->id);
});

test('policies refuse records from another business', function () {
    $foreignInvoice = Invoice::withoutGlobalScopes()->where('business_id', $this->logistics->id)->first();
    $foreignDocument = Document::withoutGlobalScopes()->where('business_id', $this->logistics->id)->first();

    expect($this->bakery->owner->can('view', $foreignInvoice))->toBeFalse()
        ->and($this->bakery->owner->can('view', $foreignDocument))->toBeFalse()
        ->and($this->logistics->owner->can('view', $foreignInvoice))->toBeTrue();
});

test('only owners and managers can approve invoices', function () {
    $employee = User::factory()->create();
    $this->bakery->members()->attach($employee->id, ['role' => BusinessRole::Employee->value]);

    $invoice = Invoice::withoutGlobalScopes()->where('business_id', $this->bakery->id)->first();

    expect($this->bakery->owner->can('approve', $invoice))->toBeTrue()
        ->and($employee->can('approve', $invoice))->toBeFalse();
});

test('users cannot switch to a business they do not belong to', function () {
    $this->actingAs($this->bakery->owner)
        ->post(route('businesses.switch', $this->logistics))
        ->assertForbidden();
});

test('members can switch between their businesses', function () {
    $owner = $this->bakery->owner;
    $this->logistics->members()->attach($owner->id, ['role' => BusinessRole::Employee->value]);

    $this->actingAs($owner)
        ->post(route('businesses.switch', $this->logistics))
        ->assertRedirect(route('dashboard'));

    expect($owner->fresh()->current_business_id)->toBe($this->logistics->id);
});

test('users without a business are sent to create one', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('businesses.create'));
});

test('creating a business makes you its owner with default categories', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $business = app(CreateBusiness::class)->handle($user, 'Mombasa Spice Traders');

    expect($user->roleIn($business))->toBe(BusinessRole::Owner)
        ->and($user->fresh()->current_business_id)->toBe($business->id)
        ->and($business->categories()->count())->toBe(5)
        ->and($business->slug)->toBe('mombasa-spice-traders');
});
