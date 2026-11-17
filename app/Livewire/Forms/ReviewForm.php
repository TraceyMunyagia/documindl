<?php

namespace App\Livewire\Forms;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Review\ReviewData;
use App\ValueObjects\Money;
use Closure;
use DateTimeImmutable;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ReviewForm extends Form
{
    public string $type = 'invoice';

    public string $supplier = '';

    public string $documentNumber = '';

    public string $issueDate = '';

    public string $dueDate = '';

    public string $currency = 'KES';

    public string $total = '';

    public string $tax = '';

    public string $categoryId = '';

    /** The AI's values, formatted exactly as this form shows them. */
    public static function valuesFrom(DocumentExtraction $extraction): array
    {
        $decimal = fn (mixed $cents): string => $cents === null ? '' : number_format(((int) $cents) / 100, 2, '.', '');

        return [
            'type' => $extraction->detected_type->value,
            'supplier' => (string) $extraction->field('supplier_name'),
            'documentNumber' => (string) $extraction->field('document_number'),
            'issueDate' => (string) $extraction->field('issue_date'),
            'dueDate' => (string) $extraction->field('due_date'),
            'currency' => (string) $extraction->field('currency'),
            'total' => $decimal($extraction->field('total_cents')),
            'tax' => $decimal($extraction->field('tax_cents')),
        ];
    }

    public function fillFrom(Document $document, ?DocumentExtraction $extraction): void
    {
        $values = $extraction ? self::valuesFrom($extraction) : [];

        foreach (['supplier', 'documentNumber', 'issueDate', 'dueDate', 'total', 'tax'] as $property) {
            $this->{$property} = $values[$property] ?? '';
        }

        $type = $document->type->isFinancial()
            ? $document->type
            : DocumentType::tryFrom($values['type'] ?? '');

        $this->type = $type?->isFinancial() ? $type->value : DocumentType::Invoice->value;
        $this->currency = ($values['currency'] ?? '') ?: $document->business->currency;
        $this->categoryId = (string) ($document->category_id ?? '');
    }

    public function toData(Document $document): ReviewData
    {
        // Clean first, so "13,660.16 " and "13660.16" are treated the same.
        $this->supplier = trim(preg_replace('/\s+/u', ' ', $this->supplier) ?? '');
        $this->documentNumber = trim($this->documentNumber);
        $this->currency = strtoupper(trim($this->currency));
        $this->total = $this->cleanAmount($this->total);
        $this->tax = $this->cleanAmount($this->tax);

        $isInvoice = $this->type === DocumentType::Invoice->value;
        $amount = 'regex:/^\d{1,12}(\.\d{1,2})?$/';

        $this->validate([
            'type' => ['required', Rule::in([DocumentType::Invoice->value, DocumentType::Receipt->value])],
            'supplier' => ['required', 'string', 'max:255'],
            'documentNumber' => ['required', 'string', 'max:100'],
            'issueDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addDays(31)->toDateString()],
            'dueDate' => $isInvoice
                ? ['required', 'date_format:Y-m-d', $this->notBeforeIssueDate()]
                : ['nullable'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'total' => ['required', $amount, 'not_regex:/^0+(\.0+)?$/'],
            'tax' => ['nullable', $amount, $this->notMoreThanTotal()],
            'categoryId' => [
                'nullable',
                Rule::exists('categories', 'id')->where('business_id', $document->business_id),
            ],
        ], [
            'total.regex' => 'Enter the total as a number, e.g. 13660.16.',
            'total.not_regex' => 'The total must be more than zero.',
            'tax.regex' => 'Enter the tax as a number, e.g. 1884.16.',
            'dueDate.required' => 'Invoices need a due date.',
            'issueDate.before_or_equal' => 'The issue date is too far in the future.',
        ], [
            'documentNumber' => 'document number',
            'issueDate' => 'issue date',
            'dueDate' => 'due date',
            'categoryId' => 'category',
        ]);

        return new ReviewData(
            type: DocumentType::from($this->type),
            supplier: $this->supplier,
            documentNumber: $this->documentNumber,
            issueDate: DateTimeImmutable::createFromFormat('!Y-m-d', $this->issueDate),
            dueDate: $isInvoice ? DateTimeImmutable::createFromFormat('!Y-m-d', $this->dueDate) : null,
            total: Money::fromDecimal($this->total, $this->currency),
            tax: $this->tax === '' ? null : Money::fromDecimal($this->tax, $this->currency),
            categoryId: $this->categoryId === '' ? null : (int) $this->categoryId,
        );
    }

    private function cleanAmount(string $value): string
    {
        return preg_replace('/[^\d.]/', '', $value) ?? '';
    }

    private function notBeforeIssueDate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $this->issueDate !== '' && $value < $this->issueDate) {
                $fail('The due date can\'t be before the issue date.');
            }
        };
    }

    private function notMoreThanTotal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== '' && $this->total !== '' && (float) $value > (float) $this->total) {
                $fail('The tax can\'t be more than the total.');
            }
        };
    }
}
