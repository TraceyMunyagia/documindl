@props(['item'])

@php
    $routeName = $item['route'] ?? null;
    $available = $routeName !== null && \Illuminate\Support\Facades\Route::has($routeName);
    $active = $available && request()->routeIs($item['active'] ?? $routeName);
@endphp

@if ($available)
    <a
        href="{{ route($routeName) }}"
        wire:navigate
        @if ($active) aria-current="page" @endif
        @class([
            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
            'bg-indigo-50 text-indigo-700' => $active,
            'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900' => ! $active,
        ])
    >
        <x-shell.icon :name="$item['icon']" />
        {{ $item['label'] }}
    </a>
@else
    <span
        class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-zinc-400"
        title="{{ $item['week'] ? 'Coming in Week ' . $item['week'] : 'Coming soon' }}"
    >
        <x-shell.icon :name="$item['icon']" />
        {{ $item['label'] }}
        @if ($item['week'])
            <span class="ml-auto rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-500">Wk {{ $item['week'] }}</span>
        @endif
    </span>
@endif
