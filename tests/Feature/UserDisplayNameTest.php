<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->user);

    $this->hiddenUserId = DB::table('users')->insertGetId([
        'name' => 'DeV',
        'username' => 'hidden_dev_reports',
        'email' => 'hidden-dev-reports@example.test',
        'password' => 'password',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('hides the dev user name from reports', function (): void {
    $registerId = DB::table('registers')->insertGetId([
        'user_id' => $this->hiddenUserId,
        'opening_cash' => 275,
        'opened_at' => now()->subMinute(),
        'status' => 'closed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('register_closings')->insert([
        'register_id' => $registerId,
        'actual_cash' => 275,
        'expected_cash' => 275,
        'difference' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('cash_ins')->insert([
        'register_id' => $registerId,
        'user_id' => $this->hiddenUserId,
        'amount' => 90,
        'movement_date' => now(),
        'note' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cashFlow = $this->get(route('reports.show', 'cash-flow'))
        ->assertSuccessful();

    expect($cashFlow->getContent())
        ->not->toContain('>DeV<')
        ->toContain('Cashier');

    $registerClosing = $this->get(route('reports.show', 'register-closing'))
        ->assertSuccessful();

    expect($registerClosing->getContent())
        ->not->toContain('>DeV<')
        ->toContain('Cashier');
});

it('hides the dev user name from activity screens', function (): void {
    DB::table('activity_logs')->insert([
        'user_id' => $this->hiddenUserId,
        'action' => 'test',
        'module' => 'reports',
        'description' => 'Hidden dev activity.',
        'ip_address' => '127.0.0.1',
        'properties' => json_encode(['source' => 'test']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dashboard = $this->get(route('dashboard'))
        ->assertSuccessful();

    expect($dashboard->getContent())
        ->not->toContain('>DeV<')
        ->toContain('Hidden dev activity.');

    $activityLogs = $this->get(route('activity-logs.index'))
        ->assertSuccessful();

    expect($activityLogs->getContent())
        ->not->toContain('>DeV<')
        ->toContain('Hidden dev activity.')
        ->toContain('System');
});
