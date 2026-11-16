<?php

declare(strict_types=1);

namespace App\Ai\Extraction;

use App\Ai\ExtractionUsage;
use App\Enums\DocumentType;
use App\Exceptions\AiExtractionException;
use App\Exceptions\InvalidMoneyException;
use App\Extraction\ExtractionResult;
use App\ValueObjects\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Turns the model's JSON into an ExtractionResult. The schema shapes the answer;
 * this class decides how far to trust it.
 */
final class ExtractionPayload
{
    private const array CURRENCY_ALIASES = [
        'KSH' => 'KES', 'KSHS' => 'KES', 'KSH.' => 'KES', 'SH' => 'KES',
        'USH' => 'UGX', 'TSH' => 'TZS', '$' => 'USD', 'US$' => 'USD',
    ];

    /** @param array<string, mixed> $data */
    public function toResult(array $data, string $sourceText, ?ExtractionUsage $usage = null): ExtractionResult
    {
        $this->validate($data);

        $warnings = collect($data['warnings'])
            ->map(fn (mixed $warning): string => Str::limit(trim((string) $warning), 300))
            ->filter()
            ->take(5)
            ->values()
            ->all();

        $confidence = $this->confidence((array) $data['confidence']);

        $supplier = $this->text($data['supplier_name'], 255);
        $number = $this->text($data['document_number'], 100);
        $issueDate = $this->date($data['issue_date'], 'issue date', $warnings);
        $dueDate = $this->date($data['due_date'], 'due date', $warnings);

        $currency = $this->currency($data['currency']);
        $hasAnyAmount = trim((string) $data['total_amount']) !== '' || trim((string) $data['tax_amount']) !== '';

        if ($currency === null && $hasAnyAmount) {
            $currency = config('documind.default_currency');
            $warnings[] = "No currency was shown, so {$currency} was assumed.";
            $confidence['currency'] = min($confidence['currency'], 0.5);
        }

        $currency ??= config('documind.default_currency');
        $total = $this->money($data['total_amount'], $currency, 'total', $warnings);
        $tax = $this->money($data['tax_amount'], $currency, 'tax', $warnings);

        // Cross-checks: the model can be confidently wrong, so test its answer against simple facts.
        if ($total !== null && $tax !== null && $tax->amountInCents > $total->amountInCents) {
            $warnings[] = 'The tax amount is larger than the total – check both.';
            $confidence['tax_amount'] = min($confidence['tax_amount'], 0.3);
        }

        if ($issueDate !== null && $dueDate !== null && $dueDate < $issueDate) {
            $warnings[] = 'The due date is before the issue date – check both.';
            $confidence['due_date'] = min($confidence['due_date'], 0.3);
        }

        if ($total !== null && ! $this->appearsIn($total, $sourceText)) {
            $warnings[] = 'The total does not appear in the document text – check it against the original.';
            $confidence['total_amount'] = min($confidence['total_amount'], 0.4);
        }

        // An empty field has no confidence, whatever the model said.
        $values = [
            'supplier_name' => $supplier, 'document_number' => $number, 'issue_date' => $issueDate,
            'due_date' => $dueDate, 'total_amount' => $total, 'tax_amount' => $tax,
        ];

        foreach ($values as $field => $value) {
            if ($value === null) {
                $confidence[$field] = 0.0;
            }
        }

        return new ExtractionResult(
            type: DocumentType::from($data['document_type']),
            supplier: $supplier,
            documentNumber: $number,
            issueDate: $issueDate,
            dueDate: $dueDate,
            total: $total,
            tax: $tax,
            summary: $this->text($data['summary'], 500),
            confidence: $confidence,
            warnings: array_values(array_unique($warnings)),
            usage: $usage,
        );
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'supplier_name' => ['present', 'nullable', 'string'],
            'document_number' => ['present', 'nullable', 'string'],
            'issue_date' => ['present', 'nullable', 'string'],
            'due_date' => ['present', 'nullable', 'string'],
            'currency' => ['present', 'nullable', 'string'],
            'total_amount' => ['present', 'nullable'],
            'tax_amount' => ['present', 'nullable'],
            'summary' => ['present', 'nullable', 'string'],
            'confidence' => ['present', 'array'],
            'warnings' => ['present', 'array'],
        ]);

        if ($validator->fails()) {
            throw AiExtractionException::invalidResponse($validator->errors()->first());
        }
    }

    /** @return array<string, float> */
    private function confidence(array $raw): array
    {
        $scores = [];

        foreach (ExtractionPrompt::FIELDS as $field) {
            $value = $raw[$field] ?? 0;
            $scores[$field] = is_numeric($value) ? max(0.0, min(1.0, (float) $value)) : 0.0;
        }

        return $scores;
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $value === '' ? null : Str::limit($value, $max, '');
    }

    /** @param list<string> $warnings */
    private function date(mixed $value, string $label, array &$warnings): ?DateTimeImmutable
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        if ($date === false || $date->format('Y-m-d') !== $raw) {
            $warnings[] = "Couldn't understand the {$label} ({$raw}).";

            return null;
        }

        if ((int) $date->format('Y') < 2000 || $date > now()->addYears(5)) {
            $warnings[] = "The {$label} ({$raw}) looks unusual – check it.";
        }

        return $date;
    }

    private function currency(mixed $value): ?string
    {
        $code = strtoupper(trim((string) $value));

        if ($code === '') {
            return null;
        }

        $code = self::CURRENCY_ALIASES[$code] ?? $code;

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }

    /** @param list<string> $warnings */
    private function money(mixed $value, string $currency, string $label, array &$warnings): ?Money
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $clean = preg_replace('/[^\d.\-]/', '', $raw) ?? '';

        if (! is_numeric($clean)) {
            $warnings[] = "Couldn't understand the {$label} amount ({$raw}).";

            return null;
        }

        try {
            return Money::fromDecimal($clean, $currency);
        } catch (InvalidMoneyException) {
            $warnings[] = "The {$label} amount ({$raw}) isn't valid.";

            return null;
        }
    }

    /** True if the amount is printed somewhere in the text, in any common format. */
    private function appearsIn(Money $money, string $text): bool
    {
        $amount = $money->amountInCents / 100;
        $haystack = preg_replace('/\s+/u', '', $text) ?? $text;

        $variants = [number_format($amount, 2, '.', ','), number_format($amount, 2, '.', '')];

        if ($money->amountInCents % 100 === 0) {
            $variants[] = number_format($amount, 0, '.', ',');
            $variants[] = number_format($amount, 0, '.', '');
        }

        foreach ($variants as $variant) {
            if (str_contains($haystack, $variant)) {
                return true;
            }
        }

        return false;
    }
}
