<?php

declare(strict_types=1);

namespace App\Exceptions;

final class FileNotReadableException extends DocuMindException
{
    public static function at(string $path): self
    {
        return new self('File missing or not readable: '.basename($path));
    }
}
