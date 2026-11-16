<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Ai\ExtractionUsage;
use App\Enums\DocumentType;
use App\ValueObjects\Money;
use DateTimeImmutable;

final readonly class ExtractionResult
{
    /**
     * @param  array<string, float>  $confidence  field name => 0.0–1.0
     * @param  list<string>  $warnings
     */
    public function __construct(
        public DocumentType $type,
        public ?string $supplier = null,
        public ?string $documentNumber = null,
        public ?DateTimeImmutable $issueDate = null,
        public ?DateTimeImmutable $dueDate = null,
        public ?Money $total = null,
        public ?Money $tax = null,
        public ?string $summary = null,
        public array $confidence = [],
        public array $warnings = [],
        public ?ExtractionUsage $usage = null,
    ) {}

    /** @return list<string> */
    public function missingFields(): array
    {
        if (! $this->type->isFinancial()) {
            return [];
        }

        $required = [
            'supplier' => $this->supplier,
            'document_number' => $this->documentNumber,
            'issue_date' => $this->issueDate,
            'total' => $this->total,
        ];

        // Receipts are already paid, so only invoices need a due date.
        if ($this->type === DocumentType::Invoice) {
            $required['due_date'] = $this->dueDate;
        }

        return array_keys(array_filter($required, fn (mixed $value): bool => $value === null));
    }

    public function isComplete(): bool
    {
        return $this->missingFields() === [];
    }

    /** The shape stored in document_extractions.fields: plain values, money in cents. */
    public function toFields(): array
    {
        return [
            'supplier_name' => $this->supplier,
            'document_number' => $this->documentNumber,
            'issue_date' => $this->issueDate?->format('Y-m-d'),
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'currency' => $this->total?->currency ?? $this->tax?->currency,
            'total_cents' => $this->total?->amountInCents,
            'tax_cents' => $this->tax?->amountInCents,
        ];
    }
}
