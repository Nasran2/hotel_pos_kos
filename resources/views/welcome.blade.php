<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Hotel POS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f8fc] text-slate-800 antialiased">
    <main class="mx-auto grid min-h-screen w-full max-w-6xl place-items-center px-6 py-10">
        <section class="w-full rounded-[1.25rem] border border-slate-200/80 bg-white p-8 shadow-sm shadow-slate-900/5 lg:p-10">
            <div class="grid gap-8 lg:grid-cols-[1.1fr_.9fr] lg:items-center">
                <div>
                    <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-blue-600">Hotel POS Platform</span>
                    <h1 class="mt-5 text-4xl font-black leading-tight text-slate-900 lg:text-5xl">Modern restaurant operations with one clean dashboard system.</h1>
                    <p class="mt-4 max-w-2xl text-base text-slate-500 lg:text-lg">Run POS, inventory, purchases, reports, and settings from one consistent UI with fast workflows and role-based access.</p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('dashboard') }}" class="btn-primary">Open Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="btn-primary">Login</a>
                            @endauth
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <article class="summary-card summary-card-blue">
                        <p class="summary-card-title">Daily Sales</p>
                        <p class="summary-card-value">Live</p>
                        <p class="summary-card-trend text-emerald-600">Connected to dashboard filters</p>
                    </article>
                    <article class="summary-card summary-card-green">
                        <p class="summary-card-title">Kitchen & Tables</p>
                        <p class="summary-card-value">Real-time</p>
                        <p class="summary-card-trend text-emerald-600">Status synced to POS flow</p>
                    </article>
                    <article class="summary-card summary-card-orange sm:col-span-2">
                        <p class="summary-card-title">Reports & Controls</p>
                        <p class="summary-card-value">Owner ready</p>
                        <p class="summary-card-trend text-orange-600">Profit, expenses, waiter performance, and activity insights</p>
                    </article>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
