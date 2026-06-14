<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['view', 'export', 'print'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "reports.t-accounts.{$action}"],
                [
                    'label' => 'T Accounts '.str($action)->headline(),
                    'category' => 'reports',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('name', [
            'reports.t-accounts.view',
            'reports.t-accounts.export',
            'reports.t-accounts.print',
        ])->delete();
    }
};
