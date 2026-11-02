<?php

use App\Contracts\DocumentClassifier;
use App\Contracts\InvoiceExtractor;
use App\Extraction\KeywordClassifier;
use App\Extraction\RegexInvoiceExtractor;

it('binds the interfaces to the rule-based implementations', function () {
    expect(app(DocumentClassifier::class))->toBeInstanceOf(KeywordClassifier::class)
        ->and(app(InvoiceExtractor::class))->toBeInstanceOf(RegexInvoiceExtractor::class);
});
