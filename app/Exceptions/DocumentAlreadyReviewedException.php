<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Document;

/** Thrown when a reviewer acts on a document that is no longer awaiting review (e.g. someone else got there first). */
final class DocumentAlreadyReviewedException extends DocuMindException
{
    public static function for(Document $document): self
    {
        return new self(sprintf(
            'This document has already been reviewed (it is now %s). Refresh the page to see the latest version.',
            $document->status->label(),
        ));
    }
}
