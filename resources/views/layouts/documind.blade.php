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
<body class="h-full text-zinc-900 antialiased" x-data="{ sidebarOpen: false }">
    <x-shell.sidebar />

    <div class="lg:pl-64">
        <x-shell.topbar :title="$title ?? null" />

               <main class="px-4 py-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
