<?php

use App\Models\DailyTokenCounter;
use App\Models\OrderToken;
use App\Models\User;
use App\Services\DailyTokenService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', config('app.timezone')));

    $this->seed(DatabaseSeeder::class);
    $this->user = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->user);

    $this->registerId = DB::table('registers')->insertGetId([
        'user_id' => $this->user->id,
        'opening_cash' => 0,
        'opened_at' => now(),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('settings')->updateOrInsert(
        ['group' => 'pos', 'key' => 'enable_service_charge'],
        ['value' => '0', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
    );

    $this->customerId = (int) DB::table('customers')->where('is_walk_in', true)->value('id');
    $this->productId = (int) DB::table('products')->value('id');
    $this->tableIds = DB::table('restaurant_tables')->orderBy('id')->limit(2)->pluck('id')->map(fn ($id): int => (int) $id)->all();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('starts at 01 and shares one sequence between takeaway and table orders', function (): void {
    $takeaway = $this->postJson(route('pos.hold'), dailyTokenPayload($this->customerId, $this->productId))
        ->assertSuccessful()
        ->assertJsonPath('formatted_token', '01');

    $table = $this->postJson(route('pos.hold'), dailyTokenPayload($this->customerId, $this->productId, $this->tableIds[0]))
        ->assertSuccessful()
        ->assertJsonPath('formatted_token', '02');

    expect($takeaway->json('token_number'))->toBe(1)
        ->and($table->json('token_number'))->toBe(2)
        ->and(DB::table('order_tokens')->count())->toBe(2);
});

it('keeps one token through hold resume re-hold and payment', function (): void {
    $firstHold = $this->postJson(route('pos.hold'), dailyTokenPayload($this->customerId, $this->productId))
        ->assertSuccessful();

    $this->getJson(route('pos.resume-held-order', $firstHold->json('hold_id')))
        ->assertSuccessful()
        ->assertJsonPath('hold.token_number', 1)
        ->assertJsonPath('hold.formatted_token', '01');

    $secondHold = $this->postJson(
        route('pos.hold'),
        dailyTokenPayload($this->customerId, $this->productId, holdId: $firstHold->json('hold_id')),
    )->assertSuccessful()->assertJsonPath('formatted_token', '01');

    $payment = $this->postJson(
        route('pos.pay'),
        dailyTokenPayload($this->customerId, $this->productId, holdId: $secondHold->json('hold_id')) + [
            'payment_method' => 'cash',
            'received_amount' => 100,
        ],
    )->assertSuccessful()->assertJsonPath('formatted_token', '01');

    $saleTokenId = DB::table('sales')->where('id', $payment->json('sale_id'))->value('order_token_id');

    expect((int) $saleTokenId)->toBe((int) DB::table('hold_orders')->where('id', $secondHold->json('hold_id'))->value('order_token_id'))
        ->and(DB::table('order_tokens')->count())->toBe(1);
});

it('keeps the token when a table order is transferred', function (): void {
    $hold = $this->postJson(
        route('pos.hold'),
        dailyTokenPayload($this->customerId, $this->productId, $this->tableIds[0]),
    )->assertSuccessful();

    $tokenId = DB::table('hold_orders')->where('id', $hold->json('hold_id'))->value('order_token_id');

    $this->postJson(route('pos.transfer'), [
        'from_table_id' => $this->tableIds[0],
        'to_table_id' => $this->tableIds[1],
    ])->assertSuccessful();

    $transferred = DB::table('hold_orders')->where('id', $hold->json('hold_id'))->first();

    expect((int) $transferred->restaurant_table_id)->toBe($this->tableIds[1])
        ->and((int) $transferred->order_token_id)->toBe((int) $tokenId);
});

it('does not reuse a cancelled token', function (): void {
    $cancelled = $this->postJson(route('pos.hold'), dailyTokenPayload($this->customerId, $this->productId))
        ->assertSuccessful();

    $this->deleteJson(route('pos.hold.cancel', $cancelled->json('hold_id')))->assertSuccessful();

    $this->postJson(route('pos.hold'), dailyTokenPayload($this->customerId, $this->productId))
        ->assertSuccessful()
        ->assertJsonPath('formatted_token', '02');

    expect(DB::table('order_tokens')->pluck('token_number')->all())->toBe([1, 2]);
});

it('resets automatically for the next application-local day', function (): void {
    $this->postJson(route('pos.hold'), dailyTokenPayload($this->customerId, $this->productId))
        ->assertSuccessful()
        ->assertJsonPath('formatted_token', '01');

    Carbon::setTestNow(Carbon::parse('2026-09-03 00:00:00', config('app.timezone')));

    $this->postJson(route('pos.hold'), dailyTokenPayload($this->customerId, $this->productId))
        ->assertSuccessful()
        ->assertJsonPath('formatted_token', '01')
        ->assertJsonPath('token_date', '2026-09-03');
});

it('continues above 99 without truncating the number', function (): void {
    DB::table('daily_token_counters')->insert([
        'token_date' => '2026-09-02',
        'last_number' => 99,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson(route('pos.hold'), dailyTokenPayload($this->customerId, $this->productId))
        ->assertSuccessful()
        ->assertJsonPath('token_number', 100)
        ->assertJsonPath('formatted_token', '100');
});

it('shows the stored token on the POS print receipt and later sales reprints', function (): void {
    $payment = $this->postJson(route('pos.pay'), dailyTokenPayload($this->customerId, $this->productId) + [
        'payment_method' => 'cash',
        'received_amount' => 100,
    ])->assertSuccessful()->assertJsonPath('formatted_token', '01');

    $this->get(route('pos.index'))
        ->assertSuccessful()
        ->assertSee('Next Token')
        ->assertSee('data-print-token', false);

    Carbon::setTestNow(Carbon::parse('2026-09-03 09:00:00', config('app.timezone')));

    $this->get(route('backoffice.modules.print', ['sales', $payment->json('sale_id')]))
        ->assertSuccessful()
        ->assertSee('TOKEN NO: 01');

    expect(DB::table('order_tokens')->where('id', DB::table('sales')->where('id', $payment->json('sale_id'))->value('order_token_id'))->value('token_date'))
        ->toBe('2026-09-02');
});

it('enforces unique token numbers per day at the database level', function (): void {
    OrderToken::query()->create(['token_date' => '2026-09-02', 'token_number' => 1]);

    expect(fn (): OrderToken => OrderToken::query()->create([
        'token_date' => '2026-09-02',
        'token_number' => 1,
    ]))->toThrow(QueryException::class);
});

it('allocates distinct values through independent service instances', function (): void {
    $tokens = collect(range(1, 25))->map(
        fn (): int => app(DailyTokenService::class)->issue()->token_number,
    );

    expect($tokens->all())->toBe(range(1, 25))
        ->and($tokens->unique()->count())->toBe(25)
        ->and(DailyTokenCounter::query()->value('last_number'))->toBe(25);
});

/**
 * @return array<string, mixed>
 */
function dailyTokenPayload(int $customerId, int $productId, ?int $tableId = null, ?int $holdId = null): array
{
    return [
        'customer_id' => $customerId,
        'waiter_id' => null,
        'table_id' => $tableId,
        'hold_id' => $holdId,
        'note' => null,
        'discount_amount' => 0,
        'items' => [[
            'id' => $productId,
            'name' => 'Token Test Item',
            'quantity' => 1,
            'price' => 100,
            'discount_amount' => 0,
        ]],
    ];
}
