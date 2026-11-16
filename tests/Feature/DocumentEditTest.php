<?php

use App\Enums\BusinessRole;
use App\Enums\DocumentType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Category;
use App\Models\Document;
use Livewire\Livewire;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
    $this->document = Document::factory()->for($this->business)->create([
        'original_name' => 'scan-001.pdf',
        'uploaded_by' => $this->owner->id,
        'type' => DocumentType::Other,
    ]);
});

test('renaming keeps the original extension', function (string $typed, string $expected) {
    Livewire::actingAs($this->owner)
        ->test('pages::documents.show', ['document' => $this->document])
        ->call('startEditing')
        ->set('form.name', $typed)
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($this->document->fresh()->original_name)->toBe($expected);
})->with([
    'plain name' => ['March fuel', 'March fuel.pdf'],
    'already has extension' => ['March fuel.pdf', 'March fuel.pdf'],
    'tries a new extension' => ['March fuel.exe', 'March fuel.exe.pdf'],
]);

test('category, type and notes are saved and audited', function () {
    $category = Category::factory()->for($this->business)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::documents.show', ['document' => $this->document])
        ->call('startEditing')
        ->set('form.categoryId', (string) $category->id)
        ->set('form.type', 'receipt')
        ->set('form.summary', 'Paid in cash')
        ->call('saveDetails');

    $fresh = $this->document->fresh();

    expect($fresh->category_id)->toBe($category->id)
        ->and($fresh->type)->toBe(DocumentType::Receipt)
        ->and($fresh->summary)->toBe('Paid in cash')
        ->and(AuditLog::where('action', 'document.updated')->first()->changes['fields'])
        ->toEqualCanonicalizing(['category_id', 'type', 'summary']);
});

test('a category from another business is rejected', function () {
    $foreign = Category::factory()->for(Business::factory())->create();

    Livewire::actingAs($this->owner)
        ->test('pages::documents.show', ['document' => $this->document])
        ->call('startEditing')
        ->set('form.categoryId', (string) $foreign->id)
        ->call('saveDetails')
        ->assertHasErrors('form.categoryId');
});

test('employees can only edit their own uploads', function () {
    $employee = memberOf($this->business, BusinessRole::Employee);
    $own = Document::factory()->for($this->business)->create(['uploaded_by' => $employee->id]);

    Livewire::actingAs($employee)
        ->test('pages::documents.show', ['document' => $this->document])
        ->call('startEditing')
        ->assertForbidden();

    Livewire::actingAs($employee)
        ->test('pages::documents.show', ['document' => $own])
        ->call('startEditing')
        ->assertSet('editing', true);
});
