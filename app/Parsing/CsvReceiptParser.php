<?php

declare(strict_types=1);

namespace App\Parsing;

/**
 * Turns two-column CSV rows ("Total","KES 1,250.00") into "Total: KES 1,250.00" lines.
 */
final class CsvReceiptParser extends AbstractParser
{
    protected function extensions(): array
    {
        return ['csv'];
    }

    protected function extract(string $contents): string
    {
        $lines = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $row) {
            if (trim($row) === '') {
                continue;
            }

            $cells = str_getcsv($row, ',', '"', '');
            $label = trim((string) ($cells[0] ?? ''));
            $value = trim((string) ($cells[1] ?? ''));

            if ($label === '' || strtolower($label) === 'field') {
                continue; // skip blank labels and the header row
            }

            $lines[] = "{$label}: {$value}";
        }

        return implode("\n", $lines);
    }
}
