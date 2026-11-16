<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every upload path (the upload page now, an API later) uses these rules.
 */
final class DocumentUploadRules
{
    /** @return list<string> */
    public static function file(): array
    {
        $extensions = implode(',', config('documind.uploads.allowed_extensions'));

        return [
            'required',
            'file',
            "extensions:{$extensions}", // what the filename claims to be
            "mimes:{$extensions}",      // what the file's bytes actually are
            'max:'.config('documind.uploads.max_kb'),
        ];
    }

    public static function maxFiles(): int
    {
        return (int) config('documind.uploads.max_files', 10);
    }
}
