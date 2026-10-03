<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = $this->permissions();
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                ['label' => $permission['label'], 'category' => $permission['category'], 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $roleId = DB::table('roles')->updateOrInsert(
            ['name' => 'Super Admin'],
            ['description' => 'Full system access', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        $roleId = DB::table('roles')->where('name', 'Super Admin')->value('id');
        DB::table('role_permissions')->where('role_id', $roleId)->delete();
        foreach (DB::table('permissions')->pluck('id') as $permissionId) {
            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::table('users')->updateOrInsert(
            ['username' => 'admin'],
            [
                'name' => 'Default Admin',
                'email' => 'admin@example.com',
                'phone' => '0770000000',
                'password' => Hash::make('password'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $userId = DB::table('users')->where('username', 'admin')->value('id');
        DB::table('user_roles')->updateOrInsert(['user_id' => $userId, 'role_id' => $roleId], ['created_at' => now(), 'updated_at' => now()]);

        DB::table('roles')->updateOrInsert(['name' => 'Kitchen Staff'], [
            'description' => 'Kitchen display and order preparation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $kitchenRoleId = DB::table('roles')->where('name', 'Kitchen Staff')->value('id');
        foreach (DB::table('permissions')->whereIn('name', ['kitchen.view', 'kitchen.update'])->pluck('id') as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $kitchenRoleId, 'permission_id' => $permissionId], ['created_at' => now(), 'updated_at' => now()]);
        }

        foreach (['Food', 'Beverage', 'Dessert', 'Service'] as $category) {
            DB::table('categories')->updateOrInsert(['name' => $category], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        $foodId = DB::table('categories')->where('name', 'Food')->value('id');
        $serviceId = DB::table('categories')->where('name', 'Service')->value('id');
        foreach ([
            ['name' => 'Kothu (Full)', 'cost' => 350, 'price' => 550, 'stock' => 25, 'maintain_stock' => true, 'category_id' => $foodId],
            ['name' => 'Kothu (Half)', 'cost' => 200, 'price' => 300, 'stock' => 30, 'maintain_stock' => true, 'category_id' => $foodId],
            ['name' => 'Rice & Curry', 'cost' => 140, 'price' => 250, 'stock' => 40, 'maintain_stock' => true, 'category_id' => $foodId],
            ['name' => 'Fried Rice', 'cost' => 250, 'price' => 450, 'stock' => 20, 'maintain_stock' => true, 'category_id' => $foodId],
            ['name' => 'Egg Noodles', 'cost' => 220, 'price' => 400, 'stock' => 18, 'maintain_stock' => true, 'category_id' => $foodId],
            ['name' => 'Delivery Charge', 'cost' => 0, 'price' => 150, 'stock' => 0, 'maintain_stock' => false, 'category_id' => $serviceId],
            ['name' => 'Service Charge', 'cost' => 0, 'price' => 100, 'stock' => 0, 'maintain_stock' => false, 'category_id' => $serviceId],
            ['name' => 'Packing Fee', 'cost' => 0, 'price' => 50, 'stock' => 0, 'maintain_stock' => false, 'category_id' => $serviceId],
        ] as $product) {
            DB::table('products')->updateOrInsert(
                ['name' => $product['name']],
                [
                    'category_id' => $product['category_id'],
                    'sku' => Str::slug($product['name']).'-sku',
                    'barcode' => DB::table('products')->where('name', $product['name'])->value('barcode') ?? 'POS'.Str::ulid(),
                    'cost_price' => $product['cost'],
                    'selling_price' => $product['price'],
                    'maintain_stock' => $product['maintain_stock'],
                    'stock_quantity' => $product['stock'],
                    'alert_quantity' => $product['maintain_stock'] ? 5 : 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        foreach (range(1, 8) as $number) {
            DB::table('restaurant_tables')->updateOrInsert(['number' => sprintf('%02d', $number)], ['status' => 'available', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (['Waiter Kumar', 'Waiter Siva', 'Waiter Ravi', 'Waiter Nimal'] as $waiter) {
            DB::table('waiters')->updateOrInsert(['name' => $waiter], ['is_available' => true, 'is_active' => true, 'incentive_percentage' => 2, 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::table('customers')->updateOrInsert(['name' => 'Walk-in Customer'], ['is_walk_in' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('expense_categories')->updateOrInsert(['name' => 'Card Fee'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('expense_categories')->updateOrInsert(['name' => 'Damage Write-Off'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('expense_categories')->updateOrInsert(['name' => 'Online Platform Commission'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bank_accounts')->updateOrInsert(['name' => 'Main Bank'], ['is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        foreach ([
            ['name' => 'Uber Eats', 'commission_type' => 'percentage', 'commission_value' => 25],
            ['name' => 'PickMe Food', 'commission_type' => 'percentage', 'commission_value' => 18],
            ['name' => 'Website', 'commission_type' => 'percentage', 'commission_value' => 0],
            ['name' => 'Facebook', 'commission_type' => 'percentage', 'commission_value' => 0],
            ['name' => 'WhatsApp', 'commission_type' => 'percentage', 'commission_value' => 0],
        ] as $source) {
            DB::table('online_order_sources')->updateOrInsert(
                ['name' => $source['name']],
                [
                    'commission_type' => $source['commission_type'],
                    'commission_value' => $source['commission_value'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        foreach ($this->settings() as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => $setting[0], 'key' => $setting[1]],
                ['value' => $setting[2], 'type' => $setting[3] ?? 'string', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * @return array<int, array{name: string, label: string, category: string}>
     */
    private function permissions(): array
    {
        $permissions = [
            ['name' => 'kitchen.view', 'label' => 'Kitchen display', 'category' => 'kitchen'],
            ['name' => 'kitchen.update', 'label' => 'Prepare and mark kitchen orders ready', 'category' => 'kitchen'],
            ['name' => 'pos.send_kitchen', 'label' => 'Send orders to kitchen and mark served', 'category' => 'pos'],
            ['name' => 'dashboard.view', 'label' => 'Dashboard view', 'category' => 'dashboard'],
            ['name' => 'settings.view', 'label' => 'Settings view', 'category' => 'settings'],
            ['name' => 'settings.update', 'label' => 'Settings update', 'category' => 'settings'],
            ['name' => 'activity_logs.view', 'label' => 'Activity log view', 'category' => 'activity_logs'],
            ['name' => 'accounts.view', 'label' => 'Accounts view', 'category' => 'accounts'],
            ['name' => 'accounts.cash_in.create', 'label' => 'Add cash in', 'category' => 'accounts'],
            ['name' => 'accounts.cash_out.create', 'label' => 'Add cash out', 'category' => 'accounts'],
            ['name' => 'accounts.bank_transfer.create', 'label' => 'Bank transfer create', 'category' => 'accounts'],
            ['name' => 'online_orders.view', 'label' => 'Online orders view', 'category' => 'online_orders'],
            ['name' => 'online_orders.create', 'label' => 'Online orders create', 'category' => 'online_orders'],
            ['name' => 'online_orders.edit', 'label' => 'Online orders edit', 'category' => 'online_orders'],
            ['name' => 'online_orders.add_payment', 'label' => 'Online orders add payment', 'category' => 'online_orders'],
            ['name' => 'online_orders.print', 'label' => 'Online orders print', 'category' => 'online_orders'],
            ['name' => 'online_orders.view_commission_expense', 'label' => 'Online orders view commission expense', 'category' => 'online_orders'],
        ];

        foreach (config('hotelpos.dashboard_cards') as $card) {
            $permissions[] = ['name' => $card['permission'], 'label' => $card['label'].' view', 'category' => 'dashboard'];
        }

        foreach (config('hotelpos.dashboard_charts') as $chart) {
            $permissions[] = ['name' => $chart['permission'], 'label' => $chart['label'].' view', 'category' => 'dashboard'];
        }

        foreach (config('hotelpos.modules') as $module => $config) {
            foreach (['view', 'create', 'edit', 'delete', 'print', 'export'] as $action) {
                $permissions[] = ['name' => "{$module}.{$action}", 'label' => "{$config['label']} ".str($action)->headline(), 'category' => $module];
            }

            foreach ($config['fields'] as $field => $definition) {
                $permissions[] = ['name' => "{$module}.field.{$field}", 'label' => $definition['label'].' field', 'category' => $module];
            }
        }

        foreach (config('hotelpos.pos_permissions') as $name => $label) {
            $permissions[] = ['name' => $name, 'label' => $label, 'category' => 'pos'];
        }

        foreach (config('hotelpos.reports') as $report => $label) {
            foreach (['view', 'export', 'print'] as $action) {
                $permissions[] = ['name' => "reports.{$report}.{$action}", 'label' => "{$label} ".str($action)->headline(), 'category' => 'reports'];
            }
        }

        return $permissions;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3?: string}>
     */
    private function settings(): array
    {
        return [
            ['business', 'name', 'Hotel POS'],
            ['currency', 'symbol', 'Rs.'],
            ['currency', 'position', 'before'],
            ['currency', 'decimal_places', '2', 'integer'],
            ['display', 'items_per_page', '10', 'integer'],
            ['stock', 'default_maintain_stock', '1', 'boolean'],
            ['stock', 'low_stock_alert', '5', 'integer'],
            ['stock', 'allow_negative_stock', '0', 'boolean'],
            ['invoice', 'prefix', 'INV'],
            ['invoice', 'paper_size', '80mm'],
            ['pos', 'enable_card_fee', '1', 'boolean'],
            ['pos', 'card_fee_rate', '3', 'decimal'],
            ['pos', 'default_customer', 'Walk-in Customer'],
            ['pos', 'enable_waiter_incentive', '1', 'boolean'],
            ['pos', 'default_waiter_incentive', '2', 'decimal'],
            ['pos', 'allow_due_sale', '1', 'boolean'],
            ['pos', 'allow_due_walk_in', '0', 'boolean'],
            ['pos', 'enable_print_before_payment', '1', 'boolean'],
            ['pos', 'enable_service_charge', '1', 'boolean'],
            ['pos', 'service_charge_percentage', '10', 'decimal'],
            ['barcode', 'auto_generate', '1', 'boolean'],
            ['barcode', 'prefix', 'POS'],
            ['barcode', 'length', '6', 'integer'],
            ['barcode', 'next_number', '1001', 'integer'],
            ['system_tools', 'maintenance_enabled', '0', 'boolean'],
        ];
    }
}
