<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderSource;
use App\Services\DailyTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OnlineOrderController extends Controller
{
    public function __construct(private readonly DailyTokenService $dailyTokenService) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('online_orders.view');

        $register = $this->currentRegister();

        if (! $register) {
            return redirect()->route('pos.index')->withErrors('Please open a register first to access online orders.');
        }
        $sources = OnlineOrderSource::query()->where('is_active', true)->orderBy('name')->get();
        $products = DB::table('products')->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get();

        $orders = OnlineOrder::query()
            ->leftJoin('online_order_sources', 'online_order_sources.id', '=', 'online_orders.online_order_source_id')
            ->leftJoin('order_tokens', 'order_tokens.id', '=', 'online_orders.order_token_id')
            ->select('online_orders.*', 'online_order_sources.name as source_name', 'order_tokens.token_number', 'order_tokens.token_date')
            ->when($request->filled('source_id') && $request->input('source_id') !== 'all', fn ($query) => $query->where('online_order_source_id', (int) $request->input('source_id')))
            ->when($request->filled('order_status') && $request->input('order_status') !== 'all', fn ($query) => $query->where('order_status', $request->input('order_status')))
            ->when($request->filled('payment_status') && $request->input('payment_status') !== 'all', fn ($query) => $query->where('payment_status', $request->input('payment_status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('order_reference', 'like', $search)
                        ->orWhere('customer_name', 'like', $search)
                        ->orWhere('customer_phone', 'like', $search);
                });
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('online_orders.created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('online_orders.created_at', '<=', $request->date('to')))
            ->latest('online_orders.id')
            ->paginate(20)
            ->withQueryString();

        return view('pos.online-orders', [
            'register' => $register,
            'sources' => $sources,
            'products' => $products,
            'orders' => $orders,
            'currency' => $this->currencySymbol(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('online_orders.create');
        $register = $this->currentRegister();

        if (! $register) {
            return back()->withErrors('Open register first.');
        }

        $validated = $request->validate([
            'online_order_source_id' => ['required', 'exists:online_order_sources,id'],
            'order_reference' => ['required', 'string', 'max:255', 'unique:online_orders,order_reference'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['required', 'in:paid,cash_on_delivery,pending,partially_paid'],
            'payment_method' => ['required', 'in:cash,bank,card,online,platform_payment'],
            'order_status' => ['required', 'in:new,preparing,ready,out_for_delivery,delivered,cancelled'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items_payload' => ['required', 'string'],
        ]);

        $items = json_decode($validated['items_payload'], true);

        if (! is_array($items) || count($items) === 0) {
            return back()->withErrors('Add at least one item.');
        }

        $source = OnlineOrderSource::query()->findOrFail((int) $validated['online_order_source_id']);

        $lineItems = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            if ($productId <= 0 || $qty <= 0 || $price < 0) {
                return back()->withErrors('Invalid item payload.');
            }

            $product = DB::table('products')->where('id', $productId)->first();
            if (! $product) {
                return back()->withErrors('One or more selected products are invalid.');
            }

            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;

            $lineItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'qty' => $qty,
                'unit_price' => $price,
                'total' => $lineTotal,
            ];
        }

        $discountValue = (float) ($validated['discount_value'] ?? 0);
        $discountType = $validated['discount_type'] ?? 'fixed';
        $discountAmount = $discountType === 'percentage' ? ($subtotal * $discountValue / 100) : $discountValue;
        $discountAmount = min($discountAmount, $subtotal);
        $deliveryCharge = (float) ($validated['delivery_charge'] ?? 0);

        $commissionType = $source->commission_type;
        $commissionValue = (float) $source->commission_value;
        $commissionAmount = $commissionType === 'percentage'
            ? (($subtotal - $discountAmount) * $commissionValue / 100)
            : $commissionValue;

        $total = max(0.0, $subtotal - $discountAmount + $deliveryCharge);
        $requestedPaid = (float) ($validated['paid_amount'] ?? 0);
        $paidAmount = match ($validated['payment_status']) {
            'paid' => $total,
            'partially_paid' => min($total, $requestedPaid),
            default => 0.0,
        };
        $balanceAmount = max(0.0, $total - $paidAmount);
        $paymentStatus = $balanceAmount <= 0.0001 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : $validated['payment_status']);

        DB::transaction(function () use ($register, $validated, $lineItems, $source, $subtotal, $discountType, $discountValue, $discountAmount, $deliveryCharge, $commissionType, $commissionValue, $commissionAmount, $total, $paidAmount, $balanceAmount, $paymentStatus): void {
            $orderToken = $this->dailyTokenService->issue();
            $onlineOrderId = DB::table('online_orders')->insertGetId([
                'register_id' => $register->id,
                'order_token_id' => $orderToken->id,
                'online_order_source_id' => (int) $validated['online_order_source_id'],
                'order_reference' => $validated['order_reference'],
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'delivery_charge' => $deliveryCharge,
                'commission_type' => $commissionType,
                'commission_value' => $commissionValue,
                'commission_amount' => $commissionAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'payment_status' => $paymentStatus,
                'payment_method' => $validated['payment_method'],
                'order_status' => $validated['order_status'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lineItems as $item) {
                DB::table('online_order_items')->insert([
                    'online_order_id' => $onlineOrderId,
                    ...$item,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $profit = 0.0;
            foreach ($lineItems as $item) {
                $product = DB::table('products')->where('id', $item['product_id'])->first();
                $profit += (($item['unit_price'] - (float) ($product->cost_price ?? 0)) * $item['qty']);
            }
            $profit -= $discountAmount;
            $profit -= $commissionAmount;

            $saleId = DB::table('sales')->insertGetId([
                'register_id' => $register->id,
                'user_id' => auth()->id(),
                'invoice_no' => $validated['order_reference'],
                'order_token_id' => $orderToken->id,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'service_charge' => $deliveryCharge,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'due_amount' => $balanceAmount,
                'profit' => $profit,
                'status' => $balanceAmount > 0 ? 'due' : 'paid',
                'order_channel' => 'online',
                'online_order_source_id' => (int) $validated['online_order_source_id'],
                'online_order_reference' => $validated['order_reference'],
                'online_order_status' => $validated['order_status'],
                'online_payment_status' => $paymentStatus,
                'note' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('online_orders')->where('id', $onlineOrderId)->update([
                'sale_id' => $saleId,
                'updated_at' => now(),
            ]);

            foreach ($lineItems as $item) {
                $product = DB::table('products')->where('id', $item['product_id'])->first();
                DB::table('sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['qty'],
                    'unit_cost' => (float) ($product->cost_price ?? 0),
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['total'],
                    'profit' => (($item['unit_price'] - (float) ($product->cost_price ?? 0)) * $item['qty']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($paidAmount > 0) {
                DB::table('online_order_payments')->insert([
                    'online_order_id' => $onlineOrderId,
                    'register_id' => $register->id,
                    'amount' => $paidAmount,
                    'payment_method' => $validated['payment_method'],
                    'payment_date' => now(),
                    'note' => 'Initial payment',
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $salePaymentMethod = match ($validated['payment_method']) {
                    'cash' => 'cash',
                    'card' => 'card',
                    default => 'qr',
                };

                DB::table('sale_payments')->insert([
                    'sale_id' => $saleId,
                    'payment_method' => $salePaymentMethod,
                    'amount' => $paidAmount,
                    'received_amount' => $paidAmount,
                    'change_amount' => 0,
                    'fee_amount' => 0,
                    'paid_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($validated['payment_method'] !== 'cash') {
                    DB::table('bank_transactions')->insert([
                        'bank_account_id' => DB::table('bank_accounts')->where('is_default', true)->value('id'),
                        'type' => 'deposit',
                        'amount' => $paidAmount,
                        'transaction_date' => now(),
                        'source_type' => 'online_order_payment',
                        'source_id' => $onlineOrderId,
                        'note' => 'Online order payment '.$validated['order_reference'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($commissionAmount > 0) {
                $commissionCategory = DB::table('expense_categories')->where('name', 'Online Platform Commission')->value('id');
                $defaultBankAccountId = DB::table('bank_accounts')->where('is_default', true)->value('id');

                $expenseId = DB::table('expenses')->insertGetId([
                    'expense_category_id' => $commissionCategory,
                    'user_id' => auth()->id(),
                    'amount' => $commissionAmount,
                    'payment_method' => 'bank',
                    'bank_account_id' => $defaultBankAccountId,
                    'expense_date' => now(),
                    'note' => $source->name.' commission for order '.$validated['order_reference'],
                    'source_type' => 'online_order_commission',
                    'source_id' => $onlineOrderId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($defaultBankAccountId) {
                    DB::table('bank_transactions')->insert([
                        'bank_account_id' => $defaultBankAccountId,
                        'type' => 'bank_expense',
                        'amount' => $commissionAmount,
                        'transaction_date' => now(),
                        'source_type' => 'expense',
                        'source_id' => $expenseId,
                        'note' => $source->name.' commission for order '.$validated['order_reference'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($this->shouldReduceStock($validated['order_status'])) {
                $this->reduceStock($onlineOrderId, $saleId, $lineItems);
            }

            ActivityLog::record('create', 'online_orders', 'Online order created.', [
                'online_order_id' => $onlineOrderId,
                'sale_id' => $saleId,
                'reference' => $validated['order_reference'],
                'token' => $orderToken->token_number,
            ]);
        });

        return redirect()->route('online-orders.index')->with('status', 'Online order created.');
    }

    public function addPayment(Request $request, int $onlineOrder): RedirectResponse
    {
        $this->authorize('online_orders.add_payment');

        $order = OnlineOrder::query()->where('id', $onlineOrder)->firstOrFail();

        if ($order->order_status === 'cancelled') {
            return back()->withErrors('Cannot add payment to cancelled order.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank,card,online,platform_payment'],
            'payment_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $amount = min((float) $validated['amount'], (float) $order->balance_amount);
        if ($amount <= 0) {
            return back()->withErrors('Payment amount must be greater than 0.');
        }

        DB::transaction(function () use ($order, $validated, $amount): void {
            $register = $this->currentRegister();

            DB::table('online_order_payments')->insert([
                'online_order_id' => $order->id,
                'register_id' => $register?->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'],
                'note' => $validated['note'] ?? null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newPaid = (float) $order->paid_amount + $amount;
            $newBalance = max(0.0, (float) $order->total - $newPaid);
            $newPaymentStatus = $newBalance <= 0.0001 ? 'paid' : 'partially_paid';

            DB::table('online_orders')->where('id', $order->id)->update([
                'paid_amount' => $newPaid,
                'balance_amount' => $newBalance,
                'payment_status' => $newPaymentStatus,
                'updated_at' => now(),
            ]);

            if ($order->sale_id) {
                DB::table('sales')->where('id', $order->sale_id)->update([
                    'paid_amount' => $newPaid,
                    'due_amount' => $newBalance,
                    'status' => $newBalance > 0 ? 'due' : 'paid',
                    'online_payment_status' => $newPaymentStatus,
                    'updated_at' => now(),
                ]);

                $salePaymentMethod = match ($validated['payment_method']) {
                    'cash' => 'cash',
                    'card' => 'card',
                    default => 'qr',
                };

                DB::table('sale_payments')->insert([
                    'sale_id' => $order->sale_id,
                    'payment_method' => $salePaymentMethod,
                    'amount' => $amount,
                    'received_amount' => $amount,
                    'change_amount' => 0,
                    'fee_amount' => 0,
                    'paid_at' => $validated['payment_date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($validated['payment_method'] !== 'cash') {
                DB::table('bank_transactions')->insert([
                    'bank_account_id' => DB::table('bank_accounts')->where('is_default', true)->value('id'),
                    'type' => 'deposit',
                    'amount' => $amount,
                    'transaction_date' => $validated['payment_date'],
                    'source_type' => 'online_order_payment',
                    'source_id' => $order->id,
                    'note' => 'Online order payment '.$order->order_reference,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            ActivityLog::record('payment', 'online_orders', 'Online order payment added.', [
                'online_order_id' => $order->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
            ]);
        });

        return back()->with('status', 'Payment added.');
    }

    public function updateStatus(Request $request, int $onlineOrder): RedirectResponse
    {
        $this->authorize('online_orders.edit');

        $validated = $request->validate([
            'order_status' => ['required', 'in:new,preparing,ready,out_for_delivery,delivered,cancelled'],
        ]);

        $order = OnlineOrder::query()->with('items')->findOrFail($onlineOrder);
        $saleId = $order->sale_id;

        DB::transaction(function () use ($validated, $order, $saleId): void {
            $newStatus = $validated['order_status'];

            if ($newStatus === 'cancelled') {
                $this->cancelOnlineOrder($order, $saleId);

                return;
            }

            if ($this->shouldReduceStock($newStatus) && ! $order->stock_reduced_at) {
                $lineItems = $order->items->map(fn ($item) => [
                    'product_id' => (int) $item->product_id,
                    'qty' => (float) $item->qty,
                ])->values()->all();

                $this->reduceStock($order->id, $saleId, $lineItems);
            }

            DB::table('online_orders')->where('id', $order->id)->update([
                'order_status' => $newStatus,
                'updated_at' => now(),
            ]);

            if ($saleId) {
                DB::table('sales')->where('id', $saleId)->update([
                    'online_order_status' => $newStatus,
                    'updated_at' => now(),
                ]);
            }

            ActivityLog::record('update', 'online_orders', 'Online order status updated.', [
                'online_order_id' => $order->id,
                'status' => $newStatus,
            ]);
        });

        return back()->with('status', 'Order status updated.');
    }

    public function printInvoice(int $onlineOrder): View
    {
        $this->authorize('online_orders.print');

        $order = OnlineOrder::query()
            ->leftJoin('online_order_sources', 'online_order_sources.id', '=', 'online_orders.online_order_source_id')
            ->leftJoin('order_tokens', 'order_tokens.id', '=', 'online_orders.order_token_id')
            ->select('online_orders.*', 'online_order_sources.name as source_name', 'order_tokens.token_number', 'order_tokens.token_date')
            ->where('online_orders.id', $onlineOrder)
            ->firstOrFail();

        $items = DB::table('online_order_items')->where('online_order_id', $onlineOrder)->get();
        $payments = DB::table('online_order_payments')->where('online_order_id', $onlineOrder)->orderBy('payment_date')->get();

        return view('pos.online-order-invoice', [
            'order' => $order,
            'items' => $items,
            'payments' => $payments,
            'currency' => $this->currencySymbol(),
        ]);
    }

    private function cancelOnlineOrder(OnlineOrder $order, ?int $saleId): void
    {
        if ($order->stock_reduced_at) {
            $items = DB::table('online_order_items')->where('online_order_id', $order->id)->get();
            foreach ($items as $item) {
                $product = DB::table('products')->where('id', $item->product_id)->lockForUpdate()->first();
                if (! $product || ! $product->maintain_stock) {
                    continue;
                }

                $newStock = (float) $product->stock_quantity + (float) $item->qty;
                DB::table('products')->where('id', $product->id)->update(['stock_quantity' => $newStock, 'updated_at' => now()]);
                DB::table('stock_movements')->insert([
                    'product_id' => $product->id,
                    'type' => 'online_order_cancel',
                    'quantity' => abs((float) $item->qty),
                    'balance_after' => $newStock,
                    'source_type' => 'online_order',
                    'source_id' => $order->id,
                    'note' => 'Cancelled '.$order->order_reference,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('expenses')
            ->where('source_type', 'online_order_commission')
            ->where('source_id', $order->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        DB::table('bank_transactions')->where('source_type', 'online_order_payment')->where('source_id', $order->id)->delete();

        DB::table('online_orders')->where('id', $order->id)->update([
            'order_status' => 'cancelled',
            'updated_at' => now(),
        ]);

        if ($saleId) {
            DB::table('sale_payments')->where('sale_id', $saleId)->delete();
            DB::table('sales')->where('id', $saleId)->update([
                'status' => 'deleted',
                'deleted_at' => now(),
                'online_order_status' => 'cancelled',
                'updated_at' => now(),
            ]);
        }

        ActivityLog::record('cancel', 'online_orders', 'Online order cancelled.', [
            'online_order_id' => $order->id,
            'reference' => $order->order_reference,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function reduceStock(int $onlineOrderId, ?int $saleId, array $lineItems): void
    {
        foreach ($lineItems as $item) {
            $product = DB::table('products')->where('id', (int) $item['product_id'])->lockForUpdate()->first();
            if (! $product || ! $product->maintain_stock) {
                continue;
            }

            if ((float) $product->stock_quantity < (float) $item['qty']) {
                abort(422, $product->name.' does not have enough stock.');
            }

            $newStock = (float) $product->stock_quantity - (float) $item['qty'];
            DB::table('products')->where('id', $product->id)->update(['stock_quantity' => $newStock, 'updated_at' => now()]);
            DB::table('stock_movements')->insert([
                'product_id' => $product->id,
                'type' => 'online_order',
                'quantity' => -abs((float) $item['qty']),
                'balance_after' => $newStock,
                'source_type' => 'online_order',
                'source_id' => $onlineOrderId,
                'note' => 'Online order stock deduction',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('online_orders')->where('id', $onlineOrderId)->update([
            'stock_reduced_at' => now(),
            'updated_at' => now(),
        ]);

        if ($saleId) {
            DB::table('sales')->where('id', $saleId)->update([
                'stock_reduced_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function shouldReduceStock(string $status): bool
    {
        return in_array($status, ['preparing', 'ready', 'out_for_delivery', 'delivered'], true);
    }

    private function currentRegister(): ?object
    {
        return DB::table('registers')->where('user_id', auth()->id())->where('status', 'open')->latest()->first();
    }

    private function currencySymbol(): string
    {
        return (string) (DB::table('settings')->where('group', 'currency')->where('key', 'symbol')->value('value') ?? 'Rs.');
    }
}
