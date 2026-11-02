<?php

declare(strict_types=1);

namespace App\Parsing;

final class PlainTextParser extends AbstractParser
{
    protected function extensions(): array
    {
        return ['txt'];
    }

    protected function extract(string $contents): string
    {
        return $contents;
    }
}
