<?php

declare(strict_types=1);

namespace App\Enums;

enum ExtractionMethod: string
{
    case PdfText = 'pdf_text';
    case Ocr = 'ocr';

    public function label(): string
    {
        return match ($this) {
            self::PdfText => 'Text layer',
            self::Ocr => 'OCR',
        };
    }
}
