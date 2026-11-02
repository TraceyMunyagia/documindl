@php
    $user = auth()->user();
    $current = $user->currentBusiness;
    $role = $current ? $user->roleIn($current) : null;
    $businesses = $user->businesses()->orderBy('businesses.name')->get();
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button
        type="button"
        class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-zinc-100"
        @click="open = ! open"
        :aria-expanded="open.toString()"
    >
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-sm font-semibold text-white">
            {{ $current ? mb_strtoupper(mb_substr($current->name, 0, 1)) : '?' }}
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-zinc-900">{{ $current?->name ?? 'No business' }}</span>
            <span class="block text-xs text-zinc-500">{{ $role?->label() ?? 'Create one to start' }}</span>
        </span>
        <flux:icon.chevron-up-down class="size-4 shrink-0 text-zinc-400" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute bottom-full left-0 right-0 z-50 mb-2 rounded-xl border border-zinc-200 bg-white p-1 shadow-lg"
    >
        <p class="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-zinc-400">Switch business</p>

        @foreach ($businesses as $business)
            <form method="POST" action="{{ route('businesses.switch', $business) }}">
                @csrf
                <button
                    type="submit"
                    @class([
                        'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm',
                        'bg-indigo-50 text-indigo-700' => $current?->is($business),
                        'text-zinc-700 hover:bg-zinc-100' => ! $current?->is($business),
                    ])
                >
                    <span class="truncate">{{ $business->name }}</span>
                    <span class="ml-auto shrink-0 text-xs text-zinc-400">{{ $business->membership->role->label() }}</span>
                    @if ($current?->is($business))
                        <flux:icon.check class="size-4 shrink-0" />
                    @endif
                </button>
            </form>
        @endforeach

        <div class="my-1 h-px bg-zinc-100"></div>

        <a href="{{ route('businesses.create') }}" wire:navigate class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-700 hover:bg-zinc-100">
            <flux:icon.plus class="size-4" />
            New business
        </a>
    </div>
</div>
