<?php

use App\Enums\DocumentStatus;

it('allows the normal processing flow', function () {
    expect(DocumentStatus::Pending->canTransitionTo(DocumentStatus::Processing))->toBeTrue()
        ->and(DocumentStatus::Processing->canTransitionTo(DocumentStatus::ReviewRequired))->toBeTrue()
        ->and(DocumentStatus::ReviewRequired->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
});

it('blocks invalid jumps', function (DocumentStatus $from, DocumentStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'pending to completed' => [DocumentStatus::Pending, DocumentStatus::Completed],
    'completed to pending' => [DocumentStatus::Completed, DocumentStatus::Pending],
    'review to failed' => [DocumentStatus::ReviewRequired, DocumentStatus::Failed],
]);

it('has a readable label for every status', function () {
    foreach (DocumentStatus::cases() as $status) {
        expect($status->label())->not->toBeEmpty();
    }
});
