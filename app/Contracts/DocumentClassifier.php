<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\DocumentType;

interface DocumentClassifier
{
    public function classify(string $text): DocumentType;
}
