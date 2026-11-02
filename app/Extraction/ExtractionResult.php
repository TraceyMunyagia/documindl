<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Enums\DocumentType;
use App\ValueObjects\Money;
use DateTimeImmutable;

final readonly class ExtractionResult
{
    public function __construct(
        public DocumentType $type,
        public ?string $supplier = null,
        public ?string $documentNumber = null,
        public ?DateTimeImmutable $issueDate = null,
        public ?DateTimeImmutable $dueDate = null,
        public ?Money $total = null,
        public ?Money $tax = null,
    ) {}

    /** @return list<string> */
    public function missingFields(): array
    {
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
}
