<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Hotel POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="flex min-h-screen">
        <!-- Left Side: Hero / Brand -->
        <section class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-hotel-navy p-12 text-white lg:flex">
            <!-- Decorative background elements -->
            <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 32px 32px"></div>
            <div class="absolute -left-20 top-0 size-96 rounded-full bg-blue-500/20 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 size-96 rounded-full bg-hotel-gold/10 blur-3xl"></div>

            <div class="relative z-10">
                <span class="inline-flex items-center gap-2 rounded-full border border-blue-400/30 bg-blue-500/20 px-4 py-1.5 text-sm font-semibold tracking-wide backdrop-blur-md">
                    <span class="size-2 rounded-full bg-blue-400 animate-pulse"></span>
                    Restaurant Command Center
                </span>
                <h1 class="mt-8 max-w-xl text-5xl font-black leading-[1.1] tracking-tight">
                    Fast tables, <br>
                    <span class="text-hotel-gold">clear bills,</span> <br>
                    controlled permissions.
                </h1>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-blue-100/80">
                    Built for cashiers, waiters, stock rooms, purchases, accounts, and owner reports. Everything you need in one place.
                </p>
            </div>
            
            <div class="relative z-10 grid grid-cols-3 gap-5">
                @foreach(['Open Register', 'Hold Table', 'Close Cash'] as $item)
                    <div class="group cursor-pointer rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur-md transition-all hover:scale-[1.02] hover:bg-white/20">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold text-white/90">{{ $item }}</p>
                            <svg class="size-4 text-blue-300 opacity-0 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </div>
                        <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-white/20">
                            <div class="h-full w-1/3 rounded-full bg-blue-400 transition-all group-hover:w-2/3"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Right Side: Login Form -->
        <section class="flex w-full flex-col justify-center px-6 py-12 lg:w-1/2 lg:px-20 xl:px-32">
            <div class="mx-auto w-full max-w-sm">
                <!-- Mobile Logo -->
                <div class="mb-8 flex size-14 items-center justify-center rounded-2xl bg-hotel-navy text-xl font-black text-white shadow-lg lg:hidden">
                    HP
                </div>

                <div class="mb-10">
                    <h2 class="text-3xl font-black tracking-tight text-slate-900">Welcome back</h2>
                    <p class="mt-2 text-slate-500">Please enter your details to sign in.</p>
                </div>

                @if($errors->any())
                    <div class="mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
                        <svg class="size-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="grid gap-6">
                    @csrf
                    
                    <div class="space-y-2">
                        <label for="username" class="text-sm font-bold text-slate-700">Username</label>
                        <input id="username" name="username" value="{{ old('username') }}" required autofocus
                               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10 placeholder:text-slate-400"
                               placeholder="Enter your username">
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="text-sm font-bold text-slate-700">Password</label>
                        <div class="relative" x-data="{ show: false }">
                            <input id="password" type="password" name="password" required
                                   class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10 placeholder:text-slate-400"
                                   placeholder="••••••••"
                                   x-bind:type="show ? 'text' : 'password'">
                            <button type="button" @click="show = !show" 
                                    class="absolute inset-y-0 right-0 flex items-center justify-center px-4 text-slate-400 hover:text-slate-600 focus:outline-none">
                                <svg x-show="!show" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="show" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.978 9.978 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" name="remember" value="1" 
                                   class="size-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-slate-600">Remember me</span>
                        </label>
                    </div>

                    <button class="mt-2 w-full rounded-xl bg-blue-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-500/30 transition-all hover:bg-blue-700 hover:shadow-blue-500/40 active:scale-[0.98]">
                        Sign in
                    </button>
                </form>
            </div>
        </section>
    </main>
    <script>
        // Fallback for password toggle if Alpine.js is not loaded
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Alpine === 'undefined') {
                const wrappers = document.querySelectorAll('[x-data="{ show: false }"]');
                wrappers.forEach(wrapper => {
                    const input = wrapper.querySelector('input');
                    const btn = wrapper.querySelector('button');
                    const svgs = btn.querySelectorAll('svg');
                    
                    btn.removeAttribute('@click');
                    input.removeAttribute('x-bind:type');
                    svgs[0].removeAttribute('x-show');
                    svgs[1].removeAttribute('x-show');
                    
                    btn.addEventListener('click', () => {
                        const isPassword = input.type === 'password';
                        input.type = isPassword ? 'text' : 'password';
                        svgs[0].style.display = isPassword ? 'none' : 'block';
                        svgs[1].style.display = isPassword ? 'block' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>
