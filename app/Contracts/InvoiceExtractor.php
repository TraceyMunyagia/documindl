<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\DocumentType;
use App\Extraction\ExtractionResult;

interface InvoiceExtractor
{
    public function extract(string $text, DocumentType $type): ExtractionResult;
}
