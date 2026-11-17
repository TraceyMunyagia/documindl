@props([
    'label',
    'model',               // e.g. "form.supplier"
    'current' => '',       // the form's current value
    'aiValue' => null,     // what the AI read (null when there was no AI extraction)
    'level' => null,       // "high" | "medium" | "low" | null
    'missing' => false,
    'error' => null,
    'type' => 'text',
    'inputmode' => null,
    'prefix' => null,
])

@php
    $normalise = fn ($value) => mb_strtolower(preg_replace('/[\s,]+/u', '', (string) $value) ?? '');
    $edited = $aiValue !== null && $normalise($current) !== $normalise($aiValue);

    $border = match (true) {
        (bool) $error => 'border-rose-400 ring-2 ring-rose-100',
        $missing && ! $edited => 'border-rose-300 bg-rose-50/50',
        $edited => 'border-indigo-300',
        $level === 'low' => 'border-rose-300',
        $level === 'medium' => 'border-amber-300',
        default => 'border-zinc-300',
    };
@endphp

<div>
    <div class="flex items-center justify-between gap-2">
        <label for="{{ $model }}" class="text-sm font-medium text-zinc-700">{{ $label }}</label>

        @if ($edited)
            <span class="text-xs font-medium text-indigo-600">Edited</span>
        @elseif ($missing)
            <span class="text-xs font-medium text-rose-600">Not found – please enter</span>
        @elseif ($level === 'low')
            <span class="text-xs font-medium text-rose-600">Low confidence – check</span>
        @elseif ($level === 'medium')
            <span class="text-xs font-medium text-amber-700">Check this</span>
        @endif
    </div>

    <div class="mt-1 flex rounded-lg border {{ $border }} bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20">
        @if ($prefix)
            <span class="flex items-center rounded-l-lg border-r border-zinc-200 bg-zinc-50 px-3 text-sm text-zinc-500">{{ $prefix }}</span>
        @endif

        <input
            id="{{ $model }}"
            type="{{ $type }}"
            wire:model.blur="{{ $model }}"
            @if ($inputmode) inputmode="{{ $inputmode }}" @endif
            class="min-w-0 flex-1 rounded-lg border-0 bg-transparent px-3 py-2 text-sm focus:outline-none"
        >
    </div>

    @if ($edited)
        <p class="mt-1 text-xs text-zinc-500">AI read: {{ $aiValue !== '' ? $aiValue : '(nothing)' }}</p>
    @endif

    @if ($error)
        <p class="mt-1 text-xs text-rose-600">{{ $error }}</p>
    @endif
</div>
