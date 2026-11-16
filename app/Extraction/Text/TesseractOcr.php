<?php

declare(strict_types=1);

namespace App\Extraction\Text;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;

/**
 * Reads text from pixels: photos, scans, and PDFs with no text layer.
 */
final class TesseractOcr
{
    public function __construct(private CommandRunner $runner) {}

    public function image(string $imagePath): string
    {
        return $this->runner->run('tesseract', [
            config('documind.extraction.tesseract'),
            $imagePath,
            'stdout',
            '-l', config('documind.extraction.ocr_languages'),
            '--psm', '3', // fully automatic page layout
        ]);
    }

    /** Renders each page to an image with pdftoppm, then OCRs the images in order. */
    public function pdf(string $pdfPath): string
    {
        $directory = storage_path('app/tmp/ocr/'.Str::uuid());
        File::ensureDirectoryExists($directory);

        try {
            $this->runner->run('pdftoppm', [
                config('documind.extraction.pdftoppm'),
                '-r', (string) config('documind.extraction.ocr_dpi'),
                '-png',
                '-l', (string) config('documind.extraction.ocr_max_pages'),
                $pdfPath,
                $directory.'/page',
            ]);

            return collect(File::files($directory))
                ->sortBy(fn (SplFileInfo $file): string => $file->getFilename())
                ->map(fn (SplFileInfo $file): string => $this->image($file->getPathname()))
                ->implode("\n\f\n"); // form feed between pages, like pdftotext
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
