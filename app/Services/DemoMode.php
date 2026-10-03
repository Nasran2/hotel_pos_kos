<?php

namespace App\Services;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DemoMode
{
    public function __construct(private readonly DatabaseSeeder $seeder) {}

    public function isProtectedResource(string $module, int $id): bool
    {
        if (! config('demo.enabled')) {
            return false;
        }

        return match ($module) {
            'users' => DB::table('users')->where('id', $id)->where('username', 'admin')->exists(),
            'roles' => DB::table('roles')->where('id', $id)->where('name', 'Super Admin')->exists(),
            default => false,
        };
    }

    public function assertResourceMutable(string $module, int $id): void
    {
        abort_if($this->isProtectedResource($module, $id), 403, 'The admin account and its access are protected in demo mode.');
    }

    public function validateSettings(Request $request): void
    {
        if (! config('demo.enabled')) {
            return;
        }
        $business = $request->input('settings.business', []);
        $current = DB::table('settings')->where('group', 'business')->whereIn('key', ['name', 'tagline'])->pluck('value', 'key');
        foreach (['name' => 'Hotel POS', 'tagline' => 'Restaurant operations'] as $key => $default) {
            if (is_array($business) && array_key_exists($key, $business) && $business[$key] !== ($current->get($key) ?? $default)) {
                throw ValidationException::withMessages(['settings.business.'.$key => 'Business names and headings cannot be changed in demo mode.']);
            }
            if ($request->hasFile('files.business.'.$key)) {
                throw ValidationException::withMessages(['files.business.'.$key => 'Business names and headings cannot be changed in demo mode.']);
            }
        }
        if ($request->has('settings.system_tools') || $request->hasFile('files.system_tools')) {
            throw ValidationException::withMessages(['settings.system_tools' => 'System controls are protected in demo mode.']);
        }
    }

    public function resetIfDue(): string
    {
        if (! config('demo.enabled')) {
            return 'disabled';
        }

        return Cache::lock('hotel-pos:demo-data-access', 300)->block(15, function (): string {
            if (! config('demo.enabled')) {
                return 'disabled';
            }

            return DB::transaction(function (): string {
                $initialized = DB::table('demo_reset_cycles')->insertOrIgnore([
                    'id' => 1, 'next_reset_at' => now()->addDays(config('demo.reset_days')),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $cycle = DB::table('demo_reset_cycles')->where('id', 1)->lockForUpdate()->first();
                if ($initialized) {
                    return 'initialized';
                }
                if (Carbon::parse($cycle->next_reset_at)->isFuture()) {
                    return 'waiting';
                }

                foreach ($this->resetTables() as $table) {
                    DB::table($table)->delete();
                }
                DB::table('user_roles')->delete();
                DB::table('role_permissions')->delete();
                DB::table('users')->where('username', '!=', 'admin')->delete();
                DB::table('roles')->whereNotIn('name', ['Super Admin', 'Kitchen Staff'])->delete();
                DB::table('users')->where('username', 'admin')->update(['remember_token' => null, 'deleted_at' => null]);
                DB::table('roles')->whereIn('name', ['Super Admin', 'Kitchen Staff'])->update(['deleted_at' => null]);
                $this->seeder->run();
                DB::table('demo_reset_cycles')->where('id', 1)->update([
                    'last_reset_at' => now(), 'next_reset_at' => now()->addDays(config('demo.reset_days')), 'updated_at' => now(),
                ]);

                return 'reset';
            });
        });
    }

    /** @return list<string> */
    private function resetTables(): array
    {
        return [
            'online_order_items', 'online_order_payments', 'sale_items', 'sale_payments', 'sale_deductions',
            'hold_order_items', 'purchase_items', 'purchase_payments', 'purchase_returns',
            'register_closings', 'cash_ins', 'cash_outs', 'bank_transactions', 'stock_movements',
            'damage_write_offs', 'waiter_incentives', 'activity_logs', 'kitchen_orders',
            'online_orders', 'hold_orders', 'sales', 'purchases', 'expenses', 'registers',
            'order_tokens', 'daily_token_counters', 'products', 'categories', 'customers', 'suppliers',
            'waiters', 'restaurant_tables', 'expense_categories', 'online_order_sources', 'bank_accounts',
            'settings', 'sessions', 'password_reset_tokens', 'jobs', 'job_batches', 'failed_jobs',
        ];
    }
}
