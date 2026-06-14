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
        'opening_cash' => 500,
        'opened_at' => now(),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->bankAccountId = DB::table('bank_accounts')->where('is_default', true)->value('id');
});

it('shows todays cash and bank transactions by default', function (): void {
    $cashSaleId = createCashBookSale($this->registerId, $this->user->id, 'INV-CASH', 'cash', 250);
    $bankSaleId = createCashBookSale($this->registerId, $this->user->id, 'INV-CARD', 'card', 400);

    DB::table('bank_transactions')->insert([
        'bank_account_id' => $this->bankAccountId,
        'type' => 'card_payment',
        'amount' => 400,
        'transaction_date' => now(),
        'source_type' => 'sale',
        'source_id' => $bankSaleId,
        'note' => 'INV-CARD',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('cash_ins')->insert([
        'register_id' => $this->registerId,
        'user_id' => $this->user->id,
        'amount' => 100,
        'movement_date' => now(),
        'note' => 'Owner cash in',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('cash_outs')->insert([
        'register_id' => $this->registerId,
        'user_id' => $this->user->id,
        'amount' => 50,
        'movement_date' => now(),
        'note' => 'Petty cash out',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    createCashBookSale($this->registerId, $this->user->id, 'INV-OLD', 'cash', 999, now()->subDay());

    $this->get(route('accounts.cash-book'))
        ->assertSuccessful()
        ->assertSee('Cashbook Summary Report')
        ->assertSee('Cash Drawer')
        ->assertSee('Main Bank')
        ->assertSee('INV-CASH')
        ->assertSee('INV-CASH CASH SALE')
        ->assertSee('INV-CARD')
        ->assertSee('Owner cash in')
        ->assertSee('Petty cash out')
        ->assertDontSee('INV-OLD');

    expect(DB::table('sales')->where('id', $cashSaleId)->exists())->toBeTrue();
});

it('supports custom date filtering', function (): void {
    createCashBookSale($this->registerId, $this->user->id, 'INV-YESTERDAY', 'cash', 320, now()->subDay());

    $this->get(route('accounts.cash-book', [
        'range' => 'custom',
        'from' => now()->subDay()->toDateString(),
        'to' => now()->subDay()->toDateString(),
    ]))
        ->assertSuccessful()
        ->assertSee('INV-YESTERDAY');
});

it('exports the cash book as excel', function (): void {
    createCashBookSale($this->registerId, $this->user->id, 'INV-EXCEL', 'cash', 275);

    $response = $this->get(route('accounts.cash-book.export', ['format' => 'excel']));

    $response->assertSuccessful();

    expect($response->headers->get('content-type'))->toContain('application/vnd.ms-excel')
        ->and($response->headers->get('content-disposition'))->toContain('.xls')
        ->and($response->streamedContent())->toContain('Cashbook Summary Report')
        ->and($response->streamedContent())->toContain('INV-EXCEL');
});

it('exports the cash book as pdf', function (): void {
    createCashBookSale($this->registerId, $this->user->id, 'INV-PDF', 'cash', 425);

    $response = $this->get(route('accounts.cash-book.export', ['format' => 'pdf']));

    $response->assertSuccessful();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('.pdf')
        ->and($response->getContent())->toStartWith('%PDF-1.4')
        ->and($response->getContent())->toContain('INV-PDF');
});

function createCashBookSale(int $registerId, int $userId, string $invoiceNo, string $paymentMethod, float $amount, mixed $date = null): int
{
    $date ??= now();
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
