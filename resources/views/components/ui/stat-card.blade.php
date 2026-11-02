@props(['label', 'value', 'hint' => null])

<div {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-5 shadow-sm') }}>
    <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
    <p class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900">{{ $value }}</p>

    @if ($hint)
        <p class="mt-2 text-xs text-zinc-400">{{ $hint }}</p>
    @endif
</div>
