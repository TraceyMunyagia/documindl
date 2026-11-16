@props(['column', 'sort', 'direction'])

<button
    type="button"
    wire:click="sortBy('{{ $column }}')"
    class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wide hover:text-zinc-900"
>
    {{ $slot }}

    @if ($sort === $column)
        @if ($direction === 'asc')
            <flux:icon.chevron-up class="size-3.5" />
        @else
            <flux:icon.chevron-down class="size-3.5" />
        @endif
    @else
        <flux:icon.chevron-up-down class="size-3.5 opacity-0 group-hover:opacity-60" />
    @endif
</button>
