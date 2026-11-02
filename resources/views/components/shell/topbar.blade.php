@props(['title' => null])

<header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-zinc-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button type="button" class="-m-2 rounded-md p-2 text-zinc-600 hover:bg-zinc-100 lg:hidden" @click="sidebarOpen = true">
        <span class="sr-only">Open sidebar</span>
        <flux:icon.bars-3 class="size-6" />
    </button>

    <h1 class="text-base font-semibold text-zinc-900">{{ $title ?? config('app.name') }}</h1>

    <div class="ml-auto flex items-center gap-3">
        <button
            type="button"
            disabled
            title="Uploads arrive in Week 4"
            class="hidden cursor-not-allowed items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white opacity-50 sm:inline-flex"
        >
            <flux:icon.arrow-up-tray class="size-4" />
            Upload
        </button>

        <x-shell.user-menu />
    </div>
</header>
