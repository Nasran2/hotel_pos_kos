@props(['posFullscreen' => false, 'heading' => null, 'title' => null, 'showDateFilter' => true])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Hotel POS' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell {{ request()->routeIs('pos.index') ? 'pos-screen' : '' }} min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        <x-sidebar :drawer-only="$posFullscreen" />

        <div class="min-w-0 flex-1">
            <header class="app-topbar sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
                <div class="flex h-18 items-center justify-between gap-3 px-4 sm:px-6">
                    <button class="topbar-menu-button {{ $posFullscreen ? '' : 'lg:hidden' }}" data-sidebar-open aria-label="Open sidebar">
                        <x-lucide name="menu" class="size-5" />
                    </button>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-500">Restaurant workspace</p>
                        <h1 class="truncate text-xl font-black text-slate-800">{{ $heading ?? 'Hotel POS' }}</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="hidden text-xs font-semibold text-slate-500 xl:block">{{ now()->format('D, d M Y') }}</span>
                        @can('pos.access')
                        @if(request()->routeIs('pos.index'))
                        <button type="button" data-new-order aria-label="New order" class="btn-primary !min-h-10 !px-4 !py-2"><x-lucide name="circle-plus" class="size-4" /><span class="hidden sm:inline">New order</span></button>
                        @else
                        <a href="{{ route('pos.index') }}" aria-label="New order" class="btn-primary !min-h-10 !px-4 !py-2"><x-lucide name="circle-plus" class="size-4" /><span class="hidden sm:inline">New order</span></a>
                        @endif
                        @endcan
                        <span class="hidden size-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800 sm:flex" title="{{ auth()->user()?->name }}">{{ mb_substr(auth()->user()?->name ?? 'U', 0, 1) }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn-secondary !min-h-10">Logout</button>
                        </form>
                    </div>
                </div>
            </header>

            @if($showDateFilter)
                <div class="app-filter sticky top-[4.5rem] z-30 border-b border-slate-200/80 bg-[#f6f8fc]/95 px-4 py-3 backdrop-blur sm:px-6">
                    <x-date-filter />
                </div>
            @endif

            <div class="pointer-events-none fixed right-4 top-20 z-[100] flex w-full max-w-sm flex-col gap-3 sm:right-6">
                @if(session('status'))
                    <div class="pointer-events-auto flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 shadow-2xl transition-all" role="alert">
                        <x-lucide name="check-circle-2" class="mt-0.5 size-5 shrink-0 text-green-600" />
                        <div>
                            <h4 class="text-sm font-bold text-green-800">Success</h4>
                            <p class="mt-0.5 text-xs font-semibold text-green-700">{{ session('status') }}</p>
                        </div>
                        <button type="button" class="-mx-1.5 -my-1.5 ml-auto rounded-lg p-1.5 text-green-500 hover:bg-green-100 hover:text-green-800 focus:outline-none" onclick="this.closest('[role=\'alert\']').remove()">
                            <x-lucide name="x" class="size-4" />
                        </button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="pointer-events-auto flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 shadow-2xl transition-all" role="alert">
                        <x-lucide name="alert-circle" class="mt-0.5 size-5 shrink-0 text-red-600" />
                        <div>
                            <h4 class="text-sm font-bold text-red-800">Error</h4>
                            <p class="mt-0.5 text-xs font-semibold text-red-700">{{ $errors->first() }}</p>
                        </div>
                        <button type="button" class="-mx-1.5 -my-1.5 ml-auto rounded-lg p-1.5 text-red-500 hover:bg-red-100 hover:text-red-800 focus:outline-none" onclick="this.closest('[role=\'alert\']').remove()">
                            <x-lucide name="x" class="size-4" />
                        </button>
                    </div>
                @endif
            </div>
            @if(session('status') || $errors->any())
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        setTimeout(() => {
                            document.querySelectorAll('[role="alert"]').forEach(alert => {
                                alert.style.opacity = '0';
                                setTimeout(() => alert.remove(), 300);
                            });
                        }, 5000);
                    });
                </script>
            @endif

            <main class="app-main {{ $posFullscreen ? 'p-3 sm:p-4' : 'p-4 sm:p-6' }}">
                {{ $slot }}
            </main>
        </div>
    </div>

    @can('pos.access')
        @unless($posFullscreen || request()->routeIs('pos.index'))
            <a href="{{ route('pos.index') }}"
                class="pos-launcher fixed bottom-5 right-5 z-50 grid size-14 place-items-center rounded-full bg-violet-600 text-white shadow-2xl shadow-violet-600/35 ring-4 ring-white transition duration-200 hover:-translate-y-0.5 hover:bg-violet-700 focus:outline-none focus:ring-4 focus:ring-violet-200 sm:bottom-6 sm:right-6"
                aria-label="Open POS screen"
                title="Open POS">
                <x-lucide name="wallet-cards" class="size-6" />
            </a>
        @endunless
    @endcan

    @stack('scripts')
</body>
</html>
