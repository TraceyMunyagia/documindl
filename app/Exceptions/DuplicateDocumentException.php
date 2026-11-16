<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Document;

final class DuplicateDocumentException extends DocuMindException
{
    public ?int $existingDocumentId = null;

    public static function matching(Document $existing): self
    {
        $exception = new self("This file was already uploaded as \"{$existing->original_name}\".");
        $exception->existingDocumentId = $existing->getKey();

        return $exception;
    }
}
