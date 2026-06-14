<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->user);

    DB::table('registers')->insert([
        'user_id' => $this->user->id,
        'opening_cash' => 0,
        'opened_at' => now(),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('creates an expense category from the pos popup endpoint', function (): void {
    $this->postJson(route('pos.expense-categories.store'), [
        'name' => 'Staff Meals',
    ])
        ->assertSuccessful()
        ->assertJsonPath('category.name', 'Staff Meals');

    expect(DB::table('expense_categories')->where('name', 'Staff Meals')->where('is_active', true)->exists())->toBeTrue();
});
