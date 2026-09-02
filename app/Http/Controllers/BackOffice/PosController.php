<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OrderToken;
use App\Models\User;
use App\Services\DailyTokenService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(private readonly DailyTokenService $dailyTokenService) {}

    public function index(): View
    {
        $this->authorize('pos.access');

        return view('pos.index', [
            'register' => $this->currentRegister(),
            'tables' => DB::table('restaurant_tables')->where('is_active', true)->whereNull('deleted_at')->orderBy('number')->get(),
            'waiters' => DB::table('waiters')->where('is_active', true)->where('is_available', true)->whereNull('deleted_at')->orderBy('name')->get(),
            'customers' => DB::table('customers')->where('is_active', true)->whereNull('deleted_at')->orderByDesc('is_walk_in')->orderBy('name')->get(),
            'categories' => DB::table('categories')->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get(),
            'products' => DB::table('products')->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get(),
            'holds' => DB::table('hold_orders')
                ->leftJoin('order_tokens', 'order_tokens.id', '=', 'hold_orders.order_token_id')
                ->select('hold_orders.*', 'order_tokens.token_number', 'order_tokens.token_date')
                ->whereNotNull('restaurant_table_id')
                ->whereIn('status', ['hold', 'payment_pending'])
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('restaurant_table_id'),
            'takeawayHolds' => DB::table('hold_orders')
                ->leftJoin('customers', 'customers.id', '=', 'hold_orders.customer_id')
                ->leftJoin('order_tokens', 'order_tokens.id', '=', 'hold_orders.order_token_id')
                ->select('hold_orders.*', 'customers.name as customer_name', 'order_tokens.token_number', 'order_tokens.token_date')
                ->whereNull('hold_orders.restaurant_table_id')
                ->whereIn('hold_orders.status', ['hold', 'payment_pending'])
                ->whereNull('hold_orders.deleted_at')
                ->latest('hold_orders.updated_at')
                ->get(),
            'expenseCategories' => DB::table('expense_categories')->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get(),
            'bankAccounts' => DB::table('bank_accounts')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'dueCustomers' => $this->customerDueOptions(),
            'dueSuppliers' => $this->supplierDueOptions(),
            'settings' => $this->settings(),
            'nextToken' => $this->dailyTokenService->nextAvailable(),
        ]);
    }

    public function nextToken(): JsonResponse
    {
        $this->authorize('pos.access');

        return response()->json($this->dailyTokenService->nextAvailable());
    }

    public function openRegister(Request $request): RedirectResponse
    {
        $this->authorize('pos.open_register');

        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'opening_note' => ['nullable', 'string'],
        ]);

        DB::table('registers')->insert([
            'user_id' => $request->user()->id,
            'opening_cash' => $validated['opening_cash'],
            'opened_at' => now(),
            'status' => 'open',
            'opening_note' => $validated['opening_note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLog::record('open', 'register', 'Register opened.', ['opening_cash' => $validated['opening_cash']]);

        return back()->with('status', 'Register opened.');
    }

    public function hold(Request $request): JsonResponse
    {
        $this->authorize('pos.hold_order');
        $payload = $this->cartPayload($request);

        return DB::transaction(function () use ($payload): JsonResponse {
            $orderToken = $this->resolveOrderToken($payload);
            $holdId = $this->storePendingOrder($payload, $orderToken, 'hold', 'hold');
            ActivityLog::record('hold', 'pos', 'Order held.', ['hold_id' => $holdId, 'token' => $orderToken->token_number]);

            return response()->json([
                'message' => 'Order held.',
                'hold_id' => $holdId,
                ...$this->tokenResponse($orderToken),
            ]);
        });
    }

    public function resume(int $table): JsonResponse
    {
        $this->authorize('pos.resume_hold_order');

        return DB::transaction(function () use ($table): JsonResponse {
            $hold = DB::table('hold_orders')
                ->where('restaurant_table_id', $table)
                ->whereIn('status', ['hold', 'payment_pending'])
                ->whereNull('deleted_at')
                ->latest()
                ->lockForUpdate()
                ->first();
            abort_if(! $hold, 404);

            $hold = $this->ensureHoldToken($hold);
            $items = DB::table('hold_order_items')->where('hold_order_id', $hold->id)->get();
            ActivityLog::record('resume', 'pos', 'Held order resumed.', ['hold_id' => $hold->id, 'token' => $hold->token_number]);

            return response()->json(['hold' => $hold, 'items' => $items]);
        });
    }

    public function resumeHeldOrder(int $hold): JsonResponse
    {
        $this->authorize('pos.resume_hold_order');

        return DB::transaction(function () use ($hold): JsonResponse {
            $holdOrder = DB::table('hold_orders')->where('id', $hold)->whereIn('status', ['hold', 'payment_pending'])->whereNull('deleted_at')->lockForUpdate()->first();
            abort_if(! $holdOrder, 404);

            $holdOrder = $this->ensureHoldToken($holdOrder);
            $items = DB::table('hold_order_items')->where('hold_order_id', $holdOrder->id)->get();
            ActivityLog::record('resume', 'pos', 'Held order resumed.', ['hold_id' => $holdOrder->id, 'token' => $holdOrder->token_number]);

            return response()->json(['hold' => $holdOrder, 'items' => $items]);
        });
    }

    public function cancelHold(int $hold): JsonResponse
    {
        $this->authorize('pos.hold_order');

        $holdOrder = DB::table('hold_orders')
            ->where('id', $hold)
            ->whereIn('status', ['hold', 'payment_pending'])
            ->whereNull('deleted_at')
            ->firstOrFail();

        DB::transaction(function () use ($holdOrder): void {
            DB::table('hold_orders')
                ->where('id', $holdOrder->id)
                ->update([
                    'status' => 'cancelled',
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($holdOrder->restaurant_table_id) {
                DB::table('restaurant_tables')
                    ->where('id', $holdOrder->restaurant_table_id)
                    ->update([
                        'status' => 'available',
                        'updated_at' => now(),
                    ]);
            }

            ActivityLog::record('cancel', 'pos', 'Held order cancelled.', [
                'hold_id' => $holdOrder->id,
                'table_id' => $holdOrder->restaurant_table_id,
            ]);
        });

        return response()->json(['message' => 'Hold order cancelled.']);
    }

    public function storeExpenseCategory(Request $request): JsonResponse
    {
        $this->authorize('pos.close_register');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($validated['name']);
        $category = ExpenseCategory::withTrashed()->firstWhere('name', $name);

        if ($category) {
            if ($category->trashed()) {
                $category->restore();
            }

            $category->update([
                'is_active' => true,
            ]);
        } else {
            $category = ExpenseCategory::create([
                'name' => $name,
                'is_active' => true,
            ]);
        }

        ActivityLog::record('create', 'expense_category', 'Expense category added from POS.', [
            'id' => $category->id,
            'name' => $category->name,
        ]);

        return response()->json([
            'message' => 'Expense category saved successfully.',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $this->authorize('pos.transfer_table');
        $validated = $request->validate([
            'from_table_id' => ['required', 'exists:restaurant_tables,id'],
            'to_table_id' => ['required', 'different:from_table_id', 'exists:restaurant_tables,id'],
        ]);

        DB::transaction(function () use ($validated): void {
            $order = DB::table('hold_orders')->where('restaurant_table_id', $validated['from_table_id'])->whereIn('status', ['hold', 'payment_pending'])->whereNull('deleted_at')->latest()->first();
            abort_if(! $order, 422, 'No active order found for this table.');

            DB::table('hold_orders')->where('id', $order->id)->update([
                'restaurant_table_id' => $validated['to_table_id'],
                'updated_at' => now(),
            ]);
            DB::table('restaurant_tables')->where('id', $validated['from_table_id'])->update(['status' => 'available', 'updated_at' => now()]);
            DB::table('restaurant_tables')->where('id', $validated['to_table_id'])->update(['status' => $order->status === 'payment_pending' ? 'payment_pending' : 'hold', 'updated_at' => now()]);
            ActivityLog::record('transfer', 'pos', 'Table order transferred.', $validated);
        });

        return response()->json(['message' => 'Table transferred.']);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $this->authorize('customers.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'is_walk_in' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLog::record('create', 'customers', 'Customer created from POS.', ['id' => $customerId]);

        return response()->json([
            'message' => 'Customer added.',
            'customer' => [
                'id' => $customerId,
                'name' => $validated['name'],
                'is_walk_in' => false,
            ],
        ]);
    }

    public function storeWaiter(Request $request): JsonResponse
    {
        $this->authorize('waiters.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $waiterId = DB::table('waiters')->insertGetId([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'is_available' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLog::record('create', 'waiters', 'Waiter created from POS.', ['id' => $waiterId]);

        return response()->json([
            'message' => 'Waiter added.',
            'waiter' => [
                'id' => $waiterId,
                'name' => $validated['name'],
            ],
        ]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $this->authorize('pos.close_register');
        $register = $this->currentRegister();
        abort_unless($register, 422, 'No open register.');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string'],
            'source' => ['required', 'in:drawer,bank'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'description' => ['nullable', 'string'],
        ]);

        // Get or create expense category
        $category = ExpenseCategory::where('name', $validated['category'])->first();
        if (! $category) {
            $category = ExpenseCategory::create([
                'name' => $validated['category'],
                'is_active' => true,
            ]);
        }

        // Determine payment method based on source
        $paymentMethod = $validated['source'] === 'drawer' ? 'cash' : 'bank';
        $bankAccountId = $paymentMethod === 'bank'
            ? ($validated['bank_account_id'] ?? DB::table('bank_accounts')->where('is_default', true)->value('id'))
            : null;

        // Create expense
        $expense = Expense::create([
            'expense_category_id' => $category->id,
            'user_id' => auth()->id(),
            'amount' => $validated['amount'],
            'payment_method' => $paymentMethod,
            'bank_account_id' => $bankAccountId,
            'expense_date' => now(),
            'note' => $validated['description'] ?? null,
            'source_type' => 'register',
            'source_id' => $register->id,
        ]);

        if ($paymentMethod === 'bank' && $bankAccountId) {
            DB::table('bank_transactions')->insert([
                'bank_account_id' => $bankAccountId,
                'type' => 'bank_expense',
                'amount' => $validated['amount'],
                'transaction_date' => now(),
                'source_type' => 'expense',
                'source_id' => $expense->id,
                'note' => $expense->note ?: 'Expense paid from bank',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        ActivityLog::record('create', 'expense', 'Expense added during register session.', [
            'register_id' => $register->id,
            'expense_id' => $expense->id,
            'amount' => $validated['amount'],
        ]);

        return response()->json([
            'message' => 'Expense added successfully.',
            'expense' => [
                'id' => $expense->id,
                'amount' => (float) $expense->amount,
                'category' => $category->name,
                'source' => $validated['source'],
                'bank_account_id' => $bankAccountId,
                'description' => $expense->note,
                'can_remove' => true,
            ],
        ]);
    }

    public function destroyExpense(int $expense): JsonResponse
    {
        $this->authorize('pos.close_register');
        $register = $this->currentRegister();
        abort_unless($register, 422, 'No open register.');

        $updated = DB::table('expenses')
            ->where('id', $expense)
            ->where('source_type', 'register')
            ->where('source_id', $register->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        abort_if($updated === 0, 404, 'Expense not found.');

        DB::table('bank_transactions')
            ->where('source_type', 'expense')
            ->where('source_id', $expense)
            ->delete();

        ActivityLog::record('delete', 'expense', 'Expense removed during register session.', [
            'register_id' => $register->id,
            'expense_id' => $expense,
        ]);

        return response()->json([
            'message' => 'Expense removed successfully.',
        ]);
    }

    public function printBill(Request $request): JsonResponse
    {
        $this->authorize('pos.print_bill');
        $payload = $this->cartPayload($request);

        return DB::transaction(function () use ($payload): JsonResponse {
            $orderToken = $this->resolveOrderToken($payload);
            $invoice = $this->pendingInvoice($payload) ?? $this->nextInvoice();
            $holdId = $this->storePendingOrder($payload, $orderToken, 'payment_pending', 'payment_pending', $invoice);
            ActivityLog::record('print', 'pos', 'Pre-payment bill printed.', ['hold_id' => $holdId, 'invoice' => $invoice, 'table_id' => $payload['table_id'] ?? null, 'token' => $orderToken->token_number]);

            return response()->json([
                'message' => 'Bill marked as printed.',
                'hold_id' => $holdId,
                'invoice' => $invoice,
                'order_date' => now()->format('d/m/Y, H:i:s'),
                ...$this->tokenResponse($orderToken),
            ]);
        });
    }

    public function pay(Request $request): JsonResponse
    {
        $this->authorize('pos.payment');
        $payload = $this->cartPayload($request);
        $paymentMethod = $request->validate([
            'payment_method' => ['required', 'in:cash,card,qr,due,split'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
        ])['payment_method'];

        abort_if($paymentMethod === 'cash' && ! auth()->user()->can('pos.cash_payment'), 403);
        abort_if($paymentMethod === 'card' && ! auth()->user()->can('pos.card_payment'), 403);
        abort_if($paymentMethod === 'qr' && ! auth()->user()->can('pos.qr_payment'), 403);

        $customer = DB::table('customers')->where('id', $payload['customer_id'])->first();
        $settings = $this->settings();
        abort_if($paymentMethod === 'due' && ($customer?->is_walk_in || ! $settings['pos_allow_due_sale']), 422, 'Due payment is only allowed for registered customers.');

        return DB::transaction(function () use ($payload, $paymentMethod, $request, $customer, $settings): JsonResponse {
            $register = $this->currentRegister();
            abort_unless($register, 422, 'Open register before taking payment.');

            $orderToken = $this->resolveOrderToken($payload);
            $invoice = $this->pendingInvoice($payload) ?? $this->nextInvoice();
            $total = round((float) $payload['total'], 2);
            $received = $paymentMethod === 'due' ? 0.0 : round((float) $request->input('received_amount', $total), 2);
            $paid = $paymentMethod === 'due' ? 0.0 : min($received, $total);
            $dueAmount = round(max(0, $total - $paid), 2);
            $change = $paymentMethod === 'due' ? 0.0 : round(max(0, $received - $total), 2);

            abort_if($dueAmount > 0 && ($customer?->is_walk_in || ! $settings['pos_allow_due_sale']), 422, 'Select a registered customer before leaving a due balance.');

            $fee = $paymentMethod === 'card' && $settings['pos_enable_card_fee'] && $paid > 0
                ? $paid * ($settings['pos_card_fee_rate'] / 100)
                : 0;

            $saleId = DB::table('sales')->insertGetId([
                'register_id' => $register->id,
                'user_id' => auth()->id(),
                'customer_id' => $payload['customer_id'],
                'waiter_id' => $payload['waiter_id'],
                'restaurant_table_id' => $payload['table_id'],
                'invoice_no' => $invoice,
                'order_token_id' => $orderToken->id,
                'sale_date' => now(),
                'subtotal' => $payload['subtotal'],
                'discount_amount' => $payload['discount_amount'],
                'service_charge' => $payload['service_charge'],
                'total' => $payload['total'],
                'paid_amount' => $paid,
                'due_amount' => $dueAmount,
                'profit' => $payload['profit'],
                'status' => $dueAmount > 0 ? 'due' : 'paid',
                'note' => $payload['note'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($payload['items'] as $item) {
                $product = DB::table('products')->where('id', $item['id'])->lockForUpdate()->first();
                abort_if($product->maintain_stock && $product->stock_quantity < $item['quantity'], 422, "{$product->name} does not have enough stock.");

                DB::table('sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $product->id,
                    'product_name' => $item['name'] ?? $product->name,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $product->cost_price,
                    'unit_price' => $item['price'],
                    'discount_type' => $item['discount_type'] ?? null,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'line_total' => $item['line_total'],
                    'profit' => (($item['price'] - $product->cost_price) * $item['quantity']) - ($item['discount_amount'] ?? 0),
                    'note' => $item['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($product->maintain_stock) {
                    $balance = $product->stock_quantity - $item['quantity'];
                    DB::table('products')->where('id', $product->id)->update(['stock_quantity' => $balance, 'updated_at' => now()]);
                    DB::table('stock_movements')->insert([
                        'product_id' => $product->id,
                        'type' => 'sale',
                        'quantity' => -abs((float) $item['quantity']),
                        'balance_after' => $balance,
                        'source_type' => 'sale',
                        'source_id' => $saleId,
                        'note' => $invoice,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($paymentMethod !== 'due' && $paid > 0) {
                DB::table('sale_payments')->insert([
                    'sale_id' => $saleId,
                    'register_id' => $register->id,
                    'payment_method' => $paymentMethod,
                    'amount' => $paid,
                    'received_amount' => $received,
                    'change_amount' => $change,
                    'fee_amount' => $fee,
                    'paid_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (in_array($paymentMethod, ['card', 'qr'], true) && $paid > 0) {
                $defaultBankAccountId = DB::table('bank_accounts')->where('is_default', true)->value('id');
                DB::table('bank_transactions')->insert([
                    'bank_account_id' => $defaultBankAccountId,
                    'type' => $paymentMethod === 'card' ? 'card_payment' : 'qr_payment',
                    'amount' => $paid,
                    'transaction_date' => now(),
                    'source_type' => 'sale',
                    'source_id' => $saleId,
                    'note' => $invoice,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($fee > 0) {
                $categoryId = DB::table('expense_categories')->where('name', 'Card Fee')->value('id');
                $defaultBankAccountId ??= DB::table('bank_accounts')->where('is_default', true)->value('id');
                $expenseId = DB::table('expenses')->insertGetId([
                    'expense_category_id' => $categoryId,
                    'user_id' => auth()->id(),
                    'amount' => $fee,
                    'payment_method' => 'bank',
                    'bank_account_id' => $defaultBankAccountId,
                    'expense_date' => now(),
                    'note' => 'Automatic card payment fee.',
                    'source_type' => 'sale',
                    'source_id' => $saleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($defaultBankAccountId) {
                    DB::table('bank_transactions')->insert([
                        'bank_account_id' => $defaultBankAccountId,
                        'type' => 'bank_expense',
                        'amount' => $fee,
                        'transaction_date' => now(),
                        'source_type' => 'expense',
                        'source_id' => $expenseId,
                        'note' => 'Automatic card payment fee.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($payload['waiter_id'] && $this->settings()['pos_enable_waiter_incentive']) {
                $waiter = DB::table('waiters')->where('id', $payload['waiter_id'])->first();
                $percentage = $waiter->incentive_percentage ?: $this->settings()['pos_default_waiter_incentive'];
                DB::table('waiter_incentives')->insert([
                    'waiter_id' => $payload['waiter_id'],
                    'sale_id' => $saleId,
                    'sale_amount' => $payload['total'],
                    'percentage' => $percentage,
                    'amount' => $payload['total'] * ($percentage / 100),
                    'earned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->completePendingOrder($payload);
            ActivityLog::record('create', 'sales', 'Sale completed.', ['sale_id' => $saleId, 'invoice' => $invoice, 'token' => $orderToken->token_number]);

            return response()->json([
                'message' => $dueAmount > 0 ? 'Payment saved with due balance.' : 'Payment completed.',
                'invoice' => $invoice,
                'sale_id' => $saleId,
                'paid_amount' => $paid,
                'received_amount' => $received,
                'change_amount' => $change,
                'due_amount' => $dueAmount,
                'status' => $dueAmount > 0 ? 'due' : 'paid',
                'total' => $payload['total'],
                'order_date' => now()->format('d/m/Y, H:i:s'),
                ...$this->tokenResponse($orderToken),
            ]);
        });
    }

    public function storeCustomerDuePayment(Request $request): JsonResponse
    {
        $this->authorize('pos.payment');
        $register = $this->currentRegister();
        abort_unless($register, 422, 'Open register before receiving due payment.');

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank,card,qr'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
        ]);

        return DB::transaction(function () use ($validated, $register): JsonResponse {
            $sales = DB::table('sales')
                ->where('customer_id', $validated['customer_id'])
                ->where('due_amount', '>', 0)
                ->whereNull('deleted_at')
                ->orderBy('sale_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $totalDue = (float) $sales->sum('due_amount');
            $amount = round((float) $validated['amount'], 2);
            abort_if($totalDue <= 0, 422, 'This customer has no due balance.');
            abort_if($amount > $totalDue, 422, 'Payment amount cannot be greater than customer due.');

            $remaining = $amount;
            foreach ($sales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $applied = min($remaining, (float) $sale->due_amount);
                $newDue = round((float) $sale->due_amount - $applied, 2);

                DB::table('sale_payments')->insert([
                    'sale_id' => $sale->id,
                    'register_id' => $register->id,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $applied,
                    'received_amount' => $applied,
                    'change_amount' => 0,
                    'fee_amount' => 0,
                    'paid_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('sales')->where('id', $sale->id)->update([
                    'paid_amount' => (float) $sale->paid_amount + $applied,
                    'due_amount' => $newDue,
                    'status' => $newDue <= 0 ? 'paid' : 'due',
                    'updated_at' => now(),
                ]);

                $remaining = round($remaining - $applied, 2);
            }

            $this->recordDuePaymentMovement(
                amount: $amount,
                paymentMethod: $validated['payment_method'],
                bankAccountId: $validated['bank_account_id'] ?? null,
                registerId: (int) $register->id,
                sourceType: 'customer_due_payment',
                sourceId: (int) $validated['customer_id'],
                note: 'Customer due payment received'
            );

            ActivityLog::record('create', 'customer_due_payment', 'Customer due payment received from POS.', [
                'customer_id' => $validated['customer_id'],
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
            ]);

            return response()->json([
                'message' => 'Customer due payment saved.',
                'paid_amount' => $amount,
                'remaining_due' => round($totalDue - $amount, 2),
            ]);
        });
    }

    public function storeSupplierPayment(Request $request): JsonResponse
    {
        $this->authorize('purchases.edit');
        $register = $this->currentRegister();
        abort_unless($register, 422, 'Open register before paying supplier.');

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank,card,qr'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
        ]);

        return DB::transaction(function () use ($validated, $register): JsonResponse {
            $purchases = DB::table('purchases')
                ->where('supplier_id', $validated['supplier_id'])
                ->where('due_amount', '>', 0)
                ->whereNull('deleted_at')
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $totalDue = (float) $purchases->sum('due_amount');
            $amount = round((float) $validated['amount'], 2);
            abort_if($totalDue <= 0, 422, 'This supplier has no due balance.');
            abort_if($amount > $totalDue, 422, 'Payment amount cannot be greater than supplier due.');

            $remaining = $amount;
            foreach ($purchases as $purchase) {
                if ($remaining <= 0) {
                    break;
                }

                $applied = min($remaining, (float) $purchase->due_amount);
                $newDue = round((float) $purchase->due_amount - $applied, 2);

                DB::table('purchase_payments')->insert([
                    'purchase_id' => $purchase->id,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $applied,
                    'paid_at' => now(),
                    'note' => 'Supplier due payment from POS.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('purchases')->where('id', $purchase->id)->update([
                    'paid_amount' => (float) $purchase->paid_amount + $applied,
                    'due_amount' => $newDue,
                    'updated_at' => now(),
                ]);

                $remaining = round($remaining - $applied, 2);
            }

            $this->recordSupplierPaymentMovement(
                amount: $amount,
                paymentMethod: $validated['payment_method'],
                bankAccountId: $validated['bank_account_id'] ?? null,
                registerId: (int) $register->id,
                supplierId: (int) $validated['supplier_id']
            );

            ActivityLog::record('create', 'supplier_payment', 'Supplier due payment made from POS.', [
                'supplier_id' => $validated['supplier_id'],
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
            ]);

            return response()->json([
                'message' => 'Supplier payment saved.',
                'paid_amount' => $amount,
                'remaining_due' => round($totalDue - $amount, 2),
            ]);
        });
    }

    public function getRegisterCloseSummary(): JsonResponse
    {
        $this->authorize('pos.close_register');
        $register = $this->currentRegister();
        abort_unless($register, 422, 'No open register.');

        $summary = $this->registerSummary($register->id);
        $bankOpeningBalance = (float) DB::table('bank_accounts')->sum('opening_balance');
        $bankBalance = $bankOpeningBalance + (float) DB::table('bank_transactions')
            ->sum(DB::raw("case when type in ('deposit','qr_payment','card_payment','cash_to_bank','bank_transfer_in') then amount else -amount end"));

        $totalOrders = (int) DB::table('sales')->where('register_id', $register->id)->whereNull('deleted_at')->count();
        $totalTablesServed = (int) DB::table('sales')->where('register_id', $register->id)->whereNull('deleted_at')->distinct('restaurant_table_id')->whereNotNull('restaurant_table_id')->count('restaurant_table_id');
        $totalTakeawayOrders = (int) DB::table('sales')->where('register_id', $register->id)->whereNull('deleted_at')->whereNull('restaurant_table_id')->count();
        $expenses = $this->registerExpenses($register);

        $currentRegisterCashBalance = $summary['expected_cash'];
        $overallCashBreakdown = $this->cashBalanceBreakdown();
        $cashBalanceNow = $overallCashBreakdown['now'];
        $cashBalanceBefore = $overallCashBreakdown['before'];

        return response()->json([
            'cash_in_cashier' => $cashBalanceNow,
            'current_register_cash_balance' => $currentRegisterCashBalance,
            'overall_cash_balance' => $cashBalanceNow,
            'overall_cash_breakdown' => $overallCashBreakdown,
            'bank_amount' => $summary['bank_amount'],
            'bank_balance' => $bankBalance,
            'total_orders' => $totalOrders,
            'total_tables_served' => $totalTablesServed,
            'total_takeaway_orders' => $totalTakeawayOrders,
            'expenses' => $expenses,
            'summary' => $summary,
            'cash_balance_now' => $cashBalanceNow,
            'cash_balance_before' => $cashBalanceBefore,
            'cash_drawer_open_balance' => $summary['opening_cash'],
            'total_sale_cash' => $summary['cash_sales'],
            'total_expense_amount' => $summary['expenses'],
        ]);
    }

    public function downloadRegisterCloseCashBookPdf(): mixed
    {
        $this->authorize('pos.close_register');
        $register = $this->currentRegister();
        abort_unless($register, 422, 'No open register.');

        $filename = 'register-cash-book-'.$register->id.'-'.now()->format('Ymd-His').'.pdf';

        return response($this->registerCloseCashBookPdf($this->registerCloseCashBook($register)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function closeRegister(Request $request): RedirectResponse
    {
        $this->authorize('pos.close_register');
        $register = $this->currentRegister();
        abort_unless($register, 422, 'No open register.');

        $validated = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $summary = $this->registerSummary($register->id);
        $cashBalanceNow = $this->cashBalance();
        $difference = $validated['actual_cash'] - $cashBalanceNow;

        DB::transaction(function () use ($register, $validated, $summary, $difference, $cashBalanceNow): void {
            DB::table('register_closings')->insert([
                'register_id' => $register->id,
                ...$summary,
                'expected_cash' => $cashBalanceNow,
                'actual_cash' => $validated['actual_cash'],
                'difference' => $difference,
                'note' => $validated['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('registers')->where('id', $register->id)->update(['status' => 'closed', 'closed_at' => now(), 'updated_at' => now()]);

            if ($difference > 0) {
                DB::table('cash_ins')->insert([
                    'register_id' => $register->id,
                    'user_id' => auth()->id(),
                    'amount' => $difference,
                    'note' => trim('Register Close Overage. '.($validated['note'] ?? '')),
                    'movement_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($difference < 0) {
                DB::table('cash_outs')->insert([
                    'register_id' => $register->id,
                    'user_id' => auth()->id(),
                    'amount' => abs($difference),
                    'note' => trim('Register Close Shortage. '.($validated['note'] ?? '')),
                    'movement_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            ActivityLog::record('close', 'register', 'Register closed.', ['register_id' => $register->id, 'difference' => $difference]);
        });

        return redirect()->route('dashboard')->with('status', 'Register closed.');
    }

    private function currentRegister(): ?object
    {
        return DB::table('registers')->where('user_id', auth()->id())->where('status', 'open')->latest()->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storePendingOrder(array $payload, OrderToken $orderToken, string $orderStatus, string $tableStatus, ?string $invoice = null): int
    {
        if ($payload['hold_id']) {
            DB::table('hold_orders')->where('id', $payload['hold_id'])->whereIn('status', ['hold', 'payment_pending'])->update([
                'status' => 'replaced',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif ($payload['table_id']) {
            DB::table('hold_orders')->where('restaurant_table_id', $payload['table_id'])->whereIn('status', ['hold', 'payment_pending'])->update([
                'status' => 'replaced',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $holdId = DB::table('hold_orders')->insertGetId([
            'user_id' => auth()->id(),
            'customer_id' => $payload['customer_id'],
            'waiter_id' => $payload['waiter_id'] ?? null,
            'restaurant_table_id' => $payload['table_id'],
            'invoice_no' => $invoice,
            'order_token_id' => $orderToken->id,
            'subtotal' => $payload['subtotal'],
            'discount_amount' => $payload['discount_amount'],
            'service_charge' => $payload['service_charge'],
            'total' => $payload['total'],
            'status' => $orderStatus,
            'note' => $payload['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($payload['items'] as $item) {
            DB::table('hold_order_items')->insert([
                'hold_order_id' => $holdId,
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'discount_type' => $item['discount_type'] ?? null,
                'discount_amount' => $item['discount_amount'] ?? 0,
                'line_total' => $item['line_total'],
                'note' => $item['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($payload['table_id']) {
            DB::table('restaurant_tables')->where('id', $payload['table_id'])->update(['status' => $tableStatus, 'updated_at' => now()]);
        }

        return $holdId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveOrderToken(array $payload): OrderToken
    {
        $holdOrder = null;

        if ($payload['hold_id']) {
            $holdOrder = DB::table('hold_orders')
                ->where('id', $payload['hold_id'])
                ->whereIn('status', ['hold', 'payment_pending'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            abort_if(! $holdOrder, 422, 'This held order is no longer active.');
        } elseif ($payload['table_id']) {
            $holdOrder = DB::table('hold_orders')
                ->where('restaurant_table_id', $payload['table_id'])
                ->whereIn('status', ['hold', 'payment_pending'])
                ->whereNull('deleted_at')
                ->latest()
                ->lockForUpdate()
                ->first();
        }

        if ($holdOrder?->order_token_id) {
            return OrderToken::query()->findOrFail($holdOrder->order_token_id);
        }

        $orderToken = $this->dailyTokenService->issue();

        if ($holdOrder) {
            DB::table('hold_orders')->where('id', $holdOrder->id)->update([
                'order_token_id' => $orderToken->id,
                'updated_at' => now(),
            ]);
        }

        return $orderToken;
    }

    private function ensureHoldToken(object $holdOrder): object
    {
        $orderToken = $holdOrder->order_token_id
            ? OrderToken::query()->findOrFail($holdOrder->order_token_id)
            : $this->dailyTokenService->issue();

        if (! $holdOrder->order_token_id) {
            DB::table('hold_orders')->where('id', $holdOrder->id)->update([
                'order_token_id' => $orderToken->id,
                'updated_at' => now(),
            ]);
            $holdOrder->order_token_id = $orderToken->id;
        }

        $holdOrder->token_number = $orderToken->token_number;
        $holdOrder->token_date = $orderToken->token_date->toDateString();
        $holdOrder->formatted_token = $this->dailyTokenService->format($orderToken->token_number);

        return $holdOrder;
    }

    /**
     * @return array{token_number: int, token_date: string, formatted_token: string, next_token: array{date: string, number: int, display: string}}
     */
    private function tokenResponse(OrderToken $orderToken): array
    {
        return [
            'token_number' => $orderToken->token_number,
            'token_date' => $orderToken->token_date->toDateString(),
            'formatted_token' => $this->dailyTokenService->format($orderToken->token_number),
            'next_token' => $this->dailyTokenService->nextAvailable(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function completePendingOrder(array $payload): void
    {
        if ($payload['hold_id']) {
            DB::table('hold_orders')->where('id', $payload['hold_id'])->whereIn('status', ['hold', 'payment_pending'])->update(['status' => 'completed', 'deleted_at' => now(), 'updated_at' => now()]);
        } elseif ($payload['table_id']) {
            DB::table('hold_orders')->where('restaurant_table_id', $payload['table_id'])->whereIn('status', ['hold', 'payment_pending'])->update(['status' => 'completed', 'deleted_at' => now(), 'updated_at' => now()]);
        }

        if ($payload['table_id']) {
            DB::table('restaurant_tables')->where('id', $payload['table_id'])->update(['status' => 'available', 'updated_at' => now()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cartPayload(Request $request): array
    {
        $validated = $request->validate([
            'table_id' => ['nullable', 'exists:restaurant_tables,id'],
            'hold_id' => ['nullable', 'exists:hold_orders,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'waiter_id' => ['nullable', 'exists:waiters,id'],
            'note' => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:products,id'],
            'items.*.name' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:fixed,percentage'],
            'items.*.note' => ['nullable', 'string'],
        ]);

        $subtotal = 0;
        $profit = 0;
        foreach ($validated['items'] as $index => $item) {
            $product = DB::table('products')->where('id', $item['id'])->first();
            $validated['items'][$index]['name'] = trim((string) ($item['name'] ?? '')) ?: $product->name;
            $discount = (float) ($item['discount_amount'] ?? 0);
            $lineTotal = ((float) $item['price'] * (float) $item['quantity']) - $discount;
            $validated['items'][$index]['line_total'] = $lineTotal;
            $subtotal += $lineTotal;
            $profit += (((float) $item['price'] - (float) $product->cost_price) * (float) $item['quantity']) - $discount;
        }

        $serviceCharge = $this->settings()['pos_enable_service_charge'] ? $subtotal * ($this->settings()['pos_service_charge_percentage'] / 100) : 0;
        $discount = (float) ($validated['discount_amount'] ?? 0);

        return [
            ...$validated,
            'table_id' => $validated['table_id'] ?? null,
            'hold_id' => $validated['hold_id'] ?? null,
            'waiter_id' => $validated['waiter_id'] ?? null,
            'note' => $validated['note'] ?? null,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'service_charge' => $serviceCharge,
            'total' => max(0, $subtotal + $serviceCharge - $discount),
            'profit' => $profit - $discount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = DB::table('settings')->get()->mapWithKeys(fn ($setting) => [$setting->group.'_'.$setting->key => $setting->value])->all();

        return [
            'business_name' => $settings['business_name'] ?? 'Hotel POS',
            'business_tagline' => $settings['business_tagline'] ?? 'Restaurant operations',
            'business_address' => $settings['business_address'] ?? null,
            'business_phone' => $settings['business_phone'] ?? null,
            'business_logo' => $settings['business_logo'] ?? null,
            'currency_symbol' => $settings['currency_symbol'] ?? 'Rs.',
            'invoice_paper_size' => $settings['invoice_paper_size'] ?? '80mm',
            'invoice_show_logo' => filter_var($settings['invoice_show_logo'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'invoice_show_customer' => filter_var($settings['invoice_show_customer'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'invoice_show_waiter' => filter_var($settings['invoice_show_waiter'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'invoice_show_table' => filter_var($settings['invoice_show_table'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'invoice_footer_text' => $settings['invoice_footer_text'] ?? null,
            'invoice_terms' => $settings['invoice_terms'] ?? null,
            'pos_enable_card_fee' => filter_var($settings['pos_enable_card_fee'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'pos_card_fee_rate' => (float) ($settings['pos_card_fee_rate'] ?? 3),
            'pos_enable_waiter_incentive' => filter_var($settings['pos_enable_waiter_incentive'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'pos_default_waiter_incentive' => (float) ($settings['pos_default_waiter_incentive'] ?? 2),
            'pos_allow_due_sale' => filter_var($settings['pos_allow_due_sale'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'pos_enable_service_charge' => filter_var($settings['pos_enable_service_charge'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'pos_service_charge_percentage' => (float) ($settings['pos_service_charge_percentage'] ?? 10),
            'pos_qr_code_image' => $settings['pos_qr_code_image'] ?? null,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function registerSummary(int $registerId): array
    {
        $register = DB::table('registers')->where('id', $registerId)->first();
        $paymentRegisterScope = fn ($query) => $query
            ->where('sale_payments.register_id', $registerId)
            ->orWhere(fn ($fallback) => $fallback
                ->whereNull('sale_payments.register_id')
                ->where('sales.register_id', $registerId));
        $salePaymentsForRegister = fn (string $paymentMethod): float => (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereNull('sales.deleted_at')
            ->where($paymentRegisterScope)
            ->whereBetween('sale_payments.paid_at', [$register->opened_at, now()])
            ->where('sale_payments.payment_method', $paymentMethod)
            ->sum('sale_payments.amount');

        $cashSales = $salePaymentsForRegister('cash');
        $cardSales = $salePaymentsForRegister('card');
        $qrSales = $salePaymentsForRegister('qr');
        $expenseQuery = DB::table('expenses')
            ->leftJoin('sales as expense_sales', function ($join): void {
                $join->on('expense_sales.id', '=', 'expenses.source_id')
                    ->where('expenses.source_type', 'sale');
            })
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.expense_date', [$register->opened_at, now()])
            ->where(function ($query): void {
                $query->whereNull('expenses.source_type')
                    ->orWhere('expenses.source_type', '!=', 'sale')
                    ->orWhere(function ($saleExpense): void {
                        $saleExpense->whereNotNull('expense_sales.id')
                            ->whereNull('expense_sales.deleted_at');
                    });
            });
        $expenses = (float) (clone $expenseQuery)->where('expenses.payment_method', 'cash')->sum('expenses.amount');
        $bankExpenses = (float) (clone $expenseQuery)->where('expenses.payment_method', '!=', 'cash')->sum('expenses.amount');
        $bankTransferIn = (float) DB::table('bank_transactions')
            ->whereBetween('transaction_date', [$register->opened_at, now()])
            ->whereIn('type', ['cash_to_bank', 'bank_transfer_in'])
            ->sum('amount');
        $bankTransferOut = (float) DB::table('bank_transactions')
            ->whereBetween('transaction_date', [$register->opened_at, now()])
            ->whereIn('type', ['bank_to_cash', 'bank_transfer_out'])
            ->sum('amount');
        $dueBankIn = (float) DB::table('bank_transactions')
            ->whereBetween('transaction_date', [$register->opened_at, now()])
            ->where('source_type', 'customer_due_payment')
            ->where('type', 'deposit')
            ->sum('amount');
        $supplierBankOut = (float) DB::table('bank_transactions')
            ->whereBetween('transaction_date', [$register->opened_at, now()])
            ->where('source_type', 'supplier_payment')
            ->where('type', 'bank_expense')
            ->sum('amount');
        $cashIn = (float) DB::table('cash_ins')
            ->where('register_id', $registerId)
            ->whereNull('deleted_at')
            ->whereBetween('movement_date', [$register->opened_at, now()])
            ->sum('amount');
        $cashOut = (float) DB::table('cash_outs')
            ->where('register_id', $registerId)
            ->whereNull('deleted_at')
            ->whereBetween('movement_date', [$register->opened_at, now()])
            ->sum('amount');
        $expectedCash = (float) $register->opening_cash + $cashSales + $cashIn - $cashOut - $expenses;

        return [
            'opening_cash' => (float) $register->opening_cash,
            'cash_sales' => $cashSales,
            'card_sales' => $cardSales,
            'qr_sales' => $qrSales,
            'expenses' => $expenses,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expectedCash,
            'bank_amount' => $cardSales + $qrSales + $bankTransferIn + $dueBankIn - $bankTransferOut - $bankExpenses - $supplierBankOut,
        ];
    }

    /**
     * @return array{from: string, to: string, sections: array<int, array{title: string, rows: array<int, array<string, mixed>>, total_debits: float, total_credits: float, closing: float}>}
     */
    private function registerCloseCashBook(object $register): array
    {
        $from = $register->opened_at;
        $to = now();
        $cashRows = collect([
            $this->registerCloseCashBookRow(
                date: $from,
                number: 'REG'.$register->id,
                payee: User::visibleName(auth()->user()?->name, 'Cashier'),
                particulars: 'Register opening',
                debit: (float) $register->opening_cash
            ),
        ]);

        $paymentRegisterScope = fn ($query) => $query
            ->where('sale_payments.register_id', $register->id)
            ->orWhere(fn ($fallback) => $fallback
                ->whereNull('sale_payments.register_id')
                ->where('sales.register_id', $register->id));

        $cashSales = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereNull('sales.deleted_at')
            ->where($paymentRegisterScope)
            ->where('sale_payments.payment_method', 'cash')
            ->whereBetween('sale_payments.paid_at', [$from, $to])
            ->get(['sales.invoice_no', 'sale_payments.paid_at as date', 'sale_payments.amount', 'customers.name as customer_name'])
            ->map(fn (object $row): array => $this->registerCloseCashBookRow(
                date: $row->date,
                number: $row->invoice_no ?? '-',
                payee: $row->customer_name ?? 'Cash',
                particulars: trim(($row->invoice_no ?? 'Sale').' CASH SALE'),
                debit: (float) $row->amount
            ));

        $cashIns = DB::table('cash_ins')
            ->leftJoin('users', 'users.id', '=', 'cash_ins.user_id')
            ->whereNull('cash_ins.deleted_at')
            ->where('cash_ins.register_id', $register->id)
            ->whereBetween('cash_ins.movement_date', [$from, $to])
            ->get(['cash_ins.id', 'cash_ins.movement_date as date', 'cash_ins.amount', 'cash_ins.note', 'users.name as user_name'])
            ->map(fn (object $row): array => $this->registerCloseCashBookRow(
                date: $row->date,
                number: 'CI'.$row->id,
                payee: User::visibleName($row->user_name, 'Cash'),
                particulars: $row->note ?: 'Cash in',
                debit: (float) $row->amount
            ));

        $cashOuts = DB::table('cash_outs')
            ->leftJoin('users', 'users.id', '=', 'cash_outs.user_id')
            ->whereNull('cash_outs.deleted_at')
            ->where('cash_outs.register_id', $register->id)
            ->whereBetween('cash_outs.movement_date', [$from, $to])
            ->get(['cash_outs.id', 'cash_outs.movement_date as date', 'cash_outs.amount', 'cash_outs.note', 'users.name as user_name'])
            ->map(fn (object $row): array => $this->registerCloseCashBookRow(
                date: $row->date,
                number: 'CO'.$row->id,
                payee: User::visibleName($row->user_name, 'Cash'),
                particulars: $row->note ?: 'Cash out',
                credit: (float) $row->amount
            ));

        $cashExpenses = DB::table('expenses')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('sales as expense_sales', function ($join): void {
                $join->on('expense_sales.id', '=', 'expenses.source_id')
                    ->where('expenses.source_type', 'sale');
            })
            ->whereNull('expenses.deleted_at')
            ->where('expenses.payment_method', 'cash')
            ->whereBetween('expenses.expense_date', [$from, $to])
            ->where(function ($query): void {
                $query->whereNull('expenses.source_type')
                    ->orWhere('expenses.source_type', '!=', 'sale')
                    ->orWhere(function ($saleExpense): void {
                        $saleExpense->whereNotNull('expense_sales.id')
                            ->whereNull('expense_sales.deleted_at');
                    });
            })
            ->get(['expenses.id', 'expenses.expense_date as date', 'expenses.amount', 'expenses.note', 'expense_categories.name as category'])
            ->map(fn (object $row): array => $this->registerCloseCashBookRow(
                date: $row->date,
                number: 'EX'.$row->id,
                payee: $row->category ?? 'Expense',
                particulars: $row->note ?: 'Cash expense',
                credit: (float) $row->amount
            ));

        $cashRows = $cashRows
            ->merge($cashSales)
            ->merge($cashIns)
            ->merge($cashOuts)
            ->merge($cashExpenses)
            ->sortBy([['date', 'asc'], ['number', 'asc']])
            ->values();

        $bankRows = DB::table('bank_transactions')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'bank_transactions.bank_account_id')
            ->leftJoin('sales', function ($join): void {
                $join->on('sales.id', '=', 'bank_transactions.source_id')
                    ->where('bank_transactions.source_type', 'sale');
            })
            ->leftJoin('expenses', function ($join): void {
                $join->on('expenses.id', '=', 'bank_transactions.source_id')
                    ->where('bank_transactions.source_type', 'expense');
            })
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('bank_transactions.transaction_date', [$from, $to])
            ->where(function ($query): void {
                $query->whereNull('bank_transactions.source_type')
                    ->orWhere('bank_transactions.source_type', '!=', 'sale')
                    ->orWhere(function ($saleTransaction): void {
                        $saleTransaction->whereNotNull('sales.id')
                            ->whereNull('sales.deleted_at');
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('bank_transactions.source_type')
                    ->orWhere('bank_transactions.source_type', '!=', 'expense')
                    ->orWhere(function ($expenseTransaction): void {
                        $expenseTransaction->whereNotNull('expenses.id')
                            ->whereNull('expenses.deleted_at');
                    });
            })
            ->orderBy('bank_transactions.transaction_date')
            ->orderBy('bank_transactions.id')
            ->get([
                'bank_transactions.id',
                'bank_transactions.type',
                'bank_transactions.amount',
                'bank_transactions.transaction_date as date',
                'bank_transactions.note',
                'bank_transactions.source_type',
                'sales.invoice_no',
                'bank_accounts.name as account_name',
                'expense_categories.name as expense_category',
            ])
            ->map(function (object $row): array {
                $isDebit = in_array($row->type, ['deposit', 'qr_payment', 'card_payment', 'cash_to_bank', 'bank_transfer_in'], true);

                return $this->registerCloseCashBookRow(
                    date: $row->date,
                    number: 'BT'.$row->id,
                    payee: $this->registerCloseBankPayee($row),
                    particulars: $row->note ?: $this->registerCloseBankLabel($row->type),
                    debit: $isDebit ? (float) $row->amount : 0.0,
                    credit: $isDebit ? 0.0 : (float) $row->amount,
                    account: $row->account_name ?? 'Bank'
                );
            })
            ->values();

        $sections = [
            $this->registerCloseCashBookSection('Cash Drawer', $cashRows),
        ];

        if ($bankRows->isNotEmpty()) {
            $sections[] = $this->registerCloseCashBookSection('Bank Transactions', $bankRows);
        }

        return [
            'from' => (string) $from,
            'to' => $to->toDateTimeString(),
            'sections' => $sections,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{title: string, rows: array<int, array<string, mixed>>, total_debits: float, total_credits: float, closing: float}
     */
    private function registerCloseCashBookSection(string $title, Collection $rows): array
    {
        return [
            'title' => $title,
            'rows' => $rows->values()->all(),
            'total_debits' => (float) $rows->sum('debit'),
            'total_credits' => (float) $rows->sum('credit'),
            'closing' => (float) $rows->sum('debit') - (float) $rows->sum('credit'),
        ];
    }

    /**
     * @return array{date: string, number: string, payee: string, particulars: string, debit: float, credit: float, account: string|null}
     */
    private function registerCloseCashBookRow(string $date, string $number, string $payee, string $particulars, float $debit = 0.0, float $credit = 0.0, ?string $account = null): array
    {
        return [
            'date' => $date,
            'number' => $number,
            'payee' => $payee,
            'particulars' => $particulars,
            'debit' => $debit,
            'credit' => $credit,
            'account' => $account,
        ];
    }

    /**
     * @param  array{from: string, to: string, sections: array<int, array{title: string, rows: array<int, array<string, mixed>>, total_debits: float, total_credits: float, closing: float}>}  $cashBook
     */
    private function registerCloseCashBookPdf(array $cashBook): string
    {
        $pages = [];

        foreach ($cashBook['sections'] as $section) {
            $chunks = collect($section['rows'])->chunk(24);

            foreach ($chunks as $index => $chunk) {
                $pages[] = [
                    'section' => $section,
                    'rows' => $chunk->values(),
                    'show_totals' => $index === $chunks->count() - 1,
                ];
            }
        }

        $pageObjectIds = [];
        $contentObjectIds = [];
        foreach ($pages as $index => $_page) {
            $pageObjectIds[] = 3 + ($index * 2);
            $contentObjectIds[] = 4 + ($index * 2);
        }

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids ['.collect($pageObjectIds)->map(fn (int $id): string => "{$id} 0 R")->implode(' ').'] /Count '.count($pageObjectIds).' >> endobj',
        ];
        $fontObjectId = 3 + (count($pages) * 2);

        foreach ($pages as $index => $page) {
            $pageId = $pageObjectIds[$index];
            $contentId = $contentObjectIds[$index];
            $content = $this->registerCloseCashBookPdfPage($cashBook, $page, $index + 1, count($pages));

            $objects[] = "{$pageId} 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> /Contents {$contentId} 0 R >> endobj";
            $objects[] = "{$contentId} 0 obj << /Length ".strlen($content)." >> stream\n{$content}\nendstream endobj";
        }

        $objects[] = "{$fontObjectId} 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Courier >> endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    /**
     * @param  array{from: string, to: string}  $cashBook
     * @param  array{section: array<string, mixed>, rows: Collection<int, array<string, mixed>>, show_totals: bool}  $page
     */
    private function registerCloseCashBookPdfPage(array $cashBook, array $page, int $pageNumber, int $pageCount): string
    {
        $from = Carbon::parse($cashBook['from']);
        $to = Carbon::parse($cashBook['to']);
        $dateLabel = $from->format('Y-m-d H:i').' to '.$to->format('Y-m-d H:i');
        $settings = $this->settings();

        $content = $this->registerClosePdfTextAt(32, 558, mb_strimwidth($settings['business_name'], 0, 38), 17);
        $content .= $this->registerClosePdfTextAt(32, 538, 'REGISTER CASH BOOK', 12);
        $content .= $this->registerClosePdfTextAt(560, 558, 'OPENING TO CLOSE', 14);
        $content .= $this->registerClosePdfTextAt(560, 538, $dateLabel, 9);
        $content .= "30 526 m 812 526 l S\n";
        $content .= $this->registerClosePdfTextAt(32, 508, mb_strimwidth((string) $page['section']['title'], 0, 60), 12);
        $content .= $this->registerClosePdfTextAt(32, 486, 'DATE', 9);
        $content .= $this->registerClosePdfTextAt(96, 486, 'NUM#', 9);
        $content .= $this->registerClosePdfTextAt(184, 486, 'PAYEE / ACCOUNT', 9);
        $content .= $this->registerClosePdfTextAt(380, 486, 'PARTICULARS', 9);
        $content .= $this->registerClosePdfTextAt(670, 486, 'DEBIT', 9);
        $content .= $this->registerClosePdfTextAt(750, 486, 'CREDIT', 9);
        $content .= "30 480 m 812 480 l S\n";

        $y = 464;
        foreach ($page['rows'] as $row) {
            $payee = $row['account'] ?: $row['payee'];

            $content .= $this->registerClosePdfTextAt(32, $y, Carbon::parse($row['date'])->format('y-m-d'), 8);
            $content .= $this->registerClosePdfTextAt(96, $y, mb_strimwidth((string) $row['number'], 0, 18), 8);
            $content .= $this->registerClosePdfTextAt(184, $y, mb_strimwidth((string) $payee, 0, 28), 8);
            $content .= $this->registerClosePdfTextAt(380, $y, mb_strimwidth((string) $row['particulars'], 0, 42), 8);
            $content .= $this->registerClosePdfTextAt(650, $y, $this->registerClosePdfMoney((float) $row['debit']), 8);
            $content .= $this->registerClosePdfTextAt(735, $y, $this->registerClosePdfMoney((float) $row['credit']), 8);
            $y -= 15;
        }

        if ($page['show_totals']) {
            $y -= 8;
            $content .= $this->registerClosePdfTextAt(526, $y, 'TOTAL DEBITS :', 9);
            $content .= $this->registerClosePdfTextAt(675, $y, number_format((float) $page['section']['total_debits'], 2), 9);
            $y -= 17;
            $content .= $this->registerClosePdfTextAt(526, $y, 'TOTAL CREDITS :', 9);
            $content .= $this->registerClosePdfTextAt(675, $y, number_format((float) $page['section']['total_credits'], 2), 9);
            $y -= 17;
            $content .= $this->registerClosePdfTextAt(450, $y, mb_strimwidth((string) $page['section']['title'], 0, 26).' - BALANCE :', 9);
            $content .= $this->registerClosePdfTextAt(675, $y, number_format((float) $page['section']['closing'], 2), 9);
        }

        $content .= $this->registerClosePdfTextAt(32, 28, now()->format('l, d F, Y'), 8);
        $content .= $this->registerClosePdfTextAt(732, 28, "Page {$pageNumber} of {$pageCount}", 8);

        return $content;
    }

    private function registerClosePdfMoney(float $amount): string
    {
        return $amount === 0.0 ? '-' : number_format($amount, 2);
    }

    private function registerClosePdfTextAt(float $x, float $y, string $text, int $size = 9): string
    {
        return "BT /F1 {$size} Tf {$x} {$y} Td ({$this->registerClosePdfText($text)}) Tj ET\n";
    }

    private function registerClosePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8'));
    }

    private function registerCloseBankPayee(object $transaction): string
    {
        return match ($transaction->type) {
            'card_payment' => 'Card Sale',
            'qr_payment' => 'QR Payment',
            'cash_to_bank', 'bank_to_cash' => 'Cash Drawer',
            'bank_transfer_in' => 'Bank Transfer In',
            'bank_transfer_out' => 'Bank Transfer Out',
            'bank_expense' => $transaction->expense_category ?? 'Bank Expense',
            default => str((string) $transaction->source_type)->replace('_', ' ')->headline()->toString() ?: 'Bank',
        };
    }

    private function registerCloseBankLabel(string $type): string
    {
        return str($type)->replace('_', ' ')->headline()->toString();
    }

    private function customerDueOptions(): Collection
    {
        return DB::table('customers')
            ->join('sales', 'sales.customer_id', '=', 'customers.id')
            ->where('customers.is_active', true)
            ->where('customers.is_walk_in', false)
            ->whereNull('customers.deleted_at')
            ->whereNull('sales.deleted_at')
            ->where('sales.due_amount', '>', 0)
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->orderBy('customers.name')
            ->get([
                'customers.id',
                'customers.name',
                'customers.phone',
                DB::raw('sum(sales.due_amount) as due_amount'),
            ])
            ->map(fn (object $customer): array => [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'due_amount' => (float) $customer->due_amount,
            ]);
    }

    private function supplierDueOptions(): Collection
    {
        return DB::table('suppliers')
            ->join('purchases', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.is_active', true)
            ->whereNull('suppliers.deleted_at')
            ->whereNull('purchases.deleted_at')
            ->where('purchases.due_amount', '>', 0)
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.phone')
            ->orderBy('suppliers.name')
            ->get([
                'suppliers.id',
                'suppliers.name',
                'suppliers.phone',
                DB::raw('sum(purchases.due_amount) as due_amount'),
            ])
            ->map(fn (object $supplier): array => [
                'id' => (int) $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'due_amount' => (float) $supplier->due_amount,
            ]);
    }

    private function recordDuePaymentMovement(float $amount, string $paymentMethod, ?string $bankAccountId, int $registerId, string $sourceType, int $sourceId, string $note): void
    {
        if ($paymentMethod === 'cash') {
            return;
        }

        $accountId = $bankAccountId ?: DB::table('bank_accounts')->where('is_default', true)->value('id');
        abort_unless($accountId, 422, 'Please create a default bank account first.');

        DB::table('bank_transactions')->insert([
            'bank_account_id' => $accountId,
            'type' => match ($paymentMethod) {
                'card' => 'card_payment',
                'qr' => 'qr_payment',
                default => 'deposit',
            },
            'amount' => $amount,
            'transaction_date' => now(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordSupplierPaymentMovement(float $amount, string $paymentMethod, ?string $bankAccountId, int $registerId, int $supplierId): void
    {
        if ($paymentMethod === 'cash') {
            DB::table('cash_outs')->insert([
                'register_id' => $registerId,
                'user_id' => auth()->id(),
                'amount' => $amount,
                'movement_date' => now(),
                'note' => 'Supplier due payment from POS.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $accountId = $bankAccountId ?: DB::table('bank_accounts')->where('is_default', true)->value('id');
        abort_unless($accountId, 422, 'Please create a default bank account first.');

        DB::table('bank_transactions')->insert([
            'bank_account_id' => $accountId,
            'type' => 'bank_expense',
            'amount' => $amount,
            'transaction_date' => now(),
            'source_type' => 'supplier_payment',
            'source_id' => $supplierId,
            'note' => 'Supplier due payment from POS.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function registerExpenses(object $register): Collection
    {
        return DB::table('expenses')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('sales as expense_sales', function ($join): void {
                $join->on('expense_sales.id', '=', 'expenses.source_id')
                    ->where('expenses.source_type', 'sale');
            })
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.expense_date', [$register->opened_at, now()])
            ->where(function ($query): void {
                $query->whereNull('expenses.source_type')
                    ->orWhere('expenses.source_type', '!=', 'sale')
                    ->orWhere(function ($saleExpense): void {
                        $saleExpense->whereNotNull('expense_sales.id')
                            ->whereNull('expense_sales.deleted_at');
                    });
            })
            ->orderBy('expenses.id')
            ->get([
                'expenses.id',
                'expenses.amount',
                'expenses.payment_method',
                'expenses.note',
                'expenses.source_type',
                'expenses.source_id',
                'expense_categories.name as category',
            ])
            ->map(fn (object $expense): array => [
                'id' => (int) $expense->id,
                'amount' => (float) $expense->amount,
                'category' => $expense->category ?? 'Expense',
                'source' => $expense->payment_method === 'cash' ? 'drawer' : 'bank',
                'description' => $expense->note,
                'can_remove' => $expense->source_type === 'register' && (int) $expense->source_id === (int) $register->id,
            ]);
    }

    private function nextInvoice(): string
    {
        $prefix = DB::table('settings')->where('group', 'invoice')->where('key', 'prefix')->value('value') ?? 'INV';
        $printedBills = DB::table('hold_orders')->whereNotNull('invoice_no')->count();
        $completedSales = DB::table('sales')->count();

        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) ($completedSales + $printedBills + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function pendingInvoice(array $payload): ?string
    {
        if ($payload['hold_id']) {
            return DB::table('hold_orders')
                ->where('id', $payload['hold_id'])
                ->whereIn('status', ['hold', 'payment_pending'])
                ->whereNull('deleted_at')
                ->value('invoice_no');
        }

        if ($payload['table_id']) {
            return DB::table('hold_orders')
                ->where('restaurant_table_id', $payload['table_id'])
                ->whereIn('status', ['hold', 'payment_pending'])
                ->whereNull('deleted_at')
                ->latest()
                ->value('invoice_no');
        }

        return null;
    }

    private function cashBalance(): float
    {
        return $this->cashBalanceBreakdown()['now'];
    }

    /**
     * @return array{before: float, drawer_open_balance: float, cash_in: float, total_sale_cash: float, cash_out: float, total_expense_amount: float, now: float}
     */
    private function cashBalanceBreakdown(): array
    {
        $opening = (float) DB::table('registers')->sum('opening_cash');
        $cashSales = (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sale_payments.payment_method', 'cash')
            ->sum('sale_payments.amount');
        $cashIns = (float) DB::table('cash_ins')->whereNull('deleted_at')->sum('amount');
        $cashOuts = (float) DB::table('cash_outs')->whereNull('deleted_at')->sum('amount');
        $expenses = (float) DB::table('expenses')->where('payment_method', 'cash')->whereNull('deleted_at')->sum('amount');

        return [
            'before' => 0.0,
            'drawer_open_balance' => $opening,
            'cash_in' => $cashIns,
            'total_sale_cash' => $cashSales,
            'cash_out' => $cashOuts,
            'total_expense_amount' => $expenses,
            'now' => $opening + $cashIns + $cashSales - $cashOuts - $expenses,
        ];
    }
}
