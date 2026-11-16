<?php

use App\Actions\Documents\UploadDocument;
use App\Enums\BusinessRole;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('documents');

    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
    $this->actingAs($this->owner);

    $this->document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);
});

function memberOf(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    $business->members()->attach($user->id, ['role' => $role->value]);
    $user->switchBusiness($business);

    return $user;
}

test('members can preview the file inline with safe headers', function () {
    $this->get(route('documents.file', $this->document))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('downloads use the original filename and are audited', function () {
    $this->get(route('documents.download', $this->document))
        ->assertOk()
        ->assertDownload('invoice.pdf');

    expect(AuditLog::where('action', 'document.downloaded')->count())->toBe(1);
});

test('guests are sent to the login page', function () {
    auth()->logout();

    $this->get(route('documents.file', $this->document))->assertRedirect(route('login'));
});

test('other businesses get a 404 for the page and the file', function () {
    $outsider = Business::factory()->create()->owner;

    $this->actingAs($outsider)->get(route('documents.show', $this->document))->assertNotFound();
    $this->actingAs($outsider)->get(route('documents.file', $this->document))->assertNotFound();
    $this->actingAs($outsider)->get(route('documents.download', $this->document))->assertNotFound();
});

test('employees cannot delete documents', function () {
    $employee = memberOf($this->business, BusinessRole::Employee);

    Livewire::actingAs($employee)
        ->test('pages::documents.show', ['document' => $this->document])
        ->call('delete')
        ->assertForbidden();

    expect($this->document->fresh()->trashed())->toBeFalse();
});

test('managers can delete documents, which keeps the file for recovery', function () {
    $manager = memberOf($this->business, BusinessRole::Manager);

    Livewire::actingAs($manager)
        ->test('pages::documents.show', ['document' => $this->document])
        ->call('delete')
        ->assertRedirect(route('documents.index'));

    expect($this->document->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::where('action', 'document.deleted')->exists())->toBeTrue();

    Storage::disk('documents')->assertExists($this->document->path);
});

test('deleted documents can no longer be opened', function () {
    $this->document->delete();

    $this->get(route('documents.show', $this->document))->assertNotFound();
    $this->get(route('documents.file', $this->document))->assertNotFound();
});
