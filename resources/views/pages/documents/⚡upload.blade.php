<?php

use App\Actions\Documents\UploadDocument;
use App\Exceptions\DuplicateDocumentException;
use App\Models\Category;
use App\Models\Document;
use App\Support\DocumentUploadRules;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.documind')] #[Title('Upload documents')] class extends Component
{
    use WithFileUploads;

    /** The latest pick or drop. Valid files move into $files. */
    public array $incoming = [];

    /** Files queued for upload. */
    public array $files = [];

    public ?int $categoryId = null;

    /** @var list<array{name: string, ok: bool, message: string, url: string|null}> */
    public array $results = [];

    public function mount(): void
    {
        $this->authorize('create', Document::class);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get(['id', 'name']);
    }

    public function updatedIncoming(): void
    {
        $names = [];
        foreach ($this->incoming as $index => $file) {
            $names["incoming.{$index}"] = $file->getClientOriginalName();
        }

        $this->validate([
            'incoming' => ['array', 'max:' . DocumentUploadRules::maxFiles()],
            'incoming.*' => DocumentUploadRules::file(),
        ], [], $names);

        $this->files = array_slice([...$this->files, ...$this->incoming], 0, DocumentUploadRules::maxFiles());
        $this->incoming = [];
        $this->results = [];
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function save(UploadDocument $uploadDocument): void
    {
        $this->authorize('create', Document::class);

        $business = auth()->user()->currentBusiness;

        $this->validate([
            'files' => ['required', 'array', 'min:1', 'max:' . DocumentUploadRules::maxFiles()],
            'files.*' => DocumentUploadRules::file(),
            'categoryId' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('business_id', $business->id),
            ],
        ], [
            'files.required' => 'Choose at least one file to upload.',
        ]);
        
        $this->results = [];

        $rateKey = 'document-uploads:' . auth()->id();

        if (RateLimiter::tooManyAttempts($rateKey, 100)) {
            $minutes = (int) ceil(RateLimiter::availableIn($rateKey) / 60);
            $this->addError('files', "Upload limit reached. You can upload again in {$minutes} minutes.");

            return;
        }

        foreach ($this->files as $file) {
            RateLimiter::hit($rateKey, 3600); // 100 files per user per hour

            $name = UploadDocument::cleanName($file->getClientOriginalName());

            try {
                $document = $uploadDocument->handle($file, auth()->user(), $business, $this->categoryId);

                $this->results[] = [
                    'name' => $document->original_name,
                    'ok' => true,
                    'message' => 'Uploaded.',
                    'url' => route('documents.show', $document),
                ];
            } catch (DuplicateDocumentException $e) {
                $this->results[] = [
                    'name' => $name,
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'url' => $e->existingDocumentId ? route('documents.show', $e->existingDocumentId) : null,
                ];
            } catch (\Throwable $e) {
                report($e);

                $this->results[] = [
                    'name' => $name,
                    'ok' => false,
                    'message' => 'Something went wrong saving this file. Please try again.',
                    'url' => null,
                ];
            }
        }

        $this->files = [];

        $uploaded = count(array_filter($this->results, fn (array $result): bool => $result['ok']));

        if ($uploaded === count($this->results)) {
            session()->flash('status', $uploaded === 1 ? '1 document uploaded.' : "{$uploaded} documents uploaded.");
            $this->redirectRoute('documents.index', navigate: true);
        }
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900">Upload documents</h2>
        <p class="mt-1 text-sm text-zinc-500">
            PDF, JPG or PNG, up to {{ \Illuminate\Support\Number::fileSize(config('documind.uploads.max_kb') * 1024) }} each,
            {{ \App\Support\DocumentUploadRules::maxFiles() }} files at a time.
            Files are stored privately for {{ auth()->user()->currentBusiness->name }}.
        </p>
    </div>

    @if ($results !== [])
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-zinc-900">Upload results</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($results as $result)
                    <li class="flex items-start gap-2">
                        @if ($result['ok'])
                            <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                        @else
                            <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0 text-amber-500" />
                        @endif
                        <span>
                            <span class="font-medium text-zinc-900">{{ $result['name'] }}</span>
                            <span class="text-zinc-500">— {{ $result['message'] }}</span>
                            @if ($result['url'])
                                <a href="{{ $result['url'] }}" wire:navigate class="ml-1 font-medium text-indigo-600 hover:text-indigo-500">View</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div
            x-data="{ dragging: false, uploading: false, progress: 0 }"
            x-on:dragover.prevent="dragging = true"
            x-on:dragleave.prevent="dragging = false"
            x-on:drop.prevent="
                dragging = false;
                uploading = true;
                $wire.uploadMultiple(
                    'incoming',
                    $event.dataTransfer.files,
                    () => { uploading = false; progress = 0 },
                    () => { uploading = false; progress = 0 },
                    (event) => { progress = event.detail.progress }
                )
            "
            x-on:livewire-upload-start="uploading = true"
            x-on:livewire-upload-finish="uploading = false; progress = 0"
            x-on:livewire-upload-error="uploading = false; progress = 0"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            :class="dragging ? 'border-indigo-500 bg-indigo-50' : 'border-zinc-300 bg-white'"
            class="rounded-xl border-2 border-dashed px-6 py-10 text-center transition"
        >
            <flux:icon.arrow-up-tray class="mx-auto size-8 text-zinc-400" />
            <p class="mt-3 text-sm text-zinc-700">
                <label for="file-input" class="cursor-pointer font-semibold text-indigo-600 hover:text-indigo-500">Choose files</label>
                or drag and drop them here
            </p>
            <p class="mt-1 text-xs text-zinc-500">PDF, JPG or PNG</p>

            <input
                id="file-input"
                type="file"
                multiple
                wire:model="incoming"
                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                class="sr-only"
            >

            <div x-show="uploading" x-cloak class="mx-auto mt-4 max-w-sm">
                <div class="h-2 overflow-hidden rounded-full bg-zinc-100">
                    <div class="h-full bg-indigo-600 transition-all" :style="`width: ${progress}%`"></div>
                </div>
                <p class="mt-1 text-xs text-zinc-500" x-text="`Uploading… ${progress}%`"></p>
            </div>
        </div>

        @foreach ($errors->get('incoming*') as $messages)
            @foreach ($messages as $message)
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @endforeach
        @endforeach

        @if ($files !== [])
            <ul class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white shadow-sm">
                @foreach ($files as $index => $file)
                    <li class="flex items-center gap-4 px-4 py-3 text-sm" wire:key="queued-{{ $file->getFilename() }}">
                        @if (str_starts_with((string) $file->getMimeType(), 'image/'))
                            <img src="{{ $file->temporaryUrl() }}" alt="" class="size-10 shrink-0 rounded object-cover">
                        @else
                            <span class="flex size-10 shrink-0 items-center justify-center rounded bg-rose-50 text-xs font-semibold text-rose-600">PDF</span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-zinc-900">{{ $file->getClientOriginalName() }}</p>
                            <p class="text-xs text-zinc-500">{{ \Illuminate\Support\Number::fileSize($file->getSize(), precision: 1) }}</p>
                        </div>

                        <button type="button" wire:click="removeFile({{ $index }})" class="text-zinc-400 hover:text-rose-600">
                            <span class="sr-only">Remove {{ $file->getClientOriginalName() }}</span>
                            <flux:icon.x-mark class="size-5" />
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        @foreach ($errors->get('files*') as $messages)
            @foreach ($messages as $message)
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @endforeach
        @endforeach

        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-48 flex-1">
                <label for="category" class="block text-sm font-medium text-zinc-700">Category (optional)</label>
                <select
                    id="category"
                    wire:model="categoryId"
                    class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                >
                    <option value="">No category</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('categoryId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <button
                type="submit"
                @disabled($files === [])
                wire:loading.attr="disabled"
                wire:target="save,incoming"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="save">
                    Upload {{ count($files) }} {{ \Illuminate\Support\Str::plural('file', count($files)) }}
                </span>
                <span wire:loading wire:target="save">Uploading…</span>
            </button>
        </div>
    </form>
</div>
