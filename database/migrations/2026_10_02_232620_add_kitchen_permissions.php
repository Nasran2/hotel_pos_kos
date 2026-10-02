<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'kitchen.view' => ['Kitchen display', 'kitchen'],
            'kitchen.update' => ['Prepare and mark kitchen orders ready', 'kitchen'],
            'pos.send_kitchen' => ['Send orders to kitchen and mark served', 'pos'],
        ] as $name => [$label, $category]) {
            DB::table('permissions')->updateOrInsert(['name' => $name], [
                'label' => $label, 'category' => $category, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $kitchenRoleId = DB::table('roles')->where('name', 'Kitchen Staff')->value('id');
        if (! $kitchenRoleId) {
            $kitchenRoleId = DB::table('roles')->insertGetId([
                'name' => 'Kitchen Staff', 'description' => 'Kitchen display and order preparation',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (DB::table('permissions')->whereIn('name', ['kitchen.view', 'kitchen.update'])->pluck('id') as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $kitchenRoleId, 'permission_id' => $permissionId], ['created_at' => now(), 'updated_at' => now()]);
        }

        $holdPermissionId = DB::table('permissions')->where('name', 'pos.hold_order')->value('id');
        $sendPermissionId = DB::table('permissions')->where('name', 'pos.send_kitchen')->value('id');
        foreach (DB::table('role_permissions')->where('permission_id', $holdPermissionId)->pluck('role_id') as $roleId) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $sendPermissionId], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', ['kitchen.view', 'kitchen.update', 'pos.send_kitchen'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
