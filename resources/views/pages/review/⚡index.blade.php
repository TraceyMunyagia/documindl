<?php

use App\Models\Document;
use App\Review\ReviewStats;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.documind')] #[Title('Review')] class extends Component
{
    use WithPagination;

    #[Computed]
    public function documents()
    {
        // Oldest first: the longest-waiting documents get reviewed first.
        return Document::query()
            ->reviewableBy(auth()->user())
            ->with(['latestExtraction', 'uploader'])
            ->orderBy('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function stats(): array
    {
        return app(ReviewStats::class)->lastDays(30);
    }
};
?>

<div class="space-y-6">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900">Review</h2>
        <p class="mt-1 text-sm text-zinc-500">
            {{ $this->documents->total() }} {{ \Illuminate\Support\Str::plural('document', $this->documents->total()) }}
            waiting for someone to check the extracted details.
        </p>
    </div>

    @if ($this->stats['reviewed'] > 0)
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-zinc-900">AI accuracy, last 30 days</h3>
            <dl class="mt-3 grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-zinc-500">Reviewed</dt>
                    <dd class="text-2xl font-semibold text-zinc-900">{{ $this->stats['reviewed'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">Needed no changes</dt>
                    <dd class="text-2xl font-semibold text-zinc-900">{{ $this->stats['unchanged_percent'] }}%</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">Typical review time</dt>
                    <dd class="text-2xl font-semibold text-zinc-900">
                        {{ $this->stats['median_seconds'] !== null ? $this->stats['median_seconds'] . 's' : '—' }}
                    </dd>
                </div>
            </dl>

            @if ($this->stats['field_rates'] !== [])
                <div class="mt-4 border-t border-zinc-100 pt-4">
                    <p class="text-xs font-medium text-zinc-500">Most often corrected</p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach (array_slice($this->stats['field_rates'], 0, 4, true) as $field => $rate)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="w-36 shrink-0 text-zinc-700">{{ ucfirst(str_replace(['_cents', '_'], ['', ' '], $field)) }}</span>
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100">
                                    <span class="block h-full rounded-full bg-amber-500" style="width: {{ $rate }}%"></span>
                                </span>
                                <span class="w-10 text-right text-zinc-500">{{ $rate }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if ($this->documents->isEmpty())
        <x-ui.empty-state title="All caught up" description="Nothing is waiting for review right now." />
    @else
        <ul class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white shadow-sm">
            @foreach ($this->documents as $document)
                @php $extraction = $document->latestExtraction; @endphp

                <li class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="review-{{ $document->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900">
                            {{ $extraction?->field('supplier_name') ?? $document->original_name }}
                            @if ($extraction?->money('total'))
                                <span class="font-normal text-zinc-500">· {{ $extraction->money('total')->format() }}</span>
                            @endif
                        </p>
                        <p class="mt-0.5 truncate text-xs text-zinc-500">
                            {{ $document->original_name }} ·
                            uploaded {{ $document->created_at->diffForHumans() }}
                            by {{ $document->uploader?->name ?? '—' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($extraction === null)
                            <x-ui.badge color="rose">No text – enter by hand</x-ui.badge>
                        @elseif ($extraction->usedAi())
                            <x-ui.badge color="indigo">AI</x-ui.badge>
                        @else
                            <x-ui.badge>Rules</x-ui.badge>
                        @endif

                        @if ($extraction && $extraction->missing_fields !== [])
                            <x-ui.badge color="rose">{{ count($extraction->missing_fields) }} missing</x-ui.badge>
                        @endif

                        @if ($extraction && $extraction->warnings !== [])
                            <x-ui.badge color="amber">{{ count($extraction->warnings) }} {{ \Illuminate\Support\Str::plural('warning', count($extraction->warnings)) }}</x-ui.badge>
                        @endif
                    </div>

                    <a
                        href="{{ route('documents.review', $document) }}"
                        wire:navigate
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Review
                    </a>
                </li>
            @endforeach
        </ul>

        {{ $this->documents->links() }}
    @endif
</div>
