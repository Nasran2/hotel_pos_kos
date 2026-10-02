<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kitchen display · Hotel POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell kod-screen" data-kod data-kod-unlocked="{{ $unlocked ? '1' : '0' }}" data-kod-ready="0">
    <main class="kod-gate" data-kod-gate>
        <div class="kod-brand"><span>HP</span><div><strong>Hotel POS</strong><small>Made for hospitality</small></div></div>
        <section class="kod-access-card">
            <span class="kod-access-icon"><x-lucide name="cooking-pot" class="size-8" /></span>
            <p class="eyebrow">KITCHEN WORKSPACE</p>
            <h1 data-kod-title>Kitchen display</h1>
            <p class="kod-access-description" data-kod-description>Enter your four-digit PIN to open the live kitchen board in fullscreen.</p>
            <p class="kod-error" data-kod-error role="alert" hidden></p>
            <form method="POST" action="{{ route('kod.unlock') }}" data-kod-pin-form>
                @csrf
                <label class="sr-only" for="kitchen-access-pin">Four-digit PIN</label>
                <input id="kitchen-access-pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" placeholder="••••" class="kod-pin-input" required>
                <div class="kod-keypad" aria-label="PIN keypad">
                    @foreach(['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                        <button type="button" data-kod-digit="{{ $digit }}" aria-label="Digit {{ $digit }}">{{ $digit }}</button>
                    @endforeach
                    <button type="button" data-kod-clear class="kod-keypad-secondary">Clear</button>
                    <button type="button" data-kod-digit="0" aria-label="Digit 0">0</button>
                    <button type="button" data-kod-delete class="kod-keypad-secondary" aria-label="Delete last digit">⌫</button>
                </div>
                <button class="btn-primary kod-unlock" data-kod-unlock>Unlock & enter fullscreen</button>
            </form>
            <button type="button" class="btn-primary kod-unlock" data-kod-fullscreen hidden>Resume fullscreen</button>
            <form method="POST" action="{{ route('kod.lock') }}" data-kod-lock-form>
                @csrf
                <button type="submit" class="kod-lock-link" data-kod-lock hidden>Lock display</button>
            </form>
            <p class="kod-access-footnote">A dedicated screen for kitchen staff</p>
            <noscript><p class="kod-error">Enable JavaScript to use the fullscreen kitchen display.</p></noscript>
        </section>
        <p class="kod-gate-footer">HOTEL POS <span>·</span> KITCHEN ORDER DISPLAY</p>
    </main>

    <div class="kod-board-shell" data-kod-board hidden inert>
        <header class="kod-header">
            <div class="flex items-center gap-4"><span class="kod-header-icon"><x-lucide name="cooking-pot" class="size-6" /></span><div><p class="kod-header-eyebrow">HOTEL POS · KITCHEN</p><h1>Kitchen display</h1></div></div>
            <div class="flex items-center gap-4"><time class="kod-clock" data-kod-clock></time><button type="button" class="kod-header-lock" data-kod-lock>Lock display</button></div>
        </header>
        <main class="kod-board-main">
            <x-kitchen-queue :board="true" :kiosk="true" />
        </main>
    </div>
</body>
</html>
