<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Business;
use App\Models\Category;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('documents');
    Queue::fake(); // these tests cover uploading; processing has its own tests

    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
});

test('members can upload PDFs and images', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::documents.upload')
        ->set('incoming', [fakePdf(), UploadedFile::fake()->image('receipt.jpg', 800, 1200)])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('documents.index'));

    $documents = Document::all();
    expect($documents)->toHaveCount(2);

    $pdf = $documents->firstWhere('original_name', 'invoice.pdf');

    expect($pdf->status)->toBe(DocumentStatus::Pending)
        ->and($pdf->uploaded_by)->toBe($this->owner->id)
        ->and($pdf->business_id)->toBe($this->business->id)
        ->and($pdf->checksum)->toHaveLength(64)
        ->and($pdf->path)->toStartWith("businesses/{$this->business->id}/documents/")
        ->and($pdf->path)->not->toContain('invoice.pdf');

    Storage::disk('documents')->assertExists($pdf->path);

    Queue::assertPushed(ProcessDocument::class, 2);
});

test('disallowed file types are rejected', function (string $name) {
    Livewire::actingAs($this->owner)
        ->test('pages::documents.upload')
        ->set('incoming', [UploadedFile::fake()->create($name, 10)])
        ->assertHasErrors('incoming.0');

    expect(Document::count())->toBe(0);
})->with(['notes.docx', 'script.php', 'archive.zip', 'page.html']);

test('files over the size limit are rejected', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::documents.upload')
        ->set('incoming', [UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf')])
        ->assertHasErrors('incoming.0');
});

test('the same file cannot be uploaded twice', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::documents.upload')
        ->set('incoming', [fakePdf()])
        ->call('save');

    Livewire::actingAs($this->owner)
        ->test('pages::documents.upload')
        ->set('incoming', [fakePdf('copy-of-invoice.pdf')])
        ->call('save')
        ->assertNoRedirect()
        ->assertSee('already uploaded');

    expect(Document::count())->toBe(1);
});

test('a category from another business is rejected', function () {
    $foreignCategory = Category::factory()->for(Business::factory())->create();

    Livewire::actingAs($this->owner)
        ->test('pages::documents.upload')
        ->set('incoming', [fakePdf()])
        ->set('categoryId', $foreignCategory->id)
        ->call('save')
        ->assertHasErrors('categoryId');

    expect(Document::count())->toBe(0);
});

test('saving with nothing queued shows an error', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::documents.upload')
        ->call('save')
        ->assertHasErrors('files');
});
