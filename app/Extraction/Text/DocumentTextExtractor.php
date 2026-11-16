<?php

declare(strict_types=1);

namespace App\Extraction\Text;

use App\Enums\ExtractionMethod;
use App\Exceptions\TextExtractionFailedException;
use App\Models\Document;
use Closure;
use Illuminate\Support\Facades\Storage;

final class DocumentTextExtractor
{
    public function __construct(
        private PdfTextExtractor $pdfText,
        private TesseractOcr $ocr,
    ) {}

    public function extract(Document $document): ExtractedText
    {
        if (! $document->hasFile()) {
            throw TextExtractionFailedException::noFile();
        }

        if (! $document->isPdf() && ! $document->isImage()) {
            throw TextExtractionFailedException::unsupported($document->mime_type);
        }

        return $this->withLocalFile($document, function (string $path) use ($document): ExtractedText {
            if ($document->isImage()) {
                return new ExtractedText(self::clean($this->ocr->image($path)), ExtractionMethod::Ocr);
            }

            $text = self::clean($this->pdfText->extract($path));

            if (ExtractedText::meaningfulLength($text) >= (int) config('documind.extraction.min_pdf_text_chars')) {
                return new ExtractedText($text, ExtractionMethod::PdfText);
            }

            // Almost no text layer: it's a scan saved as PDF, so read the pixels instead.
            return new ExtractedText(self::clean($this->ocr->pdf($path)), ExtractionMethod::Ocr);
        });
    }

    /** Makes tool output safe to store and pleasant to read. */
    public static function clean(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');                       // PostgreSQL rejects invalid UTF-8
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x7F]/u', '', $text) ?? ''; // keeps \t, \n and \f
        $text = preg_replace('/[ \t]+$/m', '', $text) ?? $text; // trailing spaces on each line
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * The tools need a real file path. Local disks already have one; for cloud disks
     * (Week 18) the file is copied to a temporary file first and removed afterwards.
     *
     * @template T
     *
     * @param  Closure(string): T  $callback
     * @return T
     */
    private function withLocalFile(Document $document, Closure $callback): mixed
    {
        $disk = Storage::disk($document->disk);

        if (config("filesystems.disks.{$document->disk}.driver") === 'local') {
            return $callback($disk->path($document->path));
        }

        $temporary = tempnam(sys_get_temp_dir(), 'documind_');
        $source = $disk->readStream($document->path);
        $target = fopen($temporary, 'wb');
        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);

        try {
            return $callback($temporary);
        } finally {
            @unlink($temporary);
        }
    }
}
