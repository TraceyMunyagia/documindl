<?php

use App\Enums\BusinessRole;
use App\Enums\DocumentStatus;
use App\Models\Business;
use App\Models\Document;
use App\Review\ReviewStats;
use Livewire\Livewire;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
    $this->employee = memberOf($this->business, BusinessRole::Employee);
    $this->actingAs($this->owner);
});

test('the queue lists waiting documents, oldest first', function () {
    $newer = Document::factory()->for($this->business)->reviewRequired()->create(['original_name' => 'newer.pdf', 'created_at' => now()->subHour()]);
    $older = Document::factory()->for($this->business)->reviewRequired()->create(['original_name' => 'older.pdf', 'created_at' => now()->subDay()]);
    Document::factory()->for($this->business)->create(['original_name' => 'done.pdf', 'status' => DocumentStatus::Completed]);

    Livewire::test('pages::review.index')
        ->assertSeeInOrder(['older.pdf', 'newer.pdf'])
        ->assertDontSee('done.pdf');
});

test('employees only see their own uploads in the queue', function () {
    Document::factory()->for($this->business)->reviewRequired()->create(['uploaded_by' => $this->employee->id]);
    Document::factory()->for($this->business)->reviewRequired()->create(['uploaded_by' => $this->owner->id]);

    expect(Document::query()->reviewableBy($this->owner)->count())->toBe(2)
        ->and(Document::query()->reviewableBy($this->employee)->count())->toBe(1);
});

test('another business\'s documents never appear', function () {
    Document::factory()->for(Business::factory())->reviewRequired()->create(['original_name' => 'theirs.pdf']);

    Livewire::test('pages::review.index')->assertDontSee('theirs.pdf');
});

test('accuracy stats summarise what reviewers corrected', function () {
    foreach ([[], [], ['supplier_name' => ['ai' => 'A', 'final' => 'B']], ['total_cents' => ['ai' => 1, 'final' => 2], 'supplier_name' => ['ai' => 'C', 'final' => 'D']]] as $i => $corrections) {
        $document = Document::factory()->for($this->business)->create(['status' => DocumentStatus::Completed]);
        aiExtraction($document)->update([
            'verified_at' => now(),
            'corrections' => $corrections,
            'review_seconds' => [20, 40, 60, 80][$i],
        ]);
    }

    $stats = app(ReviewStats::class)->lastDays();

    expect($stats['reviewed'])->toBe(4)
        ->and($stats['unchanged_percent'])->toBe(50)
        ->and($stats['median_seconds'])->toBe(50)
        ->and($stats['field_rates'])->toBe(['supplier_name' => 50, 'total_cents' => 25]);
});
