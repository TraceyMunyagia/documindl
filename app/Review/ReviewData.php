<?php

declare(strict_types=1);

namespace App\Review;

use App\Enums\DocumentType;
use App\ValueObjects\Money;
use DateTimeImmutable;

/**
 * What a reviewer confirmed. Only built after validation passes, so every value is trustworthy.
 */
final readonly class ReviewData
{
    public function __construct(
        public DocumentType $type,
        public string $supplier,
        public string $documentNumber,
        public DateTimeImmutable $issueDate,
        public ?DateTimeImmutable $dueDate,
        public Money $total,
        public ?Money $tax,
        public ?int $categoryId,
    ) {}

    /** Same keys as DocumentExtraction::fields, plus the type, so the two can be compared. */
    public function toFields(): array
    {
        return [
            'document_type' => $this->type->value,
            'supplier_name' => $this->supplier,
            'document_number' => $this->documentNumber,
            'issue_date' => $this->issueDate->format('Y-m-d'),
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'currency' => $this->total->currency,
            'total_cents' => $this->total->amountInCents,
            'tax_cents' => $this->tax?->amountInCents,
        ];
    }
}
