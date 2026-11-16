<?php

declare(strict_types=1);

namespace App\Extraction\Text;

/**
 * Reads the text layer that digital PDFs (exported from accounting software, Word, etc.) contain.
 */
final class PdfTextExtractor
{
    public function __construct(private CommandRunner $runner) {}

    public function extract(string $pdfPath): string
    {
        return $this->runner->run('pdftotext', [
            config('documind.extraction.pdftotext'),
            '-layout',      // keep columns roughly where they are on the page
            '-enc', 'UTF-8',
            $pdfPath,
            '-',            // write to standard output instead of a file
        ]);
    }
}
