<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ExtractionFailedException extends DocuMindException
{
    /** @param list<string> $fields */
    public static function missingFields(array $fields): self
    {
        return new self('Cannot create record, missing: '.implode(', ', $fields));
    }
}
