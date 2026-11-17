<?php

use App\Actions\Review\MarkDocumentNotFinancial;
use App\Actions\Review\VerifyDocument;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\DocumentAlreadyReviewedException;
use App\Livewire\Forms\ReviewForm;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentExtraction;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.documind')] #[Title('Review document')] class extends Component
{
    #[Locked]
    public Document $document;

    public ReviewForm $form;

    /** When the reviewer opened the page, to measure how long reviews take. */
    #[Locked]
    public int $startedAt = 0;

    public function mount(Document $document): void
    {
        $this->authorize('update', $document);

        $this->document = $document->load(['latestExtraction', 'category', 'business']);

        if ($document->status !== DocumentStatus::ReviewRequired) {
            session()->flash('status', "{$document->original_name} isn't waiting for review.");
            $this->redirectRoute('documents.show', $document, navigate: true);

            return;
        }

        $this->form->fillFrom($this->document, $this->document->latestExtraction);
        $this->startedAt = now()->getTimestamp();
    }

    #[Computed]
    public function extraction(): ?DocumentExtraction
    {
        return $this->document->latestExtraction;
    }

    /** @return array<string, string> */
    #[Computed]
    public function aiValues(): array
    {
        return $this->extraction ? ReviewForm::valuesFrom($this->extraction) : [];
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function nextDocumentId(): ?int
    {
        return Document::query()
            ->reviewableBy(auth()->user())
            ->whereKeyNot($this->document->getKey())
            ->orderBy('created_at')
            ->value('id');
    }

    /** Confidence level for a field, or null when the values didn't come from AI. */
    public function level(string $field): ?string
    {
        return $this->extraction?->usedAi() ? $this->extraction->confidenceLevel($field) : null;
    }

    public function isMissing(string $formField): bool
    {
        return $this->extraction !== null && ($this->aiValues[$formField] ?? '') === '';
    }

    public function save(): void
    {
        $this->verify(goToNext: false);
    }

    public function saveAndNext(): void
    {
        $this->verify(goToNext: true);
    }

    public function markAs(string $type): void
    {
        $this->authorize('update', $this->document);

        $type = DocumentType::from($type);
        abort_if($type->isFinancial(), 422);

        try {
            app(MarkDocumentNotFinancial::class)->handle($this->document, auth()->user(), $type);
        } catch (DocumentAlreadyReviewedException $e) {
            $this->leave($e->getMessage(), route('documents.show', $this->document));

            return;
        }

        $this->leave("Marked {$this->document->original_name} as {$type->label()}.", route('review.index'));
    }

    private function verify(bool $goToNext): void
    {
        $this->authorize('update', $this->document);

        $data = $this->form->toData($this->document);
        $nextId = $goToNext ? $this->nextDocumentId : null;

        try {
            $invoice = app(VerifyDocument::class)->handle(
                $this->document,
                auth()->user(),
                $data,
                now()->getTimestamp() - $this->startedAt,
            );
        } catch (DocumentAlreadyReviewedException $e) {
            $this->leave($e->getMessage(), route('documents.show', $this->document));

            return;
        }

        $this->leave(
            "Saved {$invoice->supplier_name} {$invoice->invoice_number} ({$invoice->total->format()}).",
            $nextId ? route('documents.review', $nextId) : route('review.index'),
        );
    }

    private function leave(string $message, string $url): void
    {
        session()->flash('status', $message);
        $this->redirect($url, navigate: true);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('review.index') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-900">&larr; Review queue</a>
            <h2 class="mt-1 truncate text-xl font-semibold tracking-tight text-zinc-900">{{ $document->original_name }}</h2>
            <p class="mt-1 text-sm text-zinc-500">
                Check each field against the document, correct anything that's wrong, then save.
            </p>
        </div>

        <a href="{{ route('documents.show', $document) }}" wire:navigate class="text-sm font-medium text-zinc-600 hover:text-zinc-900">
            Open document details
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <div class="lg:sticky lg:top-20">
                <x-documents.preview :document="$document" />
            </div>
        </div>

        <form wire:submit="save" class="space-y-5 lg:col-span-2">
            @if ($this->extraction?->summary)
                <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm text-zinc-600 shadow-sm">
                    {{ $this->extraction->summary }}
                </div>
            @endif

            @if ($this->extraction === null)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    No text could be read from this document, so please type the details in from the preview.
                </div>
            @elseif (! $this->extraction->usedAi())
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">
                    These fields were found with simple rules rather than AI, so check every one.
                </div>
            @endif

            @if (($this->extraction?->warnings ?? []) !== [])
                <ul class="space-y-1 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    @foreach ($this->extraction->warnings as $warning)
                        <li class="flex gap-2">
                            <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" />
                            <span>{{ $warning }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="space-y-5 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <fieldset>
                    <legend class="text-sm font-medium text-zinc-700">This document is a…</legend>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        @foreach ([\App\Enums\DocumentType::Invoice, \App\Enums\DocumentType::Receipt] as $option)
                            <label @class([
                                'flex cursor-pointer flex-col rounded-lg border px-3 py-2 text-sm',
                                'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' => $form->type === $option->value,
                                'border-zinc-300 hover:bg-zinc-50' => $form->type !== $option->value,
                            ])>
                                <input type="radio" wire:model.live="form.type" value="{{ $option->value }}" class="sr-only">
                                <span class="font-semibold text-zinc-900">{{ $option->label() }}</span>
                                <span class="text-xs text-zinc-500">
                                    {{ $option === \App\Enums\DocumentType::Invoice ? 'Asks to be paid' : 'Proves it was paid' }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </fieldset>

                <x-review.field
                    label="Supplier"
                    model="form.supplier"
                    :current="$form->supplier"
                    :ai-value="$this->aiValues['supplier'] ?? null"
                    :level="$this->level('supplier_name')"
                    :missing="$this->isMissing('supplier')"
                    :error="$errors->first('form.supplier')"
                />

                <x-review.field
                    :label="$form->type === 'receipt' ? 'Receipt number' : 'Invoice number'"
                    model="form.documentNumber"
                    :current="$form->documentNumber"
                    :ai-value="$this->aiValues['documentNumber'] ?? null"
                    :level="$this->level('document_number')"
                    :missing="$this->isMissing('documentNumber')"
                    :error="$errors->first('form.documentNumber')"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-review.field
                        :label="$form->type === 'receipt' ? 'Date paid' : 'Issue date'"
                        model="form.issueDate"
                        type="date"
                        :current="$form->issueDate"
                        :ai-value="$this->aiValues['issueDate'] ?? null"
                        :level="$this->level('issue_date')"
                        :missing="$this->isMissing('issueDate')"
                        :error="$errors->first('form.issueDate')"
                    />

                    @if ($form->type === 'invoice')
                        <x-review.field
                            label="Due date"
                            model="form.dueDate"
                            type="date"
                            :current="$form->dueDate"
                            :ai-value="$this->aiValues['dueDate'] ?? null"
                            :level="$this->level('due_date')"
                            :missing="$this->isMissing('dueDate')"
                            :error="$errors->first('form.dueDate')"
                        />
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="form.currency" class="text-sm font-medium text-zinc-700">Currency</label>
                        <select
                            id="form.currency"
                            wire:model.live="form.currency"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm"
                        >
                            @foreach (array_unique(['KES', 'UGX', 'TZS', 'RWF', 'USD', 'EUR', 'GBP', $form->currency]) as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        @error('form.currency') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <x-review.field
                            label="Total (including tax)"
                            model="form.total"
                            inputmode="decimal"
                            :prefix="$form->currency"
                            :current="$form->total"
                            :ai-value="$this->aiValues['total'] ?? null"
                            :level="$this->level('total_amount')"
                            :missing="$this->isMissing('total')"
                            :error="$errors->first('form.total')"
                        />
                    </div>
                </div>

                <x-review.field
                    label="Tax / VAT (optional)"
                    model="form.tax"
                    inputmode="decimal"
                    :prefix="$form->currency"
                    :current="$form->tax"
                    :ai-value="$this->aiValues['tax'] ?? null"
                    :level="$this->level('tax_amount')"
                    :error="$errors->first('form.tax')"
                />

                <div>
                    <label for="form.categoryId" class="text-sm font-medium text-zinc-700">Category</label>
                    <select
                        id="form.categoryId"
                        wire:model="form.categoryId"
                        class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm"
                    >
                        <option value="">Uncategorised</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('form.categoryId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($this->nextDocumentId)
                    <button
                        type="button"
                        wire:click="saveAndNext"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                    >
                        Verify &amp; next
                    </button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 disabled:opacity-60"
                    >
                        Verify &amp; save
                    </button>
                @else
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                    >
                        Verify &amp; save
                    </button>
                @endif

                <div class="relative ml-auto" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = ! open" class="text-sm text-zinc-500 hover:text-zinc-900">
                        Not an invoice or receipt?
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute bottom-full right-0 z-10 mb-2 w-48 rounded-xl border border-zinc-200 bg-white p-1 shadow-lg">
                        <button type="button" wire:click="markAs('contract')" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-zinc-100">It's a contract</button>
                        <button type="button" wire:click="markAs('other')" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-zinc-100">It's something else</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
