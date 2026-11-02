{{-- Mobile: off-canvas sidebar, opened from the top bar's menu button --}}
<div x-show="sidebarOpen" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        class="fixed inset-0 bg-zinc-900/50"
        @click="sidebarOpen = false"
    ></div>

    <aside
        x-show="sidebarOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 flex w-64 flex-col bg-white shadow-xl"
    >
        <div class="flex h-16 items-center justify-between px-4">
            <x-shell.logo />
            <button type="button" class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100" @click="sidebarOpen = false">
                <span class="sr-only">Close sidebar</span>
                <flux:icon.x-mark class="size-5" />
            </button>
        </div>

        <x-shell.nav />

        <div class="border-t border-zinc-200 p-3">
            <x-shell.workspace-switcher />
        </div>
    </aside>
</div>

{{-- Desktop: fixed sidebar --}}
<aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:flex lg:w-64 lg:flex-col lg:border-r lg:border-zinc-200 lg:bg-white">
    <div class="flex h-16 items-center px-6">
        <x-shell.logo />
    </div>

    <x-shell.nav />
    <div class="border-t border-zinc-200 p-3">
        <x-shell.workspace-switcher />
    </div>
</aside>
