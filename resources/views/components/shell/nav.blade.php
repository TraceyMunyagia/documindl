<nav {{ $attributes->class('flex flex-1 flex-col gap-1 px-3 py-4') }} aria-label="Main">
    @foreach (config('documind.navigation') as $item)
        <x-shell.nav-link :item="$item" />
    @endforeach
</nav>
