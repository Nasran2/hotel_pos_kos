<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Hotel POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white">
    <main class="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden bg-blue-700 p-12 text-white lg:block">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, white 0 2px, transparent 2px); background-size: 42px 42px"></div>
            <div class="relative flex h-full flex-col justify-between">
                <div>
                    <span class="inline-flex rounded-lg bg-white/15 px-4 py-2 text-sm font-bold">Restaurant daily command center</span>
                    <h1 class="mt-8 max-w-xl text-5xl font-black leading-tight">Fast tables, clear bills, controlled permissions.</h1>
                    <p class="mt-5 max-w-lg text-lg text-blue-50">Built for cashiers, waiters, stock rooms, purchases, accounts, and owner reports.</p>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    @foreach(['Open Register', 'Hold Table', 'Close Cash'] as $item)
                        <div class="animate-float rounded-lg bg-white/15 p-5 shadow-2xl backdrop-blur">
                            <p class="text-sm font-bold">{{ $item }}</p>
                            <div class="mt-4 h-2 rounded-full bg-white/40"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="grid place-items-center p-6">
            <form method="POST" action="{{ route('login.store') }}" class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
                @csrf
                <div class="mb-8">
                    <div class="grid size-14 place-items-center rounded-lg bg-blue-600 text-xl font-black text-white">HP</div>
                    <h2 class="mt-5 text-3xl font-black">Welcome back</h2>
                    <p class="mt-2 text-slate-500">Sign in with your username and password.</p>
                </div>
                @if($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
                @endif
                <div class="grid gap-4">
                    <label class="grid gap-2 text-sm font-bold">
                        Username
                        <input class="form-control" name="username" value="{{ old('username') }}" required autofocus>
                    </label>
                    <label class="grid gap-2 text-sm font-bold">
                        Password
                        <input class="form-control" type="password" name="password" required>
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300">
                        Remember me
                    </label>
                    <button class="btn-primary w-full justify-center py-3">Login</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
