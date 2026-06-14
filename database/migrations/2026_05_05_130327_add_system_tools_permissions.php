<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => 'system_tools.view'],
            [
                'label' => 'System tools view',
                'category' => 'system_tools',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('name', 'system_tools.view')->value('id');
        $developerRoleId = DB::table('roles')->where('name', 'Developer')->value('id');

        if ($permissionId !== null && $developerRoleId !== null) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $developerRoleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
            $permissionId = DB::table('permissions')->where('name', 'system_tools.view')->value('id');
            $developerRoleId = DB::table('roles')->where('name', 'Developer')->value('id');

            if ($permissionId !== null && $developerRoleId !== null) {
                DB::table('role_permissions')
                    ->where('role_id', $developerRoleId)
                    ->where('permission_id', $permissionId)
                    ->delete();
            }
        }

        DB::table('permissions')->where('name', 'system_tools.view')->delete();
    }
};
