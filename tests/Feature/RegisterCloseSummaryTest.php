<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->user);

    $this->registerId = DB::table('registers')->insertGetId([
        'user_id' => $this->user->id,
        'opening_cash' => 1000.0,
        'opened_at' => now(),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->customerId = DB::table('customers')->insertGetId([
        'name' => 'Register Test Customer',
        'phone' => '0771234567',
        'is_active' => true,
        'is_walk_in' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->product = DB::table('products')->first();
});

test('it returns register close summary with cash breakdown keys', function (): void {
    // Record a cash sale of 500
    $saleId = DB::table('sales')->insertGetId([
        'register_id' => $this->registerId,
        'user_id' => $this->user->id,
        'customer_id' => $this->customerId,
        'invoice_no' => 'INV-TEST-001',
        'sale_date' => now(),
        'subtotal' => 500,
        'discount_amount' => 0,
        'service_charge' => 0,
        'total' => 500,
        'paid_amount' => 500,
        'due_amount' => 0,
        'profit' => 100,
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sale_payments')->insert([
        'sale_id' => $saleId,
        'register_id' => $this->registerId,
        'payment_method' => 'cash',
        'amount' => 500,
        'received_amount' => 500,
        'change_amount' => 0,
        'fee_amount' => 0,
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Record an expense of 100 paid in cash
    $categoryId = DB::table('expense_categories')->insertGetId([
        'name' => 'Test Category',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('expenses')->insert([
        'expense_category_id' => $categoryId,
        'user_id' => $this->user->id,
        'amount' => 100,
        'payment_method' => 'cash',
        'expense_date' => now(),
        'note' => 'Test Expense',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson(route('pos.register.close-summary'))
        ->assertSuccessful()
        ->assertJsonStructure([
            'cash_in_cashier',
            'bank_amount',
            'bank_balance',
            'cash_balance_now',
            'cash_balance_before',
            'cash_drawer_open_balance',
            'total_sale_cash',
            'total_expense_amount',
            'overall_cash_breakdown' => [
                'before',
                'drawer_open_balance',
                'cash_in',
                'total_sale_cash',
                'cash_out',
                'total_expense_amount',
                'now',
            ],
        ]);

    $data = $response->json();
    expect((float) $data['cash_drawer_open_balance'])->toBe(1000.0)
        ->and((float) $data['total_sale_cash'])->toBe(500.0)
        ->and((float) $data['total_expense_amount'])->toBe(100.0)
        ->and((float) $data['cash_in_cashier'])->toBe(1400.0)
        ->and((float) $data['overall_cash_breakdown']['before'])->toBe(0.0)
        ->and((float) $data['overall_cash_breakdown']['drawer_open_balance'])->toBe(1000.0)
        ->and((float) $data['overall_cash_breakdown']['cash_in'])->toBe(0.0)
        ->and((float) $data['overall_cash_breakdown']['total_sale_cash'])->toBe(500.0)
        ->and((float) $data['overall_cash_breakdown']['cash_out'])->toBe(0.0)
        ->and((float) $data['overall_cash_breakdown']['total_expense_amount'])->toBe(100.0)
        ->and((float) $data['overall_cash_breakdown']['now'])->toBe(1400.0);
});

test('it returns the overall cash balance when another user has cash activity', function (): void {
    DB::table('registers')->where('id', $this->registerId)->update([
        'opening_cash' => 0,
        'updated_at' => now(),
    ]);

    $otherUserId = DB::table('users')->insertGetId([
        'name' => 'Second Cashier',
        'username' => 'second_cashier',
        'email' => 'second-cashier@example.test',
        'password' => 'password',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherRegisterId = DB::table('registers')->insertGetId([
        'user_id' => $otherUserId,
        'opening_cash' => 2000,
        'opened_at' => now()->subMinute(),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $saleId = DB::table('sales')->insertGetId([
        'register_id' => $otherRegisterId,
        'user_id' => $otherUserId,
        'customer_id' => $this->customerId,
        'invoice_no' => 'INV-SECOND-CASHIER',
        'sale_date' => now(),
        'subtotal' => 700,
        'discount_amount' => 0,
        'service_charge' => 0,
        'total' => 700,
        'paid_amount' => 700,
        'due_amount' => 0,
        'profit' => 100,
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sale_payments')->insert([
        'sale_id' => $saleId,
        'register_id' => $otherRegisterId,
        'payment_method' => 'cash',
        'amount' => 700,
        'received_amount' => 700,
        'change_amount' => 0,
        'fee_amount' => 0,
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson(route('pos.register.close-summary'))
        ->assertSuccessful()
        ->assertJsonPath('current_register_cash_balance', 0)
        ->assertJsonPath('cash_balance_now', 2700)
        ->assertJsonPath('overall_cash_balance', 2700)
        ->assertJsonPath('cash_in_cashier', 2700)
        ->assertJsonPath('overall_cash_breakdown.before', 0)
        ->assertJsonPath('overall_cash_breakdown.drawer_open_balance', 2000)
        ->assertJsonPath('overall_cash_breakdown.cash_in', 0)
        ->assertJsonPath('overall_cash_breakdown.total_sale_cash', 700)
        ->assertJsonPath('overall_cash_breakdown.cash_out', 0)
        ->assertJsonPath('overall_cash_breakdown.total_expense_amount', 0)
        ->assertJsonPath('overall_cash_breakdown.now', 2700);

    expect((float) $response->json('cash_balance_before'))->toBe(0.0);
});
