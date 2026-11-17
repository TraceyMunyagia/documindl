<?php

use App\Actions\Documents\ReprocessDocument;
use App\Livewire\Forms\DocumentDetailsForm;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.documind')] #[Title('Document')] class extends Component
{
    // Locked: the browser can't swap this for another document's ID.
    #[Locked]
    public Document $document;

    public DocumentDetailsForm $form;

    public bool $editing = false;

    public function mount(Document $document): void
    {
        $this->authorize('view', $document);

        $this->document = $document->load(['category', 'uploader', 'latestExtraction']);
        $this->form->setFrom($this->document);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get(['id', 'name']);
    }

    public function startEditing(): void
    {
        $this->authorize('update', $this->document);

        $this->form->setFrom($this->document);
        $this->resetValidation();
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->resetValidation();
        $this->editing = false;

    }
        /** Called every few seconds while the document is pending or processing. */
    public function refreshStatus(): void
    {
        $this->document->refresh()->load(['category', 'uploader']);
    }

    public function reprocess(ReprocessDocument $reprocessDocument): void
    {
        $this->authorize('update', $this->document);
        abort_unless($this->document->status === \App\Enums\DocumentStatus::Failed, 422, 'Only failed documents can be processed again.');

        $reprocessDocument->handle($this->document);

        $this->document->refresh()->load(['category', 'uploader']);
    }

    public function saveDetails(): void
    {
        $this->authorize('update', $this->document);

        $this->document = $this->form->save($this->document)->load(['category', 'uploader']);
        $this->editing = false;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->document);

        $this->document->delete(); // soft delete: the row and file are kept for recovery and audit

        AuditLog::record('document.deleted', $this->document, ['name' => $this->document->original_name]);

        session()->flash('status', "Deleted {$this->document->original_name}.");

        $this->redirectRoute('documents.index', navigate: true);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('documents.index') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-900">&larr; Documents</a>
            <h2 class="mt-1 truncate text-xl font-semibold tracking-tight text-zinc-900">{{ $document->original_name }}</h2>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-ui.badge :color="$document->status->color()">{{ $document->status->label() }}</x-ui.badge>
                <x-ui.badge>{{ $document->type->label() }}</x-ui.badge>
                @if ($document->category)
                    <x-ui.badge :color="$document->category->color">{{ $document->category->name }}</x-ui.badge>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($document->status === \App\Enums\DocumentStatus::ReviewRequired)
                @can('update', $document)
                    <a
                        href="{{ route('documents.review', $document) }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        <flux:icon.check-badge class="size-4" />
                        Review &amp; verify
                    </a>
                @endcan
            @endif

            @can('update', $document)
                @unless ($editing)
                    <button
                        type="button"
                        wire:click="startEditing"
                        class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50"
                    >
                        <flux:icon.pencil-square class="size-4" />
                        Edit details
                    </button>
                @endunless
            @endcan

            @can('delete', $document)
                <button
                    type="button"
                    wire:click="delete"
                    wire:confirm="Delete {{ $document->original_name }}? Owners can ask for it to be restored later."
                    class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50"
                >
                    <flux:icon.trash class="size-4" />
                    Delete
                </button>
            @endcan

            @if ($document->hasFile())
                <a
                    href="{{ route('documents.download', $document) }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    <flux:icon.arrow-down-tray class="size-4" />
                    Download
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-documents.preview :document="$document" />
        </div>

         <div class="space-y-6" @if ($document->status->isActive()) wire:poll.3s="refreshStatus" @endif>
                    <div class="space-y-6">
            @if ($editing)
                <form wire:submit="saveDetails" class="space-y-4 rounded-xl border border-indigo-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-zinc-900">Edit details</h3>

                    <div>
                        <label for="doc-name" class="block text-sm font-medium text-zinc-700">Name</label>
                        <div class="mt-1 flex rounded-lg border border-zinc-300 shadow-sm focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20">
                            <input id="doc-name" type="text" wire:model="form.name" class="min-w-0 flex-1 rounded-l-lg border-0 px-3 py-2 text-sm focus:outline-none">
                            <span class="flex items-center rounded-r-lg bg-zinc-50 px-3 text-sm text-zinc-500">
                                .{{ pathinfo($document->original_name, PATHINFO_EXTENSION) }}
                            </span>
                        </div>
                        @error('form.name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="doc-category" class="block text-sm font-medium text-zinc-700">Category</label>
                        <select id="doc-category" wire:model="form.categoryId" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                            <option value="">Uncategorised</option>
                            @foreach ($this->categories as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        @error('form.categoryId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="doc-type" class="block text-sm font-medium text-zinc-700">Type</label>
                        <select id="doc-type" wire:model="form.type" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                            @foreach (\App\Enums\DocumentType::cases() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('form.type') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="doc-summary" class="block text-sm font-medium text-zinc-700">Notes</label>
                        <textarea id="doc-summary" wire:model="form.summary" rows="3" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm" placeholder="Anything worth remembering about this document"></textarea>
                        @error('form.summary') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelEditing" class="rounded-lg px-4 py-2 text-sm text-zinc-600 hover:bg-zinc-100">Cancel</button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save</button>
                    </div>
                </form>
            @endif

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-zinc-900">Details</h3>

                        @if ($document->status->isActive())
                <div class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                    <svg class="mt-0.5 size-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle>
                        <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                    </svg>
                    <div>
                        <p class="font-semibold">{{ $document->status === \App\Enums\DocumentStatus::Pending ? 'Waiting to be processed' : 'Reading the document…' }}</p>
                        <p class="mt-0.5">This page updates by itself. Scans and photos take longer because they're read with OCR.</p>
                    </div>
                </div>
            @endif

            @if ($document->status === \App\Enums\DocumentStatus::Failed)
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                                     <p class="font-semibold">Processing failed</p>
                    <p class="mt-0.5">{{ $document->failure_reason ?? 'Something went wrong.' }}</p>

                    @can('update', $document)
                        <button
                            type="button"
                            wire:click="reprocess"
                            wire:loading.attr="disabled"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-rose-600 px-3 py-1.5 font-semibold text-white hover:bg-rose-500 disabled:opacity-60"
                        >
                            <flux:icon.arrow-path class="size-4" />
                            Try again
                        </button>
                    @endcan
                </div>
            @endif

            @if ($document->processing_note)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-semibold">Needs attention</p>
                    <p class="mt-0.5">{{ $document->processing_note }}</p>
                </div>
            @endif

            @if ($document->invoice)
                @php $invoice = $document->invoice; @endphp
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-emerald-900">
                            Saved as {{ $document->type === \App\Enums\DocumentType::Receipt ? 'a paid receipt' : 'an invoice' }}
                        </h3>
                        <x-ui.badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-ui.badge>
                    </div>
                    <dl class="mt-3 space-y-1.5 text-emerald-900">
                        <div class="flex justify-between gap-4"><dt class="text-emerald-700">Supplier</dt><dd class="text-right">{{ $invoice->supplier_name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-emerald-700">Number</dt><dd class="text-right">{{ $invoice->invoice_number }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-emerald-700">Total</dt><dd class="text-right font-semibold">{{ $invoice->total->format() }}</dd></div>
                        @if ($invoice->due_date)
                            <div class="flex justify-between gap-4"><dt class="text-emerald-700">Due</dt><dd class="text-right">{{ $invoice->due_date->format('j M Y') }}</dd></div>
                        @endif
                    </dl>
                    <p class="mt-3 text-xs text-emerald-700">The Invoices section arrives in Week 10.</p>
                </div>
            @endif

            @php $extraction = $document->latestExtraction; @endphp
            @if ($extraction)
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-zinc-900">Extracted information</h3>
                        @if ($extraction->verified_at)
                            <x-ui.badge color="emerald">Verified</x-ui.badge>
                        @else
                            <x-ui.badge color="amber">Not yet verified</x-ui.badge>
                        @endif
                    </div>

                    @if ($extraction->summary)
                        <p class="mt-3 text-sm text-zinc-600">{{ $extraction->summary }}</p>
                    @endif

                    @php
                        $rows = [
                            'supplier_name' => ['Supplier', $extraction->field('supplier_name')],
                            'document_number' => ['Number', $extraction->field('document_number')],
                            'issue_date' => ['Issued', $extraction->field('issue_date')
                                ? \Illuminate\Support\Carbon::parse($extraction->field('issue_date'))->format('j M Y') : null],
                            'due_date' => ['Due', $extraction->field('due_date')
                                ? \Illuminate\Support\Carbon::parse($extraction->field('due_date'))->format('j M Y') : null],
                            'total_amount' => ['Total', $extraction->money('total')?->format()],
                            'tax_amount' => ['Tax', $extraction->money('tax')?->format()],
                        ];
                        $dot = ['high' => 'bg-emerald-500', 'medium' => 'bg-amber-500', 'low' => 'bg-rose-500'];
                    @endphp

                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ($rows as $field => [$label, $value])
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-zinc-500">{{ $label }}</dt>
                                <dd class="flex items-center gap-2 text-right">
                                    @if ($value === null)
                                        <span class="text-zinc-400">Not found</span>
                                    @else
                                        <span class="font-medium text-zinc-900">{{ $value }}</span>
                                        @if ($extraction->usedAi())
                                            <span
                                                class="size-2 shrink-0 rounded-full {{ $dot[$extraction->confidenceLevel($field)] }}"
                                                title="{{ ucfirst($extraction->confidenceLevel($field)) }} confidence ({{ number_format(($extraction->confidence[$field] ?? 0) * 100) }}%)"
                                            ></span>
                                        @endif
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>

                    @if ($extraction->missing_fields !== [])
                        <p class="mt-4 text-xs text-zinc-500">
                            Missing: {{ collect($extraction->missing_fields)->map(fn ($f) => str_replace('_', ' ', $f))->implode(', ') }}
                        </p>
                    @endif

                    @if ($extraction->warnings !== [])
                        <ul class="mt-4 space-y-1 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                            @foreach ($extraction->warnings as $warning)
                                <li class="flex gap-2">
                                    <flux:icon.exclamation-triangle class="size-4 shrink-0" />
                                    <span>{{ $warning }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="mt-4 border-t border-zinc-100 pt-3 text-xs text-zinc-400">
                        @if ($extraction->usedAi())
                            Read by {{ $extraction->model }} ·
                            {{ number_format($extraction->prompt_tokens + $extraction->output_tokens) }} tokens ·
                            ~${{ number_format((float) $extraction->estimated_cost_usd, 4) }} ·
                            {{ number_format($extraction->latency_ms / 1000, 1) }}s
                        @else
                            Read with simple rules (no AI)
                        @endif
                        · {{ $extraction->created_at->timezone(config('documind.display_timezone'))->format('j M, H:i') }}
                    </p>
                </div>
            @elseif (! $document->status->isActive() && $document->type->isFinancial())
                <div class="rounded-xl border border-dashed border-zinc-200 bg-white p-5 text-sm text-zinc-500">
                    <h3 class="font-semibold text-zinc-900">Extracted information</h3>
                    <p class="mt-1">No fields have been extracted for this document.</p>
                </div>
            @endif

            @if ($document->extracted_text)
                <details class="group rounded-xl border border-zinc-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer items-center justify-between gap-2 px-5 py-4 text-sm font-semibold text-zinc-900">
                        Extracted text
                        <span class="flex items-center gap-2">
                            <x-ui.badge :color="$document->extraction_method === \App\Enums\ExtractionMethod::Ocr ? 'amber' : 'emerald'">
                                {{ $document->extraction_method?->label() }}
                            </x-ui.badge>
                            <flux:icon.chevron-down class="size-4 text-zinc-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-zinc-100 px-5 py-4">
                        <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words font-mono text-xs leading-relaxed text-zinc-700">{{ $document->extracted_text }}</pre>
                        <p class="mt-3 text-xs text-zinc-400">
                            {{ number_format(mb_strlen($document->extracted_text)) }} characters ·
                            processed {{ $document->processed_at?->timezone(config('documind.display_timezone'))->format('j M Y, H:i') }}
                        </p>
                    </div>
                </details>
            @endif
        </div>
    </div>
</div>
