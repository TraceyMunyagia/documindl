<?php

use App\Actions\Businesses\CreateBusiness;
use App\Models\BusinessInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.onboarding')] #[Title('Create your business')] class extends Component
{
    public string $name = '';

    public string $currency = 'KES';

    /** @return array<string, string> */
    #[Computed]
    public function currencies(): array
    {
        return [
            'KES' => 'Kenyan shilling (KES)',
            'UGX' => 'Ugandan shilling (UGX)',
            'TZS' => 'Tanzanian shilling (TZS)',
            'RWF' => 'Rwandan franc (RWF)',
            'USD' => 'US dollar (USD)',
        ];
    }

    #[Computed]
    public function invitations()
    {
        return BusinessInvitation::query()
            ->pending()
            ->where('email', Str::lower(auth()->user()->email))
            ->with('business')
            ->get();
    }

    #[Computed]
    public function hasBusinesses(): bool
    {
        return auth()->user()->businesses()->exists();
    }

    public function save(CreateBusiness $createBusiness): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'currency' => ['required', Rule::in(array_keys($this->currencies))],
        ]);

        $createBusiness->handle(auth()->user(), $this->name, $this->currency);

        $this->redirectRoute('dashboard', navigate: true);
    }
};
?>

<div class="space-y-6">
    @if ($this->invitations->isNotEmpty())
        <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-5">
            <h2 class="text-sm font-semibold text-indigo-900">You've been invited</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($this->invitations as $invitation)
                    <li class="flex items-center justify-between gap-4 text-sm" wire:key="invitation-{{ $invitation->id }}">
                        <span class="text-indigo-900">
                            {{ $invitation->business->name }}
                            <span class="text-indigo-600">as {{ $invitation->role->label() }}</span>
                        </span>
                        <a href="{{ route('invitations.accept', $invitation->token) }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 font-semibold text-white hover:bg-indigo-500">
                            Accept
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="save" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold tracking-tight">Create a business workspace</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Each business keeps its own documents, invoices and team. You can create or join more later.
        </p>

        <div class="mt-6 space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-zinc-700">Business name</label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    autofocus
                    placeholder="e.g. Kilimani Bakery Ltd"
                    class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                >
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="currency" class="block text-sm font-medium text-zinc-700">Currency</label>
                <select
                    id="currency"
                    wire:model="currency"
                    class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                >
                    @foreach ($this->currencies as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('currency') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="save">Create business</span>
            <span wire:loading wire:target="save">Creating…</span>
        </button>

        @if ($this->hasBusinesses)
            <a href="{{ route('dashboard') }}" wire:navigate class="mt-4 block text-center text-sm text-zinc-500 hover:text-zinc-900">
                Back to dashboard
            </a>
        @endif
    </form>
</div>
