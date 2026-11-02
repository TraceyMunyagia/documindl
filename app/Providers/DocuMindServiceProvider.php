<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\DocumentClassifier;
use App\Contracts\InvoiceExtractor;
use App\Extraction\KeywordClassifier;
use App\Extraction\RegexInvoiceExtractor;
use App\Parsing\CsvReceiptParser;
use App\Parsing\ParserRegistry;
use App\Parsing\PlainTextParser;
use Illuminate\Support\ServiceProvider;

class DocuMindServiceProvider extends ServiceProvider
{
    /**
     * Whenever the app asks for an interface, the container hands back the class bound here.
     * In Week 7 you'll swap RegexInvoiceExtractor for a Gemini extractor on this one line.
     */
    public function register(): void
    {
        $this->app->singleton(ParserRegistry::class, fn (): ParserRegistry => new ParserRegistry()
            ->register(new PlainTextParser)
            ->register(new CsvReceiptParser));

        $this->app->bind(DocumentClassifier::class, KeywordClassifier::class);
        $this->app->bind(InvoiceExtractor::class, RegexInvoiceExtractor::class);
    }

    public function boot(): void
    {
        //
    }
}
