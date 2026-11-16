<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\Document;
use Livewire\Livewire;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
    $this->employee = memberOf($this->business, BusinessRole::Employee);
    $this->category = Category::factory()->for($this->business)->create(['name' => 'Utilities']);
});

test('the url restores the filters', function () {
    Document::factory()->for($this->business)->create(['original_name' => 'fuel-001.pdf']);
    Document::factory()->for($this->business)->create(['original_name' => 'airtime-001.pdf']);

    Livewire::withQueryParams(['search' => 'fuel'])
        ->actingAs($this->owner)
        ->test('pages::documents.index')
        ->assertSet('search', 'fuel')
        ->assertSee('fuel-001.pdf')
        ->assertDontSee('airtime-001.pdf');
});

test('sorting toggles direction on the same column', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::documents.index')
        ->call('sortBy', 'size_bytes')
        ->assertSet('sort', 'size_bytes')
        ->assertSet('direction', 'desc')
        ->call('sortBy', 'size_bytes')
        ->assertSet('direction', 'asc')
        ->call('sortBy', 'password')
        ->assertSet('sort', 'size_bytes');
});

test('bulk move skips documents the user cannot edit', function () {
    $mine = Document::factory()->for($this->business)->create(['uploaded_by' => $this->employee->id, 'category_id' => null]);
    $theirs = Document::factory()->for($this->business)->create(['uploaded_by' => $this->owner->id, 'category_id' => null]);

    Livewire::actingAs($this->employee)
        ->test('pages::documents.index')
        ->set('selected', [(string) $mine->id, (string) $theirs->id])
        ->set('bulkCategory', (string) $this->category->id)
        ->call('moveSelected')
        ->assertSee('Moved 1 document.')
        ->assertSee('1 skipped')
        ->assertSet('selected', []);

    expect($mine->fresh()->category_id)->toBe($this->category->id)
        ->and($theirs->fresh()->category_id)->toBeNull();
});

test('bulk move cannot target another business\'s category', function () {
    $document = Document::factory()->for($this->business)->create(['uploaded_by' => $this->owner->id]);
    $foreign = Category::factory()->for(Business::factory())->create();

    Livewire::actingAs($this->owner)
        ->test('pages::documents.index')
        ->set('selected', [(string) $document->id])
        ->set('bulkCategory', (string) $foreign->id)
        ->call('moveSelected')
        ->assertNotFound();
});

test('employees cannot bulk delete', function () {
    $documents = Document::factory()->count(2)->for($this->business)->create(['uploaded_by' => $this->employee->id]);

    Livewire::actingAs($this->employee)
        ->test('pages::documents.index')
        ->set('selected', $documents->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('deleteSelected')
        ->assertSee('Deleted 0 documents.');

    expect(Document::count())->toBe(2);
});

test('owners can bulk delete', function () {
    $documents = Document::factory()->count(3)->for($this->business)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::documents.index')
        ->call('selectPage')
        ->assertCount('selected', 3)
        ->call('deleteSelected')
        ->assertSee('Deleted 3 documents.');

    expect(Document::count())->toBe(0)
        ->and(Document::onlyTrashed()->count())->toBe(3);
});
