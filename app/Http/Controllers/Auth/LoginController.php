<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            ActivityLog::record('login', 'auth', 'User logged in.');

            return redirect()->intended(route($request->user()->can('dashboard.view') ? 'dashboard' : ($request->user()->can('kitchen.view') ? 'kitchen.index' : 'pos.index')));
        }

        return back()
            ->withErrors(['username' => 'The provided username or password is invalid.'])
            ->onlyInput('username');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $hasOpenRegister = DB::table('registers')
                ->where('user_id', Auth::id())
                ->where('status', 'open')
                ->exists();

            if ($hasOpenRegister) {
                return redirect()->route('pos.index')
                    ->withErrors(['logout' => 'Please close your register before logging out.'])
                    ->with('show_close_register', true);
            }
        }

        ActivityLog::record('logout', 'auth', 'User logged out.');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
