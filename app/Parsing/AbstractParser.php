<?php

declare(strict_types=1);

namespace App\Parsing;

use App\Contracts\DocumentParser;
use App\Exceptions\FileNotReadableException;

/**
 * Template method: parse() is fixed, subclasses only fill in extensions() and extract().
 */
abstract class AbstractParser implements DocumentParser
{
    /** @return list<string> */
    abstract protected function extensions(): array;

    abstract protected function extract(string $contents): string;

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), $this->extensions(), true);
    }

    public function parse(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw FileNotReadableException::at($path);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw FileNotReadableException::at($path);
        }

        return $this->normalise($this->extract($contents));
    }

    protected function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = array_map(
            fn (string $line): string => trim(preg_replace('/[ \t]+/', ' ', $line) ?? $line),
            explode("\n", $text),
        );

        return trim(implode("\n", $lines));
    }
}
