<?php

declare(strict_types=1);

namespace App\Parsing;

use App\Contracts\DocumentParser;
use App\Exceptions\UnsupportedFileTypeException;

final class ParserRegistry
{
    /** @var list<DocumentParser> */
    private array $parsers = [];

    public function register(DocumentParser $parser): self
    {
        $this->parsers[] = $parser;

        return $this;
    }

    public function for(string $extension): DocumentParser
    {
        return array_find(
            $this->parsers,
            fn (DocumentParser $parser): bool => $parser->supports($extension),
        ) ?? throw UnsupportedFileTypeException::forExtension(strtolower($extension));
    }
}
