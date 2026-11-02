@props(['title', 'description' => null])

<div {{ $attributes->class('rounded-xl border-2 border-dashed border-zinc-200 bg-white px-6 py-12 text-center') }}>
    @isset($icon)
        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
            {{ $icon }}
        </div>
    @endisset

    <h3 class="mt-4 text-base font-semibold text-zinc-900">{{ $title }}</h3>

    @if ($description)
        <p class="mx-auto mt-1 max-w-md text-sm text-zinc-500">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-6">{{ $action }}</div>
    @endisset
</div>
