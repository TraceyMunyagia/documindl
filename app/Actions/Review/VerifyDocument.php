<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\DocumentAlreadyReviewedException;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\Invoice;
use App\Models\User;
use App\Review\ReviewCorrections;
use App\Review\ReviewData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class VerifyDocument
{
    public function handle(Document $document, User $reviewer, ReviewData $data, ?int $reviewSeconds = null): Invoice
    {
        if (! $data->type->isFinancial()) {
            throw new InvalidArgumentException('Only invoices and receipts are saved as invoice records.');
        }

        return DB::transaction(function () use ($document, $reviewer, $data, $reviewSeconds): Invoice {
            // Lock the row: a second reviewer saving at the same moment waits here,
            // then sees the new status and stops.
            $locked = Document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== DocumentStatus::ReviewRequired) {
                throw DocumentAlreadyReviewedException::for($locked);
            }

            $isReceipt = $data->type === DocumentType::Receipt;

            $invoice = new Invoice([
                'business_id' => $locked->business_id,
                'document_id' => $locked->getKey(),
                'supplier_name' => $data->supplier,
                'invoice_number' => $data->documentNumber,
                'issue_date' => $data->issueDate,
                'due_date' => $isReceipt ? null : $data->dueDate,
                'total' => $data->total,
                'tax' => $data->tax,
                'status' => $isReceipt ? InvoiceStatus::Paid : InvoiceStatus::Pending,
            ]);

            if ($isReceipt) {
                $invoice->forceFill(['paid_at' => $data->issueDate]); // a receipt is proof it was paid
            }

            $invoice->save();

            // Documents with no readable text are typed in by hand: record that as a "manual" extraction.
            $extraction = $locked->latestExtraction ?? DocumentExtraction::create([
                'business_id' => $locked->business_id,
                'document_id' => $locked->getKey(),
                'provider' => 'manual',
                'detected_type' => $data->type,
                'fields' => [],
                'confidence' => [],
                'missing_fields' => [],
                'warnings' => [],
            ]);

            $finalFields = $data->toFields();
            $aiFields = [...$extraction->fields, 'document_type' => $extraction->detected_type->value];

            $extraction->update([
                'verified_at' => now(),
                'verified_by' => $reviewer->getKey(),
                'final_fields' => $finalFields,
                'corrections' => $extraction->provider === 'manual' ? [] : ReviewCorrections::between($aiFields, $finalFields),
                'review_seconds' => $reviewSeconds,
            ]);

            $locked->fill([
                'type' => $data->type,
                'category_id' => $data->categoryId,
                'processing_note' => null,
            ])->save();

            $locked->transitionTo(DocumentStatus::Completed);

            AuditLog::record('document.verified', $locked, [
                'invoice_id' => $invoice->getKey(),
                'provider' => $extraction->provider,
                'corrected_fields' => array_keys($extraction->corrections ?? []),
            ]);
            AuditLog::record('invoice.created', $invoice, ['source' => 'review']);

            return $invoice;
        });
    }
}
