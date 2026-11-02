<?php

declare(strict_types=1);

namespace App\Contracts;

interface DocumentParser
{
    public function supports(string $extension): bool;

    /** Returns the document's plain text. */
    public function parse(string $path): string;
}
