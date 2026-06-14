<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Maintenance Mode</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto grid min-h-screen w-full max-w-4xl place-items-center px-6 py-10">
        <section class="w-full rounded-[1.25rem] border border-slate-200 bg-white p-8 shadow-sm shadow-slate-900/5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-amber-700">Maintenance Mode</span>
                    <h1 class="mt-5 text-3xl font-black leading-tight text-slate-900 lg:text-4xl">The system is temporarily locked.</h1>
                    <p class="mt-4 max-w-2xl text-base text-slate-500">A developer has enabled the custom maintenance lock. Core pages are paused until the lock is disabled again.</p>
                </div>

                <div class="grid size-16 place-items-center rounded-2xl bg-amber-50 text-amber-700">
                    <x-lucide name="shield-alert" class="size-8" />
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    @can('system_tools.view')
                        <a href="{{ route('system-tools.index') }}" class="btn-primary">Open System Tools</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-secondary">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-primary">Go to Login</a>
                @endauth
            </div>
        </section>
    </main>
</body>
</html>