<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Contracts\InvoiceExtractor;
use App\Enums\DocumentType;
use App\Exceptions\InvalidMoneyException;
use App\ValueObjects\Money;
use DateTimeImmutable;

final class RegexInvoiceExtractor implements InvoiceExtractor
{
    /** Captures a currency code and an amount, e.g. "KES 13,660.16". */
    private const string AMOUNT = '([A-Z]{3})\s*([\d,]+(?:\.\d{1,2})?)';

    public function extract(string $text, DocumentType $type): ExtractionResult
    {
        return new ExtractionResult(
            type: $type,
            supplier: $this->first('/^(?:supplier|vendor|from)\s*:\s*(.+)$/im', $text),
            documentNumber: $this->first('/^(?:invoice|receipt)\s*(?:no\.?|number|#)\s*:\s*(\S+)/im', $text),
            issueDate: $this->date('/^(?:date|issue date|invoice date)\s*:\s*(\d{4}-\d{2}-\d{2})/im', $text),
            dueDate: $this->date('/^due date\s*:\s*(\d{4}-\d{2}-\d{2})/im', $text),
            total: $this->money('/^(?:total|grand total|amount due)\s*:\s*'.self::AMOUNT.'/im', $text),
            tax: $this->money('/^(?:vat|tax)[^:\n]*:\s*'.self::AMOUNT.'/im', $text),
        );
    }

    private function first(string $pattern, string $text): ?string
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1]);

        return $value === '' ? null : $value;
    }

    private function date(string $pattern, string $text): ?DateTimeImmutable
    {
        $raw = $this->first($pattern, $text);

        if ($raw === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return $date === false ? null : $date;
    }

    private function money(string $pattern, string $text): ?Money
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        try {
            return Money::fromDecimal($matches[2], $matches[1]);
        } catch (InvalidMoneyException) {
            return null;
        }
    }
}
