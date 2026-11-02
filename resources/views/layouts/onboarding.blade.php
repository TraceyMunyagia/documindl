<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ config('app.name', 'DocuMind') }}</title>
    <style>[x-cloak] { display: none !important; }</style>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-full flex-col items-center justify-center px-4 py-12 text-zinc-900 antialiased">
    <div class="w-full max-w-lg">
        <div class="mb-8 flex items-center justify-between">
            <x-shell.logo />

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-zinc-500 hover:text-zinc-900">Log out</button>
            </form>
        </div>

        {{ $slot }}
    </div>
</body>
</html>
