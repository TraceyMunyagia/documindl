<?php

use App\Actions\Documents\PurgeDocument;
use App\Models\AuditLog;
use App\Models\Document;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.documind')] #[Title('Deleted documents')] class extends Component
{
    use WithPagination;

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('viewTrash', Document::class);
    }

    #[Computed]
    public function documents()
    {
        return Document::onlyTrashed()
            ->with('uploader')
            ->latest('deleted_at')
            ->paginate(15);
    }

    public function restore(int $id): void
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $document);

        $document->restore();
        AuditLog::record('document.restored', $document);

        $this->notice = "Restored {$document->original_name}.";
        unset($this->documents);
    }

    public function purge(int $id): void
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $document);

        $name = $document->original_name;
        app(PurgeDocument::class)->handle($document);

        $this->notice = "Permanently deleted {$name}.";
        unset($this->documents);
    }
};
?>

<div class="space-y-6">
    <div>
        <a href="{{ route('documents.index') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-900">&larr; Documents</a>
        <h2 class="mt-1 text-xl font-semibold tracking-tight text-zinc-900">Deleted documents</h2>
        <p class="mt-1 text-sm text-zinc-500">Managers can restore documents. Only the owner can delete them permanently.</p>
    </div>

    @if ($notice)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
    @endif

    @if ($this->documents->isEmpty())
        <x-ui.empty-state title="Nothing here" description="Deleted documents appear here until they're restored or permanently deleted.">
            <x-slot:icon>
                <flux:icon.trash class="size-6" />
            </x-slot:icon>
        </x-ui.empty-state>
    @else
        <ul class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white shadow-sm">
            @foreach ($this->documents as $document)
                <li class="flex flex-wrap items-center gap-4 px-4 py-3 text-sm" wire:key="trashed-{{ $document->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-zinc-900">{{ $document->original_name }}</p>
                        <p class="text-xs text-zinc-500">
                            Deleted {{ $document->deleted_at->timezone(config('documind.display_timezone'))->format('j M Y, H:i') }}
                            · uploaded by {{ $document->uploader?->name ?? '—' }}
                        </p>
                    </div>

                    @can('restore', $document)
                        <button type="button" wire:click="restore({{ $document->id }})" class="font-semibold text-indigo-600 hover:text-indigo-500">Restore</button>
                    @endcan

                    @can('forceDelete', $document)
                        <button
                            type="button"
                            wire:click="purge({{ $document->id }})"
                            wire:confirm="Permanently delete {{ $document->original_name }}? The file is removed and this cannot be undone."
                            class="text-rose-600 hover:text-rose-700"
                        >
                            Delete forever
                        </button>
                    @endcan
                </li>
            @endforeach
        </ul>

        {{ $this->documents->links() }}
    @endif
</div>
