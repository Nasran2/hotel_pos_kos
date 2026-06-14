<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ($this->permissions() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

    /**
     * @return array<int, string>
     */
    private function permissions(): array
    {
        $permissions = [
            'dashboard.view',
            'settings.view',
            'settings.update',
            'system_tools.view',
            'activity_logs.view',
            'accounts.view',
            'accounts.cash_in.create',
            'accounts.cash_out.create',
            'accounts.bank_transfer.create',
            'online_orders.view',
            'online_orders.create',
            'online_orders.edit',
            'online_orders.add_payment',
            'online_orders.print',
            'online_orders.view_commission_expense',
        ];

        foreach (array_keys(config('hotelpos.dashboard_cards')) as $card) {
            $permissions[] = "dashboard.card.{$card}";
        }

        foreach (array_keys(config('hotelpos.dashboard_charts')) as $chart) {
            $permissions[] = "dashboard.chart.{$chart}";
        }

        foreach (array_keys(config('hotelpos.modules')) as $module) {
            foreach (['view', 'create', 'edit', 'delete', 'print', 'export'] as $action) {
                $permissions[] = "{$module}.{$action}";
            }

            foreach (array_keys(config("hotelpos.modules.{$module}.fields", [])) as $field) {
                $permissions[] = "{$module}.field.{$field}";
            }
        }

        foreach (array_keys(config('hotelpos.pos_permissions')) as $permission) {
            $permissions[] = $permission;
        }

        foreach (array_keys(config('hotelpos.reports')) as $report) {
            $permissions[] = "reports.{$report}.view";
            $permissions[] = "reports.{$report}.export";
            $permissions[] = "reports.{$report}.print";
        }

        return array_values(array_unique($permissions));
    }
}
