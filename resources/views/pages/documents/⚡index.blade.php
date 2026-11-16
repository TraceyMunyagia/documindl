<?php

use App\Actions\Documents\DeleteDocuments;
use App\Actions\Documents\MoveDocumentsToCategory;
use App\Models\Category;
use App\Models\Document;
use App\Queries\DocumentIndexQuery;
use App\Support\DocumentFilters;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.documind')] #[Title('Documents')] class extends Component
{
    use WithPagination;

    private const array FILTERS = ['search', 'type', 'status', 'category', 'uploader', 'from', 'to'];

    // Each #[Url] property is mirrored in the address bar, e.g. /documents?status=pending&sort=size_bytes
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $uploader = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 'created_at')]
    public string $sort = 'created_at';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    /** Selected document IDs (strings, straight from the checkboxes). */
    public array $selected = [];

    public string $bulkCategory = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Document::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function selectPage(): void
    {
        $this->selected = $this->documents->pluck('id')->map(fn (int $id): string => (string) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->bulkCategory = '';
    }

    public function moveSelected(MoveDocumentsToCategory $moveDocuments): void
    {
        $this->validate([
            'selected' => ['required', 'array', 'min:1'],
            'bulkCategory' => ['required', 'string'],
        ], [
            'bulkCategory.required' => 'Choose where to move the documents.',
        ]);

        $category = $this->bulkCategory === 'none'
            ? null
            : Category::query()->findOrFail((int) $this->bulkCategory);

        $requested = count($this->selected);
        $moved = $moveDocuments->handle(auth()->user(), array_map('intval', $this->selected), $category);

        $this->notice = $this->summary('Moved', $moved, $requested);
        $this->clearSelection();
        unset($this->documents);
    }

    public function deleteSelected(DeleteDocuments $deleteDocuments): void
    {
        $this->validate(['selected' => ['required', 'array', 'min:1']]);

        $requested = count($this->selected);
        $deleted = $deleteDocuments->handle(auth()->user(), array_map('intval', $this->selected));

        $this->notice = $this->summary('Deleted', $deleted, $requested);
        $this->clearSelection();
        unset($this->documents);
    }

    private function summary(string $verb, int $done, int $requested): string
    {
        $message = "{$verb} {$done} " . \Illuminate\Support\Str::plural('document', $done) . '.';
        $skipped = $requested - $done;

        return $skipped > 0 ? "{$message} {$skipped} skipped because you don't have permission." : $message;
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, DocumentFilters::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = $column === 'original_name' ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function removeFilter(string $filter): void
    {
        if (in_array($filter, self::FILTERS, true)) {
            $this->reset($filter);
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(self::FILTERS);
        $this->resetPage();
    }

    #[Computed]
    public function filters(): DocumentFilters
    {
        return DocumentFilters::fromInput($this->only([...self::FILTERS, 'sort', 'direction']));
    }

    #[Computed]
    public function documents()
    {
        return app(DocumentIndexQuery::class)->build($this->filters)->paginate(15);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get(['id', 'name', 'color']);
    }

    #[Computed]
    public function uploaders()
    {
        return auth()->user()->currentBusiness->members()->orderBy('name')->get(['users.id', 'users.name']);
    }

    /** @return array<string, string> filter key => chip label */
    #[Computed]
    public function activeFilters(): array
    {
        $filters = $this->filters;
        $chips = [];

        if ($filters->search !== '') {
            $chips['search'] = "Search: {$filters->search}";
        }
        if ($filters->type !== null) {
            $chips['type'] = 'Type: ' . $filters->type->label();
        }
        if ($filters->status !== null) {
            $chips['status'] = 'Status: ' . $filters->status->label();
        }
        if ($filters->category === 'none') {
            $chips['category'] = 'Uncategorised';
        } elseif ($filters->category !== null) {
            $chips['category'] = 'Category: ' . ($this->categories->firstWhere('id', (int) $filters->category)?->name ?? 'Unknown');
        }
        if ($filters->uploadedBy !== null) {
            $chips['uploader'] = 'Uploaded by ' . ($this->uploaders->firstWhere('id', $filters->uploadedBy)?->name ?? 'Unknown');
        }
        if ($filters->from !== null) {
            $chips['from'] = 'From ' . Carbon::parse($filters->from)->format('j M Y');
        }
        if ($filters->to !== null) {
            $chips['to'] = 'To ' . Carbon::parse($filters->to)->format('j M Y');
        }

        return $chips;
    }
};
?>

<div
    class="space-y-6"
    @if ($this->documents->contains(fn ($document) => $document->status->isActive())) wire:poll.5s.visible @endif>    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900">Documents</h2>
            <p class="mt-1 text-sm text-zinc-500">
                {{ $this->documents->total() }} {{ \Illuminate\Support\Str::plural('document', $this->documents->total()) }}
                @if ($this->activeFilters !== [])
                    match your filters
                @else
                    in {{ auth()->user()->currentBusiness->name }}
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            @can('viewTrash', \App\Models\Document::class)
                <a
                    href="{{ route('documents.trash') }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50"
                >
                    <flux:icon.trash class="size-4" />
                    Deleted
                </a>
            @endcan

            <a
                href="{{ route('documents.upload') }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
            >
                <flux:icon.arrow-up-tray class="size-4" />
                Upload
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="relative sm:col-span-2">
                <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name, supplier or invoice no."
                    aria-label="Search documents"
                    class="block w-full rounded-lg border border-zinc-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                >
            </div>

            <select wire:model.live="type" aria-label="Type" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                <option value="">All types</option>
                @foreach (\App\Enums\DocumentType::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="status" aria-label="Status" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                <option value="">All statuses</option>
                @foreach (\App\Enums\DocumentStatus::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="category" aria-label="Category" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                <option value="">All categories</option>
                <option value="none">Uncategorised</option>
                @foreach ($this->categories as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="uploader" aria-label="Uploaded by" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                <option value="">Anyone</option>
                @foreach ($this->uploaders as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-zinc-600">
            <label class="flex items-center gap-2">
                From
                <input type="date" wire:model.live="from" max="{{ $to }}" class="rounded-lg border border-zinc-300 px-2 py-1.5 text-sm shadow-sm">
            </label>
            <label class="flex items-center gap-2">
                To
                <input type="date" wire:model.live="to" min="{{ $from }}" class="rounded-lg border border-zinc-300 px-2 py-1.5 text-sm shadow-sm">
            </label>
            <span wire:loading.delay class="text-xs text-zinc-400">Updating…</span>
        </div>
    </div>

    @if ($this->activeFilters !== [])
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->activeFilters as $key => $label)
                <button
                    type="button"
                    wire:click="removeFilter('{{ $key }}')"
                    class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                >
                    {{ $label }}
                    <flux:icon.x-mark class="size-3.5" />
                </button>
            @endforeach

            <button type="button" wire:click="clearFilters" class="text-xs font-medium text-zinc-500 hover:text-zinc-900">Clear all</button>
        </div>
    @endif

    {{-- Outside the branches below, so it still shows after the last document is deleted or moved away. --}}
    @if ($notice)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
    @endif

    @if ($this->documents->isEmpty() && $this->activeFilters === [])
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
    @elseif ($this->documents->isEmpty())
        <x-ui.empty-state title="No documents match" description="Try a different search, or remove some filters.">
            <x-slot:icon>
                <flux:icon.magnifying-glass class="size-6" />
            </x-slot:icon>
            <x-slot:action>
                <button type="button" wire:click="clearFilters" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                    Clear all filters
                </button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        @if ($selected !== [])
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm">
                <span class="font-medium text-indigo-900">{{ count($selected) }} selected</span>

                <select wire:model="bulkCategory" aria-label="Move to category" class="rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm">
                    <option value="">Move to…</option>
                    <option value="none">Uncategorised</option>
                    @foreach ($this->categories as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="moveSelected" class="rounded-lg bg-indigo-600 px-3 py-1.5 font-semibold text-white hover:bg-indigo-500">Move</button>

                <button
                    type="button"
                    wire:click="deleteSelected"
                    wire:confirm="Delete the selected documents? Documents you don't have permission to delete will be skipped."
                    class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 font-semibold text-rose-600 hover:bg-rose-50"
                >
                    Delete
                </button>

                <button type="button" wire:click="clearSelection" class="ml-auto text-indigo-700 hover:text-indigo-900">Clear selection</button>

                @error('bulkCategory') <p class="w-full text-rose-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm" wire:loading.class="opacity-60">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-zinc-500">
                    <tr>
                        <th scope="col" class="w-10 px-4 py-3">
                            <input
                                type="checkbox"
                                aria-label="Select all on this page"
                                wire:click="{{ $selected === [] ? 'selectPage' : 'clearSelection' }}"
                                @checked($selected !== [])
                                class="rounded border-zinc-300"
                            >
                        </th>
                        <th scope="col" class="px-4 py-3">
                            <x-ui.sort-button column="original_name" :sort="$sort" :direction="$direction">Name</x-ui.sort-button>
                        </th>
                        <th scope="col" class="hidden px-4 py-3 text-xs font-medium uppercase tracking-wide md:table-cell">Category</th>
                        <th scope="col" class="px-4 py-3">
                            <x-ui.sort-button column="status" :sort="$sort" :direction="$direction">Status</x-ui.sort-button>
                        </th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">
                            <x-ui.sort-button column="size_bytes" :sort="$sort" :direction="$direction">Size</x-ui.sort-button>
                        </th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">
                            <x-ui.sort-button column="created_at" :sort="$sort" :direction="$direction">Uploaded</x-ui.sort-button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($this->documents as $document)
                        <tr wire:key="document-{{ $document->id }}" class="hover:bg-zinc-50">
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    value="{{ $document->id }}"
                                    wire:model.live="selected"
                                    aria-label="Select {{ $document->original_name }}"
                                    class="rounded border-zinc-300"
                                >
                            </td>
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
                                {{ \Illuminate\Support\Number::fileSize($document->size_bytes, precision: 1) }}
                            </td>
                            <td class="hidden px-4 py-3 text-zinc-500 lg:table-cell">
                                {{ $document->created_at->timezone(config('documind.display_timezone'))->format('j M Y') }}
                                <span class="block text-xs text-zinc-400">{{ $document->uploader?->name ?? '—' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $this->documents->links() }}
    @endif
</div>
