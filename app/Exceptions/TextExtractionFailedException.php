<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Failures that retrying won't fix. The message is shown to users,
 * so it's written for them, not for developers.
 */
final class TextExtractionFailedException extends DocuMindException
{
    public static function noFile(): self
    {
        return new self('There is no file attached to this document.');
    }

    public static function unsupported(?string $mimeType): self
    {
        return new self('This file type can\'t be read'.($mimeType ? " ({$mimeType})." : '.'));
    }

    public static function toolMissing(string $tool): self
    {
        return new self("The server is missing {$tool}, which is needed to read this file. Ask an administrator to install it.");
    }

    public static function toolFailed(string $tool, string $errorOutput): self
    {
        $detail = trim(strtok(trim($errorOutput), "\n") ?: '');

        return new self('This file could not be read. It may be damaged or password-protected'
            .($detail !== '' ? " ({$tool}: {$detail})." : '.'));
    }
}
