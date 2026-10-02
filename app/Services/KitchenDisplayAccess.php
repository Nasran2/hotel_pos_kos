<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KitchenDisplayAccess
{
    private const SESSION_KEY = 'kitchen_display.access';

    public function unlock(Request $request, string $pin): bool
    {
        $hash = $this->storedHash();

        if (! ($hash === null ? hash_equals('0000', $pin) : Hash::check($pin, $hash))) {
            return false;
        }
        if (! $request->user()) {
            $request->session()->migrate(true);
        }
        $request->session()->put(self::SESSION_KEY, $this->fingerprint($hash));

        return true;
    }

    public function isUnlocked(Request $request): bool
    {
        $grant = $request->session()->get(self::SESSION_KEY);

        return is_string($grant) && hash_equals($this->fingerprint($this->storedHash()), $grant);
    }

    public function lock(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function updatePin(string $pin): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'kitchen', 'key' => 'pin_hash'],
            ['value' => Hash::make($pin), 'type' => 'secret', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function storedHash(): ?string
    {
        return DB::table('settings')->where('group', 'kitchen')->where('key', 'pin_hash')->value('value');
    }

    private function fingerprint(?string $hash): string
    {
        return hash('sha256', $hash ?? 'kitchen-display-default-pin');
    }
}
