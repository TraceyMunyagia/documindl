<?php

use App\Models\AuditLog;
use App\Models\Document;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.documind')] #[Title('Document')] class extends Component
{
    // Locked: the browser can't swap this for another document's ID.
    #[Locked]
    public Document $document;

    public function mount(Document $document): void
    {
        $this->authorize('view', $document);

        $this->document = $document->load(['category', 'uploader']);
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
            @if (! $document->hasFile())
                <x-ui.empty-state
                    title="No file attached"
                    description="This is a seeded demo record, so there's nothing to preview. Upload a real document to see the preview."
                >
                    <x-slot:icon>
                        <flux:icon.document class="size-6" />
                    </x-slot:icon>
                </x-ui.empty-state>
            @elseif ($document->isPdf())
                <iframe
                    wire:ignore
                    src="{{ route('documents.file', $document) }}"
                    title="Preview of {{ $document->original_name }}"
                    class="h-[75vh] w-full rounded-xl border border-zinc-200 bg-white shadow-sm"
                ></iframe>
            @elseif ($document->isImage())
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                    <img
                        wire:ignore
                        src="{{ route('documents.file', $document) }}"
                        alt="Preview of {{ $document->original_name }}"
                        class="mx-auto max-h-[75vh] object-contain"
                    >
                </div>
            @else
                <x-ui.empty-state title="No preview available" description="Download the file to open it on your device." />
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-zinc-900">Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Uploaded by</dt>
                        <dd class="text-right text-zinc-900">{{ $document->uploader?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Uploaded</dt>
                        <dd class="text-right text-zinc-900">
                            {{ $document->created_at->timezone(config('documind.display_timezone'))->format('j M Y, H:i') }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Size</dt>
                        <dd class="text-right text-zinc-900">
                            {{ $document->size_bytes > 0 ? \Illuminate\Support\Number::fileSize($document->size_bytes, precision: 1) : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">File type</dt>
                        <dd class="text-right text-zinc-900">{{ $document->mime_type ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Checksum</dt>
                        <dd class="text-right font-mono text-xs text-zinc-900" title="{{ $document->checksum }}">
                            {{ $document->checksum ? \Illuminate\Support\Str::limit($document->checksum, 12, '…') : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-dashed border-zinc-200 bg-white p-5 text-sm text-zinc-500">
                <h3 class="font-semibold text-zinc-900">Extracted information</h3>
                <p class="mt-1">Supplier, amounts and due dates will appear here once AI extraction arrives in Week 7.</p>
            </div>
        </div>
    </div>
</div>
