<?php

use App\Actions\Documents\UploadDocument;
use App\Enums\BusinessRole;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('documents');

    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
    $this->actingAs($this->owner);

    $this->document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);
    $this->document->delete();
});

test('managers can restore deleted documents', function () {
    $manager = memberOf($this->business, BusinessRole::Manager);

    Livewire::actingAs($manager)
        ->test('pages::documents.trash')
        ->assertSee('invoice.pdf')
        ->call('restore', $this->document->id)
        ->assertSee('Restored invoice.pdf');

    expect($this->document->fresh()->trashed())->toBeFalse();
});

test('managers cannot delete permanently', function () {
    $manager = memberOf($this->business, BusinessRole::Manager);

    Livewire::actingAs($manager)
        ->test('pages::documents.trash')
        ->call('purge', $this->document->id)
        ->assertForbidden();

    expect(Document::withTrashed()->find($this->document->id))->not->toBeNull();
});

test('owners can delete permanently, which removes the file', function () {
    $path = $this->document->path;

    Livewire::actingAs($this->owner)
        ->test('pages::documents.trash')
        ->call('purge', $this->document->id)
        ->assertSee('Permanently deleted invoice.pdf');

    expect(Document::withTrashed()->find($this->document->id))->toBeNull()
        ->and(AuditLog::where('action', 'document.purged')->exists())->toBeTrue();

    Storage::disk('documents')->assertMissing($path);
});

test('employees cannot open the trash', function () {
    $employee = memberOf($this->business, BusinessRole::Employee);

    $this->actingAs($employee)->get(route('documents.trash'))->assertForbidden();
});

test('another business cannot restore this document', function () {
    $outsider = Business::factory()->create()->owner;

    Livewire::actingAs($outsider)
        ->test('pages::documents.trash')
        ->assertDontSee('invoice.pdf')
        ->call('restore', $this->document->id)
        ->assertNotFound();
});
