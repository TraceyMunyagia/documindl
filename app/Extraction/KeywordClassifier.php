<?php

declare(strict_types=1);

namespace App\Extraction;

use App\Contracts\DocumentClassifier;
use App\Enums\DocumentType;

final class KeywordClassifier implements DocumentClassifier
{
    public function classify(string $text): DocumentType
    {
        $haystack = strtolower($text);

        // Order matters: the first matching rule wins.
        $rules = [
            [DocumentType::Contract, ['agreement', 'contract', 'hereinafter']],
            [DocumentType::Receipt, ['receipt', 'paid with', 'change due']],
            [DocumentType::Invoice, ['invoice', 'amount due', 'bill to']],
        ];

        foreach ($rules as [$type, $keywords]) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $type;
                }
            }
        }

        return DocumentType::Other;
    }
}
