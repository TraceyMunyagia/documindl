@php
    $user = auth()->user();

    $initials = \Illuminate\Support\Str::of($user->name)
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $settingsUrl = \Illuminate\Support\Facades\Route::has('profile.edit')
        ? route('profile.edit')
        : url('settings/profile');
@endphp

<div
    class="relative"
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 text-sm hover:bg-zinc-100"
        @click="open = ! open"
        :aria-expanded="open.toString()"
    >
        <span class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white">{{ $initials }}</span>
        <span class="hidden font-medium text-zinc-700 md:block">{{ $user->name }}</span>
        <flux:icon.chevron-down class="size-4 text-zinc-400" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.origin.top.right
        class="absolute right-0 z-50 mt-2 w-60 rounded-xl border border-zinc-200 bg-white p-1 shadow-lg"
    >
        <div class="px-3 py-2">
            <p class="truncate text-sm font-medium text-zinc-900">{{ $user->name }}</p>
            <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
        </div>

        <div class="my-1 h-px bg-zinc-100"></div>

        <a href="{{ $settingsUrl }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-700 hover:bg-zinc-100">
            <flux:icon.cog-6-tooth class="size-4" />
            Settings
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100">
                <flux:icon.arrow-right-start-on-rectangle class="size-4" />
                Log out
            </button>
        </form>
    </div>
</div>
