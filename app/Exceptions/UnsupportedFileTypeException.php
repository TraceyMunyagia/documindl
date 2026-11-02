<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UnsupportedFileTypeException extends DocuMindException
{
    public static function forExtension(string $extension): self
    {
        $shown = $extension === '' ? '(no extension)' : ".{$extension}";

        return new self("No parser available for {$shown} files.");
    }
}
