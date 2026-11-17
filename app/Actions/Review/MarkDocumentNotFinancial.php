<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\DocumentAlreadyReviewedException;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** For documents that turn out to be a contract or something else: no invoice record. */
final class MarkDocumentNotFinancial
{
    public function handle(Document $document, User $reviewer, DocumentType $type): void
    {
        if ($type->isFinancial()) {
            throw new InvalidArgumentException('Use VerifyDocument for invoices and receipts.');
        }

        DB::transaction(function () use ($document, $reviewer, $type): void {
            $locked = Document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== DocumentStatus::ReviewRequired) {
                throw DocumentAlreadyReviewedException::for($locked);
            }

            $extraction = $locked->latestExtraction;

            $extraction?->update([
                'verified_at' => now(),
                'verified_by' => $reviewer->getKey(),
                'final_fields' => ['document_type' => $type->value],
                'corrections' => $extraction->usedAi() && $extraction->detected_type !== $type
                    ? ['document_type' => ['ai' => $extraction->detected_type->value, 'final' => $type->value]]
                    : [],
            ]);

            $locked->fill(['type' => $type, 'processing_note' => null])->save();
            $locked->transitionTo(DocumentStatus::Completed);

            AuditLog::record('document.marked_not_financial', $locked, ['type' => $type->value]);
        });
    }
}
