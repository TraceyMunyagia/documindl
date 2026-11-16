<?php

use App\Models\Document;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.documind')] #[Title('Documents')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Document::class);
    }

    #[Computed]
    public function documents()
    {
        // with() loads categories and uploaders in two queries instead of one per row.
        return Document::query()
            ->with(['category', 'uploader'])
            ->latest()
            ->paginate(15);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900">Documents</h2>
            <p class="mt-1 text-sm text-zinc-500">
                {{ $this->documents->total() }} {{ \Illuminate\Support\Str::plural('document', $this->documents->total()) }}
                in {{ auth()->user()->currentBusiness->name }}. Search and filters arrive in Week 5.
            </p>
        </div>

        <a
            href="{{ route('documents.upload') }}"
            wire:navigate
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
        >
            <flux:icon.arrow-up-tray class="size-4" />
            Upload
        </a>
    </div>

    @if ($this->documents->isEmpty())
        <x-ui.empty-state title="No documents yet" description="Upload your first invoice, receipt or contract to get started.">
            <x-slot:icon>
                <flux:icon.document-text class="size-6" />
            </x-slot:icon>
            <x-slot:action>
                <a href="{{ route('documents.upload') }}" wire:navigate class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Upload documents
                </a>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Name</th>
                        <th scope="col" class="hidden px-4 py-3 md:table-cell">Category</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Size</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Uploaded</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($this->documents as $document)
                        <tr wire:key="document-{{ $document->id }}" class="hover:bg-zinc-50">
                            <td class="max-w-xs px-4 py-3">
                                <a
                                    href="{{ route('documents.show', $document) }}"
                                    wire:navigate
                                    class="block truncate font-medium text-zinc-900 hover:text-indigo-600"
                                >
                                    {{ $document->original_name }}
                                </a>
                                <p class="text-xs text-zinc-500">{{ $document->type->label() }}</p>
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">
                                @if ($document->category)
                                    <x-ui.badge :color="$document->category->color">{{ $document->category->name }}</x-ui.badge>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge :color="$document->status->color()">{{ $document->status->label() }}</x-ui.badge>
                            </td>
                            <td class="hidden px-4 py-3 text-zinc-500 lg:table-cell">
                                {{ $document->size_bytes > 0 ? \Illuminate\Support\Number::fileSize($document->size_bytes, precision: 1) : '—' }}
                            </td>
                            <td class="hidden px-4 py-3 text-zinc-500 lg:table-cell">
                                {{ $document->created_at->timezone(config('documind.display_timezone'))->format('j M Y, H:i') }}
                                <span class="block text-xs">{{ $document->uploader?->name ?? '—' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $this->documents->links() }}
    @endif
</div>
