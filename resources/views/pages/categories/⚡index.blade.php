<?php

use App\Enums\CategoryColor;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.documind')] #[Title('Categories')] class extends Component
{
    public string $name = '';

    public string $color = 'indigo';

    public ?int $editingId = null;

    public string $editName = '';

    public string $editColor = 'indigo';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->withCount('documents')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Category::class);

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', Rule::enum(CategoryColor::class)],
        ]);

        $category = Category::create([
            'name' => trim($this->name),
            'slug' => $this->uniqueSlug($this->name, 'name'),
            'color' => $this->color,
        ]);

        AuditLog::record('category.created', $category, ['name' => $category->name]);

        $this->notice = "Created {$category->name}.";
        $this->reset('name');
        unset($this->categories);
    }

    public function edit(int $id): void
    {
        $category = Category::query()->findOrFail($id);
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->editName = $category->name;
        $this->editColor = $category->color;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editName', 'editColor');
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $category = Category::query()->findOrFail($this->editingId);
        $this->authorize('update', $category);

        $this->validate([
            'editName' => ['required', 'string', 'max:60'],
            'editColor' => ['required', Rule::enum(CategoryColor::class)],
        ]);

        $category->update([
            'name' => trim($this->editName),
            'slug' => $this->uniqueSlug($this->editName, 'editName', $category->id),
            'color' => $this->editColor,
        ]);

        AuditLog::record('category.updated', $category, ['fields' => array_keys($category->getChanges())]);

        $this->notice = "Updated {$category->name}.";
        $this->cancelEdit();
        unset($this->categories);
    }

    public function delete(int $id): void
    {
        $category = Category::query()->withCount('documents')->findOrFail($id);
        $this->authorize('delete', $category);

        // The foreign key is nullOnDelete, so its documents become uncategorised – nothing is lost.
        $category->delete();

        AuditLog::record('category.deleted', $category, [
            'name' => $category->name,
            'documents_uncategorised' => $category->documents_count,
        ]);

        $this->notice = "Deleted {$category->name}. Its {$category->documents_count} documents are now uncategorised.";
        unset($this->categories);
    }

    /** Names must be unique per business (the slug carries the unique index). */
    private function uniqueSlug(string $name, string $field, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);

        $taken = Category::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($slug === '' || $taken) {
            throw ValidationException::withMessages([$field => 'A category with this name already exists.']);
        }

        return $slug;
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900">Categories</h2>
        <p class="mt-1 text-sm text-zinc-500">Group documents so they're easier to find and report on.</p>
    </div>

    @if ($notice)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
    @endif

    @can('create', \App\Models\Category::class)
        <form wire:submit="create" class="flex flex-wrap items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="min-w-48 flex-1">
                <label for="new-name" class="sr-only">Category name</label>
                <input
                    id="new-name"
                    type="text"
                    wire:model="name"
                    placeholder="New category, e.g. Marketing"
                    class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                >
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <label for="new-color" class="sr-only">Colour</label>
            <select id="new-color" wire:model="color" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                @foreach (\App\Enums\CategoryColor::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>

            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Add category
            </button>
        </form>
    @endcan

    <ul class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white shadow-sm">
        @forelse ($this->categories as $category)
            <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="category-{{ $category->id }}">
                @if ($editingId === $category->id)
                    <div class="min-w-48 flex-1">
                        <input
                            type="text"
                            wire:model="editName"
                            wire:keydown.escape="cancelEdit"
                            aria-label="Category name"
                            class="block w-full rounded-lg border border-zinc-300 px-3 py-1.5 text-sm shadow-sm"
                        >
                        @error('editName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <select wire:model="editColor" aria-label="Colour" class="rounded-lg border border-zinc-300 px-2 py-1.5 text-sm shadow-sm">
                        @foreach (\App\Enums\CategoryColor::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>

                    <button type="button" wire:click="saveEdit" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Save</button>
                    <button type="button" wire:click="cancelEdit" class="text-sm text-zinc-500 hover:text-zinc-900">Cancel</button>
                @else
                    <span class="size-3 shrink-0 rounded-full {{ \App\Enums\CategoryColor::tryFrom($category->color)?->swatchClass() ?? 'bg-zinc-500' }}"></span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900">{{ $category->name }}</p>
                        <a
                            href="{{ route('documents.index', ['category' => $category->id]) }}"
                            wire:navigate
                            class="text-xs text-zinc-500 hover:text-indigo-600"
                        >
                            {{ $category->documents_count }} {{ \Illuminate\Support\Str::plural('document', $category->documents_count) }}
                        </a>
                    </div>

                    @can('update', $category)
                        <button type="button" wire:click="edit({{ $category->id }})" class="text-sm text-zinc-600 hover:text-zinc-900">Edit</button>
                    @endcan

                    @can('delete', $category)
                        <button
                            type="button"
                            wire:click="delete({{ $category->id }})"
                            wire:confirm="Delete {{ $category->name }}? Its {{ $category->documents_count }} documents will become uncategorised."
                            class="text-sm text-rose-600 hover:text-rose-700"
                        >
                            Delete
                        </button>
                    @endcan
                @endif
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-zinc-500">No categories yet.</li>
        @endforelse
    </ul>
</div>
