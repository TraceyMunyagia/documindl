<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\DocumentStatus;

final class InvalidStatusTransitionException extends DocuMindException
{
    public static function between(string $documentId, DocumentStatus $from, DocumentStatus $to): self
    {
        return new self(sprintf(
            'Cannot move document %s from %s to %s.',
            $documentId,
            $from->label(),
            $to->label(),
        ));
    }
}
