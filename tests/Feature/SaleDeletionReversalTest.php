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
        'opening_cash' => 0,
        'opened_at' => now()->subMinute(),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->bankAccountId = DB::table('bank_accounts')->where('is_default', true)->value('id')
        ?: DB::table('bank_accounts')->insertGetId([
            'name' => 'Main Bank',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    DB::table('products')->where('id', 1)->update([
        'stock_quantity' => 8,
        'maintain_stock' => true,
        'updated_at' => now(),
    ]);
});

it('reverses stock payments bank movements and automatic card fee when deleting a sale', function (): void {
    $saleId = createSaleForDeletion($this->registerId, $this->user->id, $this->bankAccountId);

    $this->delete(route('backoffice.modules.destroy', ['sales', $saleId]))
        ->assertRedirect(route('backoffice.modules.index', 'sales'));

    expect((float) DB::table('products')->where('id', 1)->value('stock_quantity'))->toBe(10.0);
    expect(DB::table('sale_payments')->where('sale_id', $saleId)->count())->toBe(0);
    expect(DB::table('bank_transactions')->where('source_type', 'sale')->where('source_id', $saleId)->count())->toBe(0);
    expect(DB::table('waiter_incentives')->where('sale_id', $saleId)->count())->toBe(0);
    expect(DB::table('stock_movements')->where('source_type', 'sale')->where('source_id', $saleId)->where('type', 'sale_delete')->exists())->toBeTrue();

    $expense = DB::table('expenses')->where('source_type', 'sale')->where('source_id', $saleId)->first();

    expect($expense->deleted_at)->not->toBeNull();
    expect(DB::table('bank_transactions')->where('source_type', 'expense')->where('source_id', $expense->id)->count())->toBe(0);
});

it('does not show stale deleted sale payments or card fee expenses in register closing', function (): void {
    $saleId = createSaleForDeletion($this->registerId, $this->user->id, $this->bankAccountId);

    DB::table('sales')->where('id', $saleId)->update([
        'deleted_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson(route('pos.register.close-summary'))
        ->assertSuccessful()
        ->assertJsonPath('total_orders', 0)
        ->assertJsonPath('total_takeaway_orders', 0)
        ->assertJsonPath('summary.card_sales', 0)
        ->assertJsonPath('summary.bank_amount', 0)
        ->assertJsonCount(0, 'expenses');

    $this->get(route('backoffice.modules.index', 'expenses'))
        ->assertSuccessful()
        ->assertDontSee('Automatic card payment fee.');
});

it('downloads the register cash book from opening to close as pdf', function (): void {
    DB::table('registers')->where('id', $this->registerId)->update([
        'opening_cash' => 500,
        'opened_at' => now()->subHour(),
        'updated_at' => now(),
    ]);

    createCloseSummaryCashBookSale($this->registerId, $this->user->id, 'INV-CLOSE-CASH', 'cash', 250, now()->subMinute());
    createCloseSummaryCashBookSale($this->registerId, $this->user->id, 'INV-BEFORE-OPEN', 'cash', 999, now()->subDay());

    DB::table('cash_outs')->insert([
        'register_id' => $this->registerId,
        'user_id' => $this->user->id,
        'amount' => 50,
        'movement_date' => now()->subMinute(),
        'note' => 'Drawer payout',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bank_transactions')->insert([
        'bank_account_id' => $this->bankAccountId,
        'type' => 'deposit',
        'amount' => 200,
        'transaction_date' => now()->subMinute(),
        'source_type' => 'customer_due_payment',
        'source_id' => 1,
        'note' => 'Due bank payment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bank_transactions')->insert([
        'bank_account_id' => $this->bankAccountId,
        'type' => 'deposit',
        'amount' => 999,
        'transaction_date' => now()->subDay(),
        'source_type' => 'customer_due_payment',
        'source_id' => 1,
        'note' => 'Before opening bank payment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson(route('pos.register.close-summary'))
        ->assertSuccessful()
        ->assertJsonPath('summary.expected_cash', 700);

    $response = $this->get(route('pos.register.close-cash-book.pdf'))
        ->assertSuccessful();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('.pdf')
        ->and($response->getContent())->toStartWith('%PDF-1.4')
        ->and($response->getContent())->toContain('INV-CLOSE-CASH')
        ->toContain('Drawer payout')
        ->toContain('Due bank payment')
        ->not->toContain('INV-BEFORE-OPEN')
        ->not->toContain('Before opening bank payment');
});

function createSaleForDeletion(int $registerId, int $userId, int $bankAccountId): int
{
    $saleId = DB::table('sales')->insertGetId([
        'register_id' => $registerId,
        'user_id' => $userId,
        'invoice_no' => 'TEST-DELETE-'.uniqid(),
        'sale_date' => now(),
        'subtotal' => 1000,
        'total' => 1000,
        'paid_amount' => 1000,
        'profit' => 500,
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sale_items')->insert([
        'sale_id' => $saleId,
        'product_id' => 1,
        'product_name' => 'Kothu (Full)',
        'quantity' => 2,
        'unit_cost' => 250,
        'unit_price' => 500,
        'line_total' => 1000,
        'profit' => 500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sale_payments')->insert([
        'sale_id' => $saleId,
        'register_id' => $registerId,
        'payment_method' => 'card',
        'amount' => 1000,
        'received_amount' => 1000,
        'change_amount' => 0,
        'fee_amount' => 30,
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bank_transactions')->insert([
        'bank_account_id' => $bankAccountId,
        'type' => 'card_payment',
        'amount' => 1000,
        'transaction_date' => now(),
        'source_type' => 'sale',
        'source_id' => $saleId,
        'note' => 'TEST-DELETE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $expenseId = DB::table('expenses')->insertGetId([
        'expense_category_id' => DB::table('expense_categories')->where('name', 'Card Fee')->value('id'),
        'user_id' => $userId,
        'amount' => 30,
        'payment_method' => 'bank',
        'bank_account_id' => $bankAccountId,
        'expense_date' => now(),
        'note' => 'Automatic card payment fee.',
        'source_type' => 'sale',
        'source_id' => $saleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bank_transactions')->insert([
        'bank_account_id' => $bankAccountId,
        'type' => 'bank_expense',
        'amount' => 30,
        'transaction_date' => now(),
        'source_type' => 'expense',
        'source_id' => $expenseId,
        'note' => 'Automatic card payment fee.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('waiter_incentives')->insert([
        'sale_id' => $saleId,
        'sale_amount' => 1000,
        'percentage' => 2,
        'amount' => 20,
        'earned_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $saleId;
}

function createCloseSummaryCashBookSale(int $registerId, int $userId, string $invoiceNo, string $paymentMethod, float $amount, mixed $date): int
{
    $saleId = DB::table('sales')->insertGetId([
        'register_id' => $registerId,
        'user_id' => $userId,
        'invoice_no' => $invoiceNo,
        'sale_date' => $date,
        'subtotal' => $amount,
        'total' => $amount,
        'paid_amount' => $amount,
        'profit' => $amount,
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sale_payments')->insert([
        'sale_id' => $saleId,
        'register_id' => $registerId,
        'payment_method' => $paymentMethod,
        'amount' => $amount,
        'received_amount' => $amount,
        'change_amount' => 0,
        'fee_amount' => 0,
        'paid_at' => $date,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $saleId;
}
