<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'DocuMind') }} · Business documents that work for you</title>
    @vite('resources/css/app.css')
</head>
<body class="h-full text-zinc-900 antialiased">
    <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
        <a href="{{ url('/') }}" class="flex items-center gap-2">
            <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">D</span>
            <span class="text-base font-semibold tracking-tight">DocuMind</span>
        </a>

        <nav class="flex items-center gap-3 text-sm font-medium">
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-500">Go to dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-zinc-700 hover:bg-zinc-100">Log in</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-500">Get started</a>
                @endif
            @endauth
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-6">
        <section class="py-16 text-center sm:py-24">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">For small businesses</p>
            <h1 class="mx-auto mt-3 max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">
                Turn invoices, receipts and contracts into answers
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-zinc-600">
                Upload your business documents. DocuMind extracts the details, tracks what's due, flags duplicates,
                and answers questions about your spending.
            </p>
            <div class="mt-10 flex justify-center gap-3">
                <a href="{{ Route::has('register') ? route('register') : route('login') }}" class="rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-500">
                    Create a free account
                </a>
                <a href="{{ route('login') }}" class="rounded-lg border border-zinc-300 px-5 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                    Log in
                </a>
            </div>
        </section>

        <section class="grid gap-6 pb-24 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-6">
                <h2 class="font-semibold">Smart extraction</h2>
                <p class="mt-2 text-sm text-zinc-600">Supplier, invoice number, dates, tax and totals pulled out automatically, then checked by you.</p>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6">
                <h2 class="font-semibold">Never miss a due date</h2>
                <p class="mt-2 text-sm text-zinc-600">Reminders for invoice deadlines and contract renewals, plus approvals for your team.</p>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6">
                <h2 class="font-semibold">Ask your records</h2>
                <p class="mt-2 text-sm text-zinc-600">"Which invoices are overdue?" Answers backed by your own verified documents.</p>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-200 py-8 text-center text-sm text-zinc-500">
        &copy; {{ date('Y') }} DocuMind
    </footer>
</body>
</html>
