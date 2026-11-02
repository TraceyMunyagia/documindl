@props(['color' => 'zinc'])

@php
    $classes = match ($color) {
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        default => 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
    };
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}") }}>
    {{ $slot }}
</span>
