<?php

use App\Enums\DocumentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Document;
use App\Models\Invoice;
use App\ValueObjects\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.documind')] #[Title('Dashboard')] class extends Component
{
    #[Computed]
    public function greeting(): string
    {
        $hour = now(config('documind.display_timezone'))->hour;

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /** @return list<array{label: string, value: string, hint: string}> */
    #[Computed]
    public function stats(): array
    {
        $business = auth()->user()->currentBusiness;

        // No business_id filters needed: the global scope adds them.
        $outstanding = new Money((int) Invoice::query()->outstanding()->sum('total_cents'), $business->currency);

        return [
            [
                'label' => 'Documents processed',
                'value' => (string) Document::query()->where('status', DocumentStatus::Completed->value)->count(),
                'hint' => 'Seeded data until uploads arrive in Week 4',
            ],
            [
                'label' => 'Awaiting approval',
                'value' => (string) Invoice::query()->where('status', InvoiceStatus::Pending->value)->count(),
                'hint' => 'Approval workflow arrives in Week 11',
            ],
            [
                'label' => 'Overdue invoices',
                'value' => (string) Invoice::query()->overdue()->count(),
                'hint' => 'Reminders arrive in Week 12',
            ],
            [
                'label' => 'Outstanding',
                'value' => $outstanding->format(),
                'hint' => 'Unpaid invoice total',
            ],
        ];
    }

    #[Computed]
    public function recentDocuments()
    {
        return Document::query()->latest()->limit(5)->get();
    }

    /** @return list<array{label: string, done: bool, week: int|null}> */
    #[Computed]
    public function milestones(): array
    {
        return [
            ['label' => 'Create your account and verify your email', 'done' => true, 'week' => null],
            ['label' => 'Set up your business workspace', 'done' => true, 'week' => null],
            ['label' => 'Upload your first invoice or receipt', 'done' => false, 'week' => 4],
            ['label' => 'Review AI-extracted fields and save', 'done' => false, 'week' => 8],
        ];
    }
};
?>

<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-semibold tracking-tight text-zinc-900">
            {{ $this->greeting }}, {{ str(auth()->user()->name)->before(' ') }}
        </h2>
        <p class="mt-1 text-sm text-zinc-500">Here's an overview of your business documents.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->stats as $stat)
            <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint']" />
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
                @if ($this->recentDocuments->isEmpty())
            <x-ui.empty-state
                class="lg:col-span-2"
                title="No documents yet"
                description="Upload invoices and receipts, and DocuMind will extract the supplier, amounts and due dates for you to review."
            >
                <x-slot:icon>
                    <flux:icon.document-text class="size-6" />
                </x-slot:icon>

                <x-slot:action>
                    <button type="button" disabled class="cursor-not-allowed rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white opacity-50">
                        Upload a document (Week 4)
                    </button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-zinc-900">Recent documents</h3>
                    <span class="text-xs text-zinc-400">Full list arrives in Week 5</span>
                </div>
                <ul class="divide-y divide-zinc-100">
                    @foreach ($this->recentDocuments as $document)
                        <li class="flex items-center gap-4 px-5 py-3 text-sm" wire:key="doc-{{ $document->id }}">
                            <flux:icon.document-text class="size-5 shrink-0 text-zinc-400" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-zinc-900">{{ $document->original_name }}</p>
                                <p class="text-xs text-zinc-500">
                                    {{ $document->type->label() }} ·
                                    {{ $document->created_at->timezone(config('documind.display_timezone'))->format('j M Y') }}
                                </p>
                            </div>
                            <x-ui.badge :color="$document->status->color()">{{ $document->status->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-zinc-900">Getting started</h3>
            <ul class="mt-4 space-y-3">
                @foreach ($this->milestones as $milestone)
                    <li class="flex items-start gap-3 text-sm">
                        @if ($milestone['done'])
                            <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                            <span class="text-zinc-500 line-through">{{ $milestone['label'] }}</span>
                        @else
                            <span class="mt-0.5 size-5 shrink-0 rounded-full border-2 border-zinc-300"></span>
                            <span class="text-zinc-700">
                                {{ $milestone['label'] }}
                                <span class="ml-1 text-xs text-zinc-400">Week {{ $milestone['week'] }}</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
