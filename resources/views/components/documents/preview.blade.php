@props(['document'])

@if (! $document->hasFile())
    <x-ui.empty-state
        title="No file attached"
        description="This is a seeded demo record, so there's nothing to preview."
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
