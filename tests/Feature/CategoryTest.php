<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\Document;
use Livewire\Livewire;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->manager = memberOf($this->business, BusinessRole::Manager);
    $this->employee = memberOf($this->business, BusinessRole::Employee);
});

test('managers can create categories', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::categories.index')
        ->set('name', 'Marketing')
        ->set('color', 'rose')
        ->call('create')
        ->assertHasNoErrors();

    expect(Category::where('slug', 'marketing')->first()->color)->toBe('rose');
});

test('category names are unique within a business', function () {
    Category::factory()->for($this->business)->create(['name' => 'Marketing']);

    Livewire::actingAs($this->manager)
        ->test('pages::categories.index')
        ->set('name', 'marketing')
        ->call('create')
        ->assertHasErrors('name');
});

test('the same name is allowed in a different business', function () {
    Category::factory()->for(Business::factory())->create(['name' => 'Marketing']);

    Livewire::actingAs($this->manager)
        ->test('pages::categories.index')
        ->set('name', 'Marketing')
        ->call('create')
        ->assertHasNoErrors();
});

test('colours must be from the allowed list', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::categories.index')
        ->set('name', 'Marketing')
        ->set('color', 'hotpink')
        ->call('create')
        ->assertHasErrors('color');
});

test('employees cannot create categories', function () {
    Livewire::actingAs($this->employee)
        ->test('pages::categories.index')
        ->set('name', 'Sneaky')
        ->call('create')
        ->assertForbidden();
});

test('deleting a category uncategorises its documents', function () {
    $category = Category::factory()->for($this->business)->create();
    $document = Document::factory()->for($this->business)->create(['category_id' => $category->id]);

    Livewire::actingAs($this->manager)
        ->test('pages::categories.index')
        ->call('delete', $category->id)
        ->assertSee('now uncategorised');

    expect(Category::find($category->id))->toBeNull()
        ->and($document->fresh()->category_id)->toBeNull();
});
