<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function show(Request $request, string $report): View
    {
        abort_unless(array_key_exists($report, config('hotelpos.reports')), 404);
        $this->authorize("reports.{$report}.view");
        [$from, $to] = $this->dateRange($request);
        $accountFlow = $this->accountFlow($request);
        $categoryId = $request->integer('category_id') ?: null;
        $tAccount = $this->tAccountFilter($request);

        return view('reports.show', [
            'report' => $report,
            'title' => $this->title($report, $accountFlow),
            'from' => $from,
            'to' => $to,
            'rows' => $this->rows($report, $from, $to, $accountFlow, $categoryId, $tAccount),
            'totals' => $this->totals($report, $from, $to, $accountFlow, $categoryId, $tAccount),
            'dailySummary' => $this->dailySummary($report, $from, $to, $accountFlow, $categoryId),
            'accountFlow' => $accountFlow,
            'tAccount' => $tAccount,
            'tAccounts' => $report === 't-accounts' ? $this->tAccountOptions() : [],
            'categoryId' => $categoryId,
            'categories' => DB::table('categories')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'bankAccounts' => $report === 'cash-flow' ? $this->bankAccountsWithBalances() : collect(),
            'cashInHand' => $report === 'cash-flow' ? $this->cashBalance() : 0.0,
            'defaultBankBalance' => $report === 'cash-flow' ? $this->defaultBankBalance() : 0.0,
        ]);
    }

    public function export(Request $request, string $report, string $format): mixed
    {
        abort_unless(array_key_exists($report, config('hotelpos.reports')), 404);
        $this->authorize("reports.{$report}.export");
        abort_unless($report === 't-accounts', 404);

        [$from, $to] = $this->dateRange($request);
        $rows = $this->tAccountRows($from, $to, $this->tAccountFilter($request), $request->string('transaction')->toString() ?: null);
        $title = $request->filled('transaction') ? 'T Account Transaction' : 'T Accounts';
        $filename = str($title)->slug('-').'-'.now()->format('Ymd-His');

        if ($format === 'excel') {
            return response()->streamDownload(function () use ($rows, $title, $from, $to): void {
                echo view('reports.exports.t-accounts-excel', [
                    'title' => $title,
                    'from' => $from,
                    'to' => $to,
                    'rows' => $rows,
                ])->render();
            }, "{$filename}.xls", [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            ]);
        }

        return response($this->tAccountPdf($title, $rows, $from, $to), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        return match ($request->string('range', 'today')->toString()) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'all_time' => [Carbon::create(2000, 1, 1)->startOfDay(), now()->endOfDay()],
            'custom' => [Carbon::parse($request->input('from', today()))->startOfDay(), Carbon::parse($request->input('to', today()))->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function rows(string $report, Carbon $from, Carbon $to, string $accountFlow = 'all', ?int $categoryId = null, string $tAccount = 'all'): mixed
    {
        return match ($report) {
            'sales', 'due-bills' => DB::table('sales')->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
                ->selectRaw('sales.invoice_no as reference, customers.name as party, sales.sale_date as date, sales.total, sales.paid_amount, sales.due_amount, sales.profit')
                ->whereNull('sales.deleted_at')->whereBetween('sales.sale_date', [$from, $to])
                ->tap(fn ($query) => $this->applySalesCategory($query, $categoryId))
                ->when($report === 'due-bills', fn ($query) => $query->where('sales.due_amount', '>', 0))
                ->latest('sales.sale_date')->paginate(25)->withQueryString(),
            'profit-loss' => DB::table('sales')
                ->selectRaw('sales.invoice_no as reference, sales.order_channel as party, sales.sale_date as date, sales.total, sales.profit, sales.due_amount')
                ->whereNull('sales.deleted_at')
                ->whereBetween('sales.sale_date', [$from, $to])
                ->tap(fn ($query) => $this->applySalesCategory($query, $categoryId))
                ->latest('sales.sale_date')
                ->paginate(25)
                ->withQueryString(),
            'purchases', 'supplier-due' => DB::table('purchases')->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
                ->selectRaw('purchases.reference_no as reference, suppliers.name as party, purchases.purchase_date as date, purchases.grand_total as total, purchases.paid_amount, purchases.due_amount')
                ->whereNull('purchases.deleted_at')->whereBetween('purchases.purchase_date', [$from, $to])
                ->when($report === 'supplier-due', fn ($query) => $query->where('purchases.due_amount', '>', 0))
                ->latest('purchases.purchase_date')->paginate(25)->withQueryString(),
            'customer-due' => DB::table('sales')->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
                ->selectRaw('coalesce(customers.name, "Walk-in Customer") as reference, count(sales.id) as party, max(sales.sale_date) as date, sum(sales.total) as total, sum(sales.paid_amount) as paid_amount, sum(sales.due_amount) as due_amount')
                ->whereNull('sales.deleted_at')
                ->whereBetween('sales.sale_date', [$from, $to])
                ->tap(fn ($query) => $this->applySalesCategory($query, $categoryId))
                ->where('sales.due_amount', '>', 0)
                ->groupBy('customers.name')
                ->orderByRaw('max(sales.sale_date) desc')
                ->paginate(25)
                ->withQueryString(),
            'expenses', 'card-fees' => DB::table('expenses')->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->selectRaw('expense_categories.name as reference, expenses.payment_method as party, expenses.expense_date as date, expenses.amount as total, expenses.note')
                ->whereNull('expenses.deleted_at')->whereBetween('expenses.expense_date', [$from, $to])
                ->when($report === 'card-fees', fn ($query) => $query->where('expense_categories.name', 'Card Fee'))
                ->latest('expenses.expense_date')->paginate(25)->withQueryString(),
            'receive' => DB::table('cash_ins')->leftJoin('users', 'users.id', '=', 'cash_ins.user_id')
                ->selectRaw('concat("Cash In #", cash_ins.id) as reference, users.name as party, cash_ins.movement_date as date, cash_ins.amount as total, cash_ins.note')
                ->whereNull('cash_ins.deleted_at')
                ->whereBetween('cash_ins.movement_date', [$from, $to])
                ->latest('cash_ins.movement_date')
                ->paginate(25)
                ->withQueryString()
                ->through(function (object $row): object {
                    $row->party = User::visibleName($row->party ?? null, 'Cash');

                    return $row;
                }),
            'debit' => DB::table('cash_outs')->leftJoin('users', 'users.id', '=', 'cash_outs.user_id')
                ->selectRaw('concat("Cash Out #", cash_outs.id) as reference, users.name as party, cash_outs.movement_date as date, cash_outs.amount as total, cash_outs.note')
                ->whereNull('cash_outs.deleted_at')
                ->whereBetween('cash_outs.movement_date', [$from, $to])
                ->latest('cash_outs.movement_date')
                ->paginate(25)
                ->withQueryString()
                ->through(function (object $row): object {
                    $row->party = User::visibleName($row->party ?? null, 'Cash');

                    return $row;
                }),
            'cash-flow' => $this->cashFlowRows($from, $to, $accountFlow),
            't-accounts' => $this->paginatedTAccountRows($from, $to, $tAccount),
            'stock' => DB::table('products')->selectRaw('barcode as reference, name as party, updated_at as date, stock_quantity as total, alert_quantity as due_amount')
                ->whereNull('deleted_at')
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->paginate(25)
                ->withQueryString(),
            'damage-write-offs' => DB::table('damage_write_offs')->leftJoin('products', 'products.id', '=', 'damage_write_offs.product_id')
                ->selectRaw('products.name as reference, damage_write_offs.reason as party, damage_write_offs.write_off_date as date, damage_write_offs.cost_amount as total, damage_write_offs.quantity')
                ->whereNull('damage_write_offs.deleted_at')
                ->whereBetween('damage_write_offs.write_off_date', [$from, $to])
                ->when($categoryId, fn ($query) => $query->where('products.category_id', $categoryId))
                ->paginate(25)
                ->withQueryString(),
            'waiters' => DB::table('waiters')
                ->leftJoin('waiter_incentives', function ($join) use ($from, $to): void {
                    $join->on('waiter_incentives.waiter_id', '=', 'waiters.id')
                        ->whereBetween('waiter_incentives.earned_at', [$from, $to]);
                })
                ->selectRaw('waiters.name as reference, count(waiter_incentives.id) as party, max(waiter_incentives.earned_at) as date, coalesce(sum(waiter_incentives.sale_amount), 0) as total, coalesce(sum(waiter_incentives.amount), 0) as paid_amount, 0 as due_amount')
                ->whereNull('waiters.deleted_at')
                ->groupBy('waiters.id', 'waiters.name')
                ->orderBy('waiters.name')
                ->paginate(25)
                ->withQueryString(),
            'product-sales' => DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
                ->selectRaw('sale_items.product_name as reference, sum(sale_items.quantity) as party, max(sales.sale_date) as date, sum(sale_items.line_total) as total, sum(sale_items.profit) as profit')
                ->whereNull('sales.deleted_at')
                ->whereBetween('sales.sale_date', [$from, $to])
                ->when($categoryId, fn ($query) => $query->where('products.category_id', $categoryId))
                ->groupBy('sale_items.product_name')
                ->paginate(25)
                ->withQueryString(),
            'payment-methods', 'qr-payments' => DB::table('sale_payments')->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->selectRaw('sale_payments.payment_method as reference, count(*) as party, max(sale_payments.paid_at) as date, sum(sale_payments.amount) as total, sum(sale_payments.fee_amount) as due_amount')
                ->whereNull('sales.deleted_at')
                ->whereBetween('sale_payments.paid_at', [$from, $to])
                ->when($report === 'qr-payments', fn ($query) => $query->where('payment_method', 'qr'))
                ->groupBy('sale_payments.payment_method')->paginate(25)->withQueryString(),
            'register-closing' => DB::table('register_closings')->join('registers', 'registers.id', '=', 'register_closings.register_id')->join('users', 'users.id', '=', 'registers.user_id')
                ->selectRaw('users.name as reference, registers.opened_at as party, register_closings.created_at as date, register_closings.expected_cash as total, register_closings.actual_cash as paid_amount, register_closings.difference as due_amount')
                ->whereBetween('register_closings.created_at', [$from, $to])
                ->paginate(25)
                ->withQueryString()
                ->through(function (object $row): object {
                    $row->reference = User::visibleName($row->reference ?? null, 'Cashier');

                    return $row;
                }),
            'online-orders' => DB::table('online_orders')
                ->leftJoin('online_order_sources', 'online_order_sources.id', '=', 'online_orders.online_order_source_id')
                ->selectRaw('online_orders.order_reference as reference, coalesce(online_order_sources.name, "Unknown") as party, online_orders.created_at as date, online_orders.total as total, online_orders.paid_amount as paid_amount, online_orders.balance_amount as due_amount, online_orders.commission_amount as profit')
                ->whereNull('online_orders.deleted_at')
                ->whereBetween('online_orders.created_at', [$from, $to])
                ->latest('online_orders.created_at')
                ->paginate(25)
                ->withQueryString(),
            default => $this->emptyRows(),
        };
    }

    /**
     * @return array<string, float>
     */
    private function totals(string $report, Carbon $from, Carbon $to, string $accountFlow = 'all', ?int $categoryId = null, string $tAccount = 'all'): array
    {
        $sales = DB::table('sales')->whereNull('deleted_at')->whereBetween('sale_date', [$from, $to]);
        $this->applySalesCategory($sales, $categoryId);
        $purchases = DB::table('purchases')->whereNull('deleted_at')->whereBetween('purchase_date', [$from, $to]);
        $expenses = DB::table('expenses')->whereNull('deleted_at')->whereBetween('expense_date', [$from, $to]);
        $onlineOrders = DB::table('online_orders')->whereNull('deleted_at')->whereBetween('created_at', [$from, $to]);

        return match ($report) {
            'sales' => [
                'total sales' => (float) (clone $sales)->sum('total'),
                'paid' => (float) (clone $sales)->sum('paid_amount'),
                'due' => (float) (clone $sales)->sum('due_amount'),
                'invoices' => (float) (clone $sales)->count(),
            ],
            'profit-loss' => [
                'sales' => (float) (clone $sales)->sum('total'),
                'profit' => (float) (clone $sales)->sum('profit'),
                'expenses' => (float) (clone $expenses)->sum('amount'),
                'net profit' => (float) (clone $sales)->sum('profit') - (float) (clone $expenses)->sum('amount'),
            ],
            'purchases' => [
                'purchase total' => (float) (clone $purchases)->sum('grand_total'),
                'paid' => (float) (clone $purchases)->sum('paid_amount'),
                'discount' => (float) (clone $purchases)->sum('discount_total'),
                'due' => (float) (clone $purchases)->sum('due_amount'),
            ],
            'supplier-due' => [
                'purchase total' => (float) (clone $purchases)->sum('grand_total'),
                'paid' => (float) (clone $purchases)->sum('paid_amount'),
                'supplier due' => (float) (clone $purchases)->sum('due_amount'),
                'due bills' => (float) (clone $purchases)->where('due_amount', '>', 0)->count(),
            ],
            'expenses' => [
                'expenses' => (float) (clone $expenses)->sum('amount'),
                'records' => (float) (clone $expenses)->count(),
                'cash paid' => (float) (clone $expenses)->where('payment_method', 'cash')->sum('amount'),
                'bank paid' => (float) (clone $expenses)->where('payment_method', 'bank')->sum('amount'),
            ],
            'card-fees' => [
                'card fees' => (float) DB::table('expenses')->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->whereNull('expenses.deleted_at')->whereBetween('expenses.expense_date', [$from, $to])->where('expense_categories.name', 'Card Fee')->sum('expenses.amount'),
                'records' => (float) DB::table('expenses')->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->whereNull('expenses.deleted_at')->whereBetween('expenses.expense_date', [$from, $to])->where('expense_categories.name', 'Card Fee')->count(),
                'expenses' => (float) (clone $expenses)->sum('amount'),
                'due' => 0.0,
            ],
            'stock' => [
                'products' => (float) DB::table('products')->whereNull('deleted_at')->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))->count(),
                'stock quantity' => (float) DB::table('products')->whereNull('deleted_at')->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))->sum('stock_quantity'),
                'low stock' => (float) DB::table('products')->whereNull('deleted_at')->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))->whereColumn('stock_quantity', '<=', 'alert_quantity')->count(),
                'stock value' => (float) DB::table('products')->whereNull('deleted_at')->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))->selectRaw('sum(stock_quantity * cost_price) as value')->value('value'),
            ],
            'damage-write-offs' => [
                'write off cost' => (float) DB::table('damage_write_offs')->whereNull('deleted_at')->whereBetween('write_off_date', [$from, $to])->sum('cost_amount'),
                'quantity' => (float) DB::table('damage_write_offs')->whereNull('deleted_at')->whereBetween('write_off_date', [$from, $to])->sum('quantity'),
                'records' => (float) DB::table('damage_write_offs')->whereNull('deleted_at')->whereBetween('write_off_date', [$from, $to])->count(),
                'due' => 0.0,
            ],
            'receive' => [
                'cash in' => (float) DB::table('cash_ins')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount'),
                'records' => (float) DB::table('cash_ins')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->count(),
                'cash out' => (float) DB::table('cash_outs')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount'),
                'net cash' => (float) DB::table('cash_ins')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount') - (float) DB::table('cash_outs')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount'),
            ],
            'debit' => [
                'cash out' => (float) DB::table('cash_outs')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount'),
                'records' => (float) DB::table('cash_outs')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->count(),
                'cash in' => (float) DB::table('cash_ins')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount'),
                'net cash' => (float) DB::table('cash_ins')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount') - (float) DB::table('cash_outs')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount'),
            ],
            'due-bills', 'customer-due' => [
                'sales' => (float) (clone $sales)->sum('total'),
                'paid' => (float) (clone $sales)->sum('paid_amount'),
                'customer due' => (float) (clone $sales)->sum('due_amount'),
                'due bills' => (float) (clone $sales)->where('due_amount', '>', 0)->count(),
            ],
            'waiters' => [
                'total incentive' => (float) DB::table('waiter_incentives')->whereBetween('earned_at', [$from, $to])->sum('amount'),
                'active waiters' => (float) DB::table('waiters')->whereNull('deleted_at')->where('is_active', true)->count(),
                'sales count' => (float) DB::table('waiter_incentives')->whereBetween('earned_at', [$from, $to])->count(),
                'sales amount' => (float) DB::table('waiter_incentives')->whereBetween('earned_at', [$from, $to])->sum('sale_amount'),
            ],
            'product-sales' => [
                'sales' => (float) $this->productSalesQuery($from, $to, $categoryId)->sum('sale_items.line_total'),
                'profit' => (float) $this->productSalesQuery($from, $to, $categoryId)->sum('sale_items.profit'),
                'quantity' => (float) $this->productSalesQuery($from, $to, $categoryId)->sum('sale_items.quantity'),
                'products' => (float) $this->productSalesQuery($from, $to, $categoryId)->distinct()->count('sale_items.product_id'),
            ],
            'payment-methods', 'qr-payments' => [
                'payments' => (float) DB::table('sale_payments')->join('sales', 'sales.id', '=', 'sale_payments.sale_id')->whereNull('sales.deleted_at')->whereBetween('paid_at', [$from, $to])->when($report === 'qr-payments', fn ($query) => $query->where('payment_method', 'qr'))->sum('amount'),
                'fees' => (float) DB::table('sale_payments')->join('sales', 'sales.id', '=', 'sale_payments.sale_id')->whereNull('sales.deleted_at')->whereBetween('paid_at', [$from, $to])->when($report === 'qr-payments', fn ($query) => $query->where('payment_method', 'qr'))->sum('fee_amount'),
                'transactions' => (float) DB::table('sale_payments')->join('sales', 'sales.id', '=', 'sale_payments.sale_id')->whereNull('sales.deleted_at')->whereBetween('paid_at', [$from, $to])->when($report === 'qr-payments', fn ($query) => $query->where('payment_method', 'qr'))->count(),
                'due' => 0.0,
            ],
            'register-closing' => [
                'expected cash' => (float) DB::table('register_closings')->whereBetween('created_at', [$from, $to])->sum('expected_cash'),
                'actual cash' => (float) DB::table('register_closings')->whereBetween('created_at', [$from, $to])->sum('actual_cash'),
                'difference' => (float) DB::table('register_closings')->whereBetween('created_at', [$from, $to])->sum('difference'),
                'bank amount' => (float) DB::table('register_closings')->whereBetween('created_at', [$from, $to])->sum('bank_amount'),
            ],
            'online-orders' => [
                'sales' => (float) (clone $onlineOrders)->sum('total'),
                'profit' => (float) ((clone $onlineOrders)->sum('total') - (clone $onlineOrders)->sum('commission_amount')),
                'expenses' => (float) (clone $onlineOrders)->sum('commission_amount'),
                'due' => (float) (clone $onlineOrders)->sum('balance_amount'),
            ],
            'cash-flow' => $this->cashFlowTotals($from, $to, $accountFlow),
            't-accounts' => $this->tAccountTotals($from, $to, $tAccount),
            default => [
                'sales' => (float) (clone $sales)->sum('total'),
                'profit' => (float) (clone $sales)->sum('profit'),
                'expenses' => (float) (clone $expenses)->sum('amount'),
                'due' => (float) (clone $sales)->sum('due_amount'),
            ],
        };
    }

    private function accountFlow(Request $request): string
    {
        $flow = $request->string('account_flow', 'all')->toString();

        return in_array($flow, ['all', 'cash-in', 'cash-out', 'balance', 'bank'], true) ? $flow : 'all';
    }

    private function title(string $report, string $accountFlow): string
    {
        if ($report !== 'cash-flow') {
            return config("hotelpos.reports.{$report}");
        }

        return match ($accountFlow) {
            'cash-in' => 'Cash In',
            'cash-out' => 'Cash Out',
            'balance' => 'Cash Balance',
            'bank' => 'Bank Transfers',
            default => 'Daily Cash Closing',
        };
    }

    private function dailySummary(string $report, Carbon $from, Carbon $to, string $accountFlow = 'all', ?int $categoryId = null): mixed
    {
        return match ($report) {
            'sales', 'due-bills', 'profit-loss' => tap(
                DB::table('sales')
                    ->selectRaw('date(sale_date) as date, count(*) as records, sum(total) as total')
                    ->whereNull('deleted_at')
                    ->whereBetween('sale_date', [$from, $to])
                    ->when($report === 'due-bills', fn ($query) => $query->where('due_amount', '>', 0)),
                fn ($query) => $this->applySalesCategory($query, $categoryId)
            )
                ->groupByRaw('date(sale_date)')
                ->orderByDesc('date')
                ->get(),
            'purchases', 'supplier-due' => DB::table('purchases')
                ->selectRaw('date(purchase_date) as date, count(*) as records, sum(grand_total) as total')
                ->whereNull('deleted_at')
                ->whereBetween('purchase_date', [$from, $to])
                ->when($report === 'supplier-due', fn ($query) => $query->where('due_amount', '>', 0))
                ->groupByRaw('date(purchase_date)')
                ->orderByDesc('date')
                ->get(),
            'expenses', 'card-fees' => DB::table('expenses')
                ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->selectRaw('date(expenses.expense_date) as date, count(*) as records, sum(expenses.amount) as total')
                ->whereNull('expenses.deleted_at')
                ->whereBetween('expenses.expense_date', [$from, $to])
                ->when($report === 'card-fees', fn ($query) => $query->where('expense_categories.name', 'Card Fee'))
                ->groupByRaw('date(expenses.expense_date)')
                ->orderByDesc('date')
                ->get(),
            'product-sales' => $this->productSalesQuery($from, $to, $categoryId)
                ->selectRaw('date(sales.sale_date) as date, count(distinct sales.id) as records, sum(sale_items.line_total) as total')
                ->groupByRaw('date(sales.sale_date)')
                ->orderByDesc('date')
                ->get(),
            'payment-methods', 'qr-payments' => DB::table('sale_payments')
                ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->selectRaw('date(sale_payments.paid_at) as date, count(*) as records, sum(sale_payments.amount) as total')
                ->whereNull('sales.deleted_at')
                ->whereBetween('sale_payments.paid_at', [$from, $to])
                ->when($report === 'qr-payments', fn ($query) => $query->where('sale_payments.payment_method', 'qr'))
                ->groupByRaw('date(sale_payments.paid_at)')
                ->orderByDesc('date')
                ->get(),
            'cash-flow' => $this->cashFlowDailySummary($from, $to, $accountFlow),
            default => collect(),
        };
    }

    private function applySalesCategory(mixed $query, ?int $categoryId): void
    {
        if (! $categoryId) {
            return;
        }

        $query->whereExists(function ($subQuery) use ($categoryId): void {
            $subQuery
                ->selectRaw('1')
                ->from('sale_items')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereColumn('sale_items.sale_id', 'sales.id')
                ->where('products.category_id', $categoryId);
        });
    }

    private function productSalesQuery(Carbon $from, Carbon $to, ?int $categoryId): mixed
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->when($categoryId, fn ($query) => $query->where('products.category_id', $categoryId));
    }

    private function cashFlowDailySummary(Carbon $from, Carbon $to, string $accountFlow): mixed
    {
        $rows = $this->cashFlowRows($from, $to, $accountFlow)
            ->getCollection()
            ->groupBy(fn ($row) => Carbon::parse($row->date)->toDateString())
            ->map(fn ($rows, $date) => (object) [
                'date' => $date,
                'records' => $rows->count(),
                'total' => $rows->sum('inflow') - $rows->sum('outflow'),
            ])
            ->values();

        return $rows;
    }

    private function cashFlowRows(Carbon $from, Carbon $to, string $accountFlow): mixed
    {
        $queries = [];

        if (in_array($accountFlow, ['all', 'cash-in', 'balance', 'bank'], true)) {
            $salePayments = DB::table('sale_payments')
                ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
                ->selectRaw('"Cash In" as flow_type, sales.invoice_no as reference, sale_payments.payment_method as party, sale_payments.paid_at as date, sale_payments.amount as inflow, 0 as outflow, coalesce(customers.name, "Walk-in Customer") as note')
                ->whereNull('sales.deleted_at')
                ->whereBetween('sale_payments.paid_at', [$from, $to])
                ->when($accountFlow === 'bank', fn ($query) => $query->where('sale_payments.payment_method', '!=', 'cash'));

            $queries[] = $salePayments;

            if (in_array($accountFlow, ['all', 'cash-in', 'balance'], true)) {
                $queries[] = DB::table('registers')
                    ->leftJoin('users', 'users.id', '=', 'registers.user_id')
                    ->selectRaw('"Cash In" as flow_type, concat("REG", registers.id) as reference, coalesce(users.name, "Cashier") as party, registers.opened_at as date, registers.opening_cash as inflow, 0 as outflow, coalesce(registers.opening_note, "Register opening") as note')
                    ->whereBetween('registers.opened_at', [$from, $to]);
            }

            if ($accountFlow !== 'bank') {
                $queries[] = DB::table('cash_ins')
                    ->leftJoin('users', 'users.id', '=', 'cash_ins.user_id')
                    ->selectRaw('"Cash In" as flow_type, concat("Cash In #", cash_ins.id) as reference, "cash" as party, cash_ins.movement_date as date, cash_ins.amount as inflow, 0 as outflow, coalesce(cash_ins.note, "Cash in") as note')
                    ->whereNull('cash_ins.deleted_at')
                    ->whereBetween('cash_ins.movement_date', [$from, $to]);
            }
        }

        if (in_array($accountFlow, ['all', 'bank'], true)) {
            $queries[] = DB::table('bank_transactions')
                ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'bank_transactions.bank_account_id')
                ->selectRaw('"Bank Transfer" as flow_type, concat("Bank Tx #", bank_transactions.id) as reference, coalesce(bank_accounts.name, "Bank") as party, bank_transactions.transaction_date as date, case when bank_transactions.type in ("deposit","qr_payment","card_payment","cash_to_bank","bank_transfer_in") then bank_transactions.amount else 0 end as inflow, case when bank_transactions.type in ("deposit","qr_payment","card_payment","cash_to_bank","bank_transfer_in") then 0 else bank_transactions.amount end as outflow, coalesce(bank_transactions.note, bank_transactions.type) as note')
                ->whereBetween('bank_transactions.transaction_date', [$from, $to])
                ->whereIn('bank_transactions.type', ['cash_to_bank', 'bank_to_cash', 'bank_transfer_in', 'bank_transfer_out', 'bank_expense']);
        }

        if (in_array($accountFlow, ['all', 'cash-out', 'balance'], true)) {
            $queries[] = DB::table('expenses')
                ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->selectRaw('"Cash Out" as flow_type, coalesce(expense_categories.name, "Expense") as reference, expenses.payment_method as party, expenses.expense_date as date, 0 as inflow, expenses.amount as outflow, coalesce(expenses.note, "-") as note')
                ->whereNull('expenses.deleted_at')
                ->whereBetween('expenses.expense_date', [$from, $to]);
        }

        if ($queries === []) {
            return $this->emptyCashFlowRows();
        }

        $query = array_shift($queries);
        foreach ($queries as $union) {
            $query->unionAll($union);
        }

        return DB::query()
            ->fromSub($query, 'account_movements')
            ->orderByDesc('date')
            ->paginate(25)
            ->withQueryString()
            ->through(function (object $row): object {
                $row->party = User::visibleName($row->party ?? null, 'Cashier');

                return $row;
            });
    }

    /**
     * @return array<string, float>
     */
    private function cashFlowTotals(Carbon $from, Carbon $to, string $accountFlow): array
    {
        $salePayments = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sale_payments.paid_at', [$from, $to]);

        $cashReceived = (float) (clone $salePayments)->where('sale_payments.payment_method', 'cash')->sum('sale_payments.amount');
        $bankTransfers = (float) (clone $salePayments)->where('sale_payments.payment_method', '!=', 'cash')->sum('sale_payments.amount');
        $manualCashIn = (float) DB::table('cash_ins')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->sum('amount');
        $registerOpenings = in_array($accountFlow, ['all', 'cash-in', 'balance'], true)
            ? (float) DB::table('registers')->whereBetween('opened_at', [$from, $to])->sum('opening_cash')
            : 0.0;
        $expenses = (float) DB::table('expenses')->whereNull('deleted_at')->whereBetween('expense_date', [$from, $to])->sum('amount');
        $cashToBank = (float) DB::table('bank_transactions')->whereBetween('transaction_date', [$from, $to])->where('type', 'cash_to_bank')->sum('amount');
        $bankTransferIn = (float) DB::table('bank_transactions')->whereBetween('transaction_date', [$from, $to])->where('type', 'bank_transfer_in')->sum('amount');
        $bankTransferOut = (float) DB::table('bank_transactions')->whereBetween('transaction_date', [$from, $to])->where('type', 'bank_transfer_out')->sum('amount');
        $bankToCash = (float) DB::table('bank_transactions')->whereBetween('transaction_date', [$from, $to])->where('type', 'bank_to_cash')->sum('amount');
        $bankExpense = (float) DB::table('bank_transactions')->whereBetween('transaction_date', [$from, $to])->where('type', 'bank_expense')->sum('amount');

        $otherCashIn = $manualCashIn + $registerOpenings;
        $inflow = $cashReceived + $bankTransfers + ($accountFlow === 'bank' ? 0.0 : $otherCashIn);
        $openingBalance = $accountFlow === 'balance' ? $this->cashOpeningBalance($from) : 0.0;
        $transactions = (float) $this->cashFlowTransactionCount($from, $to, $accountFlow);

        return match ($accountFlow) {
            'cash-in' => [
                'cash received' => $cashReceived,
                'bank transfers' => $bankTransfers,
                'other cash in' => $otherCashIn,
                'total inflow' => $inflow,
            ],
            'cash-out' => [
                'expenses' => $expenses,
                'transactions' => $transactions,
                'cash paid' => (float) DB::table('expenses')->whereNull('deleted_at')->whereBetween('expense_date', [$from, $to])->where('payment_method', 'cash')->sum('amount'),
                'bank paid' => (float) DB::table('expenses')->whereNull('deleted_at')->whereBetween('expense_date', [$from, $to])->where('payment_method', '!=', 'cash')->sum('amount'),
            ],
            'bank' => [
                'bank transfers' => $bankTransfers + $cashToBank + $bankTransferIn,
                'bank out' => $bankTransferOut + $bankToCash + $bankExpense,
                'transactions' => $transactions,
                'fees' => (float) (clone $salePayments)->where('sale_payments.payment_method', '!=', 'cash')->sum('sale_payments.fee_amount'),
                'net bank in' => $bankTransfers + $cashToBank + $bankTransferIn - $bankTransferOut - $bankToCash - $bankExpense,
            ],
            default => [
                'cash received' => $cashReceived,
                'bank transfers' => $bankTransfers,
                'total inflow' => $inflow + $openingBalance,
                'expenses' => $expenses,
                'balance' => $openingBalance + $inflow - $expenses,
            ],
        };
    }

    private function cashOpeningBalance(Carbon $from): float
    {
        $registerOpening = (float) DB::table('registers')->where('opened_at', '<', $from)->sum('opening_cash');
        $cashSales = (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sale_payments.payment_method', 'cash')
            ->where('sale_payments.paid_at', '<', $from)
            ->sum('sale_payments.amount');
        $cashIns = (float) DB::table('cash_ins')->whereNull('deleted_at')->where('movement_date', '<', $from)->sum('amount');
        $cashOuts = (float) DB::table('cash_outs')->whereNull('deleted_at')->where('movement_date', '<', $from)->sum('amount');
        $cashExpenses = (float) DB::table('expenses')
            ->whereNull('deleted_at')
            ->where('payment_method', 'cash')
            ->where('expense_date', '<', $from)
            ->sum('amount');

        return $registerOpening + $cashSales + $cashIns - $cashOuts - $cashExpenses;
    }

    private function cashFlowTransactionCount(Carbon $from, Carbon $to, string $accountFlow): int
    {
        $salePayments = in_array($accountFlow, ['all', 'cash-in', 'balance', 'bank'], true)
            ? DB::table('sale_payments')
                ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->whereNull('sales.deleted_at')
                ->whereBetween('sale_payments.paid_at', [$from, $to])
                ->when($accountFlow === 'bank', fn ($query) => $query->where('sale_payments.payment_method', '!=', 'cash'))
                ->count()
            : 0;

        $cashIns = in_array($accountFlow, ['all', 'cash-in', 'balance'], true)
            ? DB::table('cash_ins')->whereNull('deleted_at')->whereBetween('movement_date', [$from, $to])->count()
            : 0;
        $expenses = in_array($accountFlow, ['cash-out', 'all', 'balance'], true)
            ? DB::table('expenses')->whereNull('deleted_at')->whereBetween('expense_date', [$from, $to])->count()
            : 0;
        $bankTransactions = in_array($accountFlow, ['all', 'bank'], true)
            ? DB::table('bank_transactions')->whereBetween('transaction_date', [$from, $to])->whereIn('type', ['cash_to_bank', 'bank_to_cash', 'bank_transfer_in', 'bank_transfer_out', 'bank_expense'])->count()
            : 0;

        return (int) $salePayments + (int) $cashIns + (int) $expenses + (int) $bankTransactions;
    }

    /**
     * @return array<string, string>
     */
    private function tAccountOptions(): array
    {
        return [
            'all' => 'All T Accounts',
            'Cash' => 'Cash',
            'Bank' => 'Bank',
            'Sales Revenue' => 'Sales Revenue',
            'Accounts Receivable' => 'Accounts Receivable',
            'Purchases / Inventory' => 'Purchases / Inventory',
            'Accounts Payable' => 'Accounts Payable',
            'Expenses' => 'Expenses',
            'Cash Adjustment' => 'Cash Adjustment',
            'Bank Transfer' => 'Bank Transfer',
        ];
    }

    private function tAccountFilter(Request $request): string
    {
        $account = $request->string('t_account', 'all')->toString();

        return array_key_exists($account, $this->tAccountOptions()) ? $account : 'all';
    }

    private function paginatedTAccountRows(Carbon $from, Carbon $to, string $account): mixed
    {
        $rows = $this->tAccountRows($from, $to, $account);
        $page = max(1, request()->integer('page', 1));
        $perPage = 25;

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function tAccountRows(Carbon $from, Carbon $to, string $account = 'all', ?string $transaction = null): Collection
    {
        $rows = collect()
            ->merge($this->salePaymentTAccounts($from, $to))
            ->merge($this->saleDueTAccounts($from, $to))
            ->merge($this->purchaseTAccounts($from, $to))
            ->merge($this->purchasePaymentTAccounts($from, $to))
            ->merge($this->expenseTAccounts($from, $to))
            ->merge($this->cashInTAccounts($from, $to))
            ->merge($this->cashOutTAccounts($from, $to))
            ->merge($this->bankTransferTAccounts($from, $to))
            ->sortByDesc('date')
            ->values();

        if ($account !== 'all') {
            $rows = $rows
                ->filter(fn (array $row): bool => $row['debit_account'] === $account || $row['credit_account'] === $account)
                ->values();
        }

        if ($transaction) {
            $rows = $rows
                ->filter(fn (array $row): bool => $row['transaction_id'] === $transaction)
                ->values();
        }

        return $rows;
    }

    private function tAccountTotals(Carbon $from, Carbon $to, string $account): array
    {
        $rows = $this->tAccountRows($from, $to, $account);

        return [
            'transactions' => (float) $rows->count(),
            'debits' => (float) $rows->sum('debit_amount'),
            'credits' => (float) $rows->sum('credit_amount'),
            'difference' => (float) ($rows->sum('debit_amount') - $rows->sum('credit_amount')),
        ];
    }

    private function tRow(string $transactionId, string $date, string $reference, string $description, string $debitAccount, string $creditAccount, float $amount, string $source): array
    {
        return [
            'transaction_id' => $transactionId,
            'date' => $date,
            'reference' => $reference,
            'description' => $description,
            'debit_account' => $debitAccount,
            'credit_account' => $creditAccount,
            'debit_amount' => $amount,
            'credit_amount' => $amount,
            'source' => $source,
        ];
    }

    private function salePaymentTAccounts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sale_payments.paid_at', [$from, $to])
            ->get([
                'sale_payments.id',
                'sale_payments.payment_method',
                'sale_payments.amount',
                'sale_payments.paid_at',
                'sales.invoice_no',
                'customers.name as customer_name',
            ])
            ->map(fn (object $payment): array => $this->tRow(
                "sale_payment:{$payment->id}",
                (string) $payment->paid_at,
                $payment->invoice_no ?? "Sale Payment #{$payment->id}",
                'Sale payment'.($payment->customer_name ? " - {$payment->customer_name}" : ''),
                $payment->payment_method === 'cash' ? 'Cash' : 'Bank',
                'Sales Revenue',
                (float) $payment->amount,
                'Sale Payment'
            ));
    }

    private function saleDueTAccounts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->where('sales.due_amount', '>', 0)
            ->get(['sales.id', 'sales.invoice_no', 'sales.sale_date', 'sales.due_amount', 'customers.name as customer_name'])
            ->map(fn (object $sale): array => $this->tRow(
                "sale_due:{$sale->id}",
                (string) $sale->sale_date,
                $sale->invoice_no ?? "Sale #{$sale->id}",
                'Due sale'.($sale->customer_name ? " - {$sale->customer_name}" : ''),
                'Accounts Receivable',
                'Sales Revenue',
                (float) $sale->due_amount,
                'Due Sale'
            ));
    }

    private function purchaseTAccounts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('purchases')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereNull('purchases.deleted_at')
            ->whereBetween('purchases.purchase_date', [$from, $to])
            ->get(['purchases.id', 'purchases.reference_no', 'purchases.purchase_date', 'purchases.grand_total', 'suppliers.name as supplier_name'])
            ->map(fn (object $purchase): array => $this->tRow(
                "purchase:{$purchase->id}",
                (string) $purchase->purchase_date,
                $purchase->reference_no ?? "Purchase #{$purchase->id}",
                'Purchase'.($purchase->supplier_name ? " - {$purchase->supplier_name}" : ''),
                'Purchases / Inventory',
                'Accounts Payable',
                (float) $purchase->grand_total,
                'Purchase'
            ));
    }

    private function purchasePaymentTAccounts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('purchase_payments')
            ->join('purchases', 'purchases.id', '=', 'purchase_payments.purchase_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereNull('purchases.deleted_at')
            ->whereBetween('purchase_payments.paid_at', [$from, $to])
            ->get(['purchase_payments.id', 'purchase_payments.payment_method', 'purchase_payments.amount', 'purchase_payments.paid_at', 'purchases.reference_no', 'suppliers.name as supplier_name'])
            ->map(fn (object $payment): array => $this->tRow(
                "purchase_payment:{$payment->id}",
                (string) $payment->paid_at,
                $payment->reference_no ?? "Purchase Payment #{$payment->id}",
                'Supplier payment'.($payment->supplier_name ? " - {$payment->supplier_name}" : ''),
                'Accounts Payable',
                $payment->payment_method === 'cash' ? 'Cash' : 'Bank',
                (float) $payment->amount,
                'Purchase Payment'
            ));
    }

    private function expenseTAccounts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('expenses')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.expense_date', [$from, $to])
            ->get(['expenses.id', 'expenses.payment_method', 'expenses.amount', 'expenses.expense_date', 'expenses.note', 'expense_categories.name as category'])
            ->map(fn (object $expense): array => $this->tRow(
                "expense:{$expense->id}",
                (string) $expense->expense_date,
                $expense->category ?? "Expense #{$expense->id}",
                $expense->note ?: 'Expense payment',
                'Expenses',
                $expense->payment_method === 'cash' ? 'Cash' : 'Bank',
                (float) $expense->amount,
                'Expense'
            ));
    }

    private function cashInTAccounts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('cash_ins')
            ->whereNull('deleted_at')
            ->whereBetween('movement_date', [$from, $to])
            ->get(['id', 'amount', 'movement_date', 'note'])
            ->map(fn (object $cashIn): array => $this->tRow(
                "cash_in:{$cashIn->id}",
                (string) $cashIn->movement_date,
                "Cash In #{$cashIn->id}",
                $cashIn->note ?: 'Cash received',
                'Cash',
                'Cash Adjustment',
                (float) $cashIn->amount,
                'Cash In'
            ));
    }

    private function cashOutTAccounts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('cash_outs')
            ->whereNull('deleted_at')
            ->whereBetween('movement_date', [$from, $to])
            ->get(['id', 'amount', 'movement_date', 'note'])
            ->map(fn (object $cashOut): array => $this->tRow(
                "cash_out:{$cashOut->id}",
                (string) $cashOut->movement_date,
                "Cash Out #{$cashOut->id}",
                $cashOut->note ?: 'Cash paid out',
                'Cash Adjustment',
                'Cash',
                (float) $cashOut->amount,
                'Cash Out'
            ));
    }

    private function bankTransferTAccounts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('bank_transactions')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'bank_transactions.bank_account_id')
            ->whereBetween('bank_transactions.transaction_date', [$from, $to])
            ->whereIn('bank_transactions.type', ['cash_to_bank', 'bank_to_cash', 'bank_transfer_in', 'bank_transfer_out'])
            ->get(['bank_transactions.id', 'bank_transactions.type', 'bank_transactions.amount', 'bank_transactions.transaction_date', 'bank_transactions.note', 'bank_accounts.name as bank_name'])
            ->map(function (object $transaction): array {
                [$debit, $credit] = match ($transaction->type) {
                    'cash_to_bank', 'bank_transfer_in' => ['Bank', $transaction->type === 'cash_to_bank' ? 'Cash' : 'Bank Transfer'],
                    'bank_to_cash' => ['Cash', 'Bank'],
                    default => ['Bank Transfer', 'Bank'],
                };

                return $this->tRow(
                    "bank_transaction:{$transaction->id}",
                    (string) $transaction->transaction_date,
                    "Bank Tx #{$transaction->id}",
                    $transaction->note ?: str($transaction->type)->replace('_', ' ')->headline().' - '.($transaction->bank_name ?? 'Bank'),
                    $debit,
                    $credit,
                    (float) $transaction->amount,
                    'Bank Transfer'
                );
            });
    }

    private function tAccountPdf(string $title, Collection $rows, Carbon $from, Carbon $to): string
    {
        $blocks = $this->tAccountPdfBlocks($rows);
        $chunks = $blocks === [] ? [[['account' => 'No Transactions', 'debits' => [], 'credits' => [], 'debit_total' => 0.0, 'credit_total' => 0.0, 'balance' => 'No transactions found.']]] : array_map(fn (array $block): array => [$block], $blocks);
        $objects = [];
        $pageObjectIds = [];
        $contentObjectIds = [];

        foreach ($chunks as $index => $chunk) {
            $pageObjectIds[] = 3 + ($index * 2);
            $contentObjectIds[] = 4 + ($index * 2);
        }

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids ['.collect($pageObjectIds)->map(fn (int $id): string => "{$id} 0 R")->implode(' ').'] /Count '.count($pageObjectIds).' >> endobj',
        ];

        $fontObjectId = 3 + (count($chunks) * 2);

        foreach ($chunks as $index => $chunk) {
            $pageId = $pageObjectIds[$index];
            $contentId = $contentObjectIds[$index];
            $content = $this->tAccountPdfPageContent($title, $from, $to, $rows->count(), $chunk, $index + 1);

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

    private function tAccountPdfPageContent(string $title, Carbon $from, Carbon $to, int $transactionCount, array $blocks, int $page): string
    {
        $content = $this->pdfTextAt(40, 560, "{$title} - Page {$page}", 16);
        $content .= $this->pdfTextAt(40, 540, 'Date range: '.$from->toDateString().' to '.$to->toDateString(), 9);
        $content .= $this->pdfTextAt(40, 526, "Transactions: {$transactionCount}", 9);

        foreach ($blocks as $block) {
            $content .= $this->tAccountPdfBlock($block, 56, 62);
        }

        return $content;
    }

    private function tAccountPdfBlock(array $block, int $x, int $y): string
    {
        $width = 730;
        $height = 430;
        $center = $x + ($width / 2);
        $top = $y + $height;
        $content = '';

        $content .= $this->pdfTextAt($x + 6, $top + 12, mb_strimwidth($block['account'], 0, 64), 14);
        $content .= "{$x} {$top} m ".($x + $width)." {$top} l S\n";
        $content .= "{$center} {$y} m {$center} {$top} l S\n";
        $content .= $this->pdfTextAt($x + 150, $top - 16, 'Debit', 9);
        $content .= $this->pdfTextAt($center + 150, $top - 16, 'Credit', 9);

        $entryY = $top - 34;
        foreach ($block['debits'] as $entry) {
            $content .= $this->pdfTextAt($x + 6, $entryY, $entry['date'], 8);
            $content .= $this->pdfTextAt($x + 70, $entryY, mb_strimwidth($entry['reference'], 0, 28), 8);
            $content .= $this->pdfTextAt($x + 250, $entryY, number_format((float) $entry['amount'], 2), 8);
            $entryY -= 12;
        }

        $entryY = $top - 34;
        foreach ($block['credits'] as $entry) {
            $content .= $this->pdfTextAt($center + 6, $entryY, number_format((float) $entry['amount'], 2), 8);
            $content .= $this->pdfTextAt($center + 90, $entryY, mb_strimwidth($entry['reference'], 0, 28), 8);
            $content .= $this->pdfTextAt($center + 290, $entryY, $entry['date'], 8);
            $entryY -= 12;
        }

        $content .= "{$x} ".($y + 30).' m '.($x + $width).' '.($y + 30)." l S\n";
        $content .= $this->pdfTextAt($x + 12, $y + 15, 'Total: '.number_format((float) $block['debit_total'], 2), 8);
        $content .= $this->pdfTextAt($center + 12, $y + 15, 'Total: '.number_format((float) $block['credit_total'], 2), 8);
        $content .= $this->pdfTextAt($x + 250, $y - 4, $block['balance'], 10);

        return $content;
    }

    private function tAccountPdfBlocks(Collection $rows): array
    {
        $accounts = [];

        foreach ($rows as $row) {
            $date = Carbon::parse($row['date'])->format('M d');
            $accounts[$row['debit_account']]['debits'][] = [
                'date' => $date,
                'reference' => $row['reference'],
                'amount' => (float) $row['debit_amount'],
            ];
            $accounts[$row['debit_account']]['credits'] ??= [];
            $accounts[$row['credit_account']]['credits'][] = [
                'date' => $date,
                'reference' => $row['reference'],
                'amount' => (float) $row['credit_amount'],
            ];
            $accounts[$row['credit_account']]['debits'] ??= [];
        }

        $order = collect($this->tAccountOptions())->keys()->reject(fn (string $account): bool => $account === 'all')->values();
        $accountNames = $order
            ->merge(array_keys($accounts))
            ->unique()
            ->filter(fn (string $account): bool => array_key_exists($account, $accounts))
            ->values();

        $blocks = [];
        foreach ($accountNames as $account) {
            $debits = $accounts[$account]['debits'] ?? [];
            $credits = $accounts[$account]['credits'] ?? [];
            $debitTotal = array_sum(array_column($debits, 'amount'));
            $creditTotal = array_sum(array_column($credits, 'amount'));
            $entriesPerPage = 30;
            $chunkCount = max(1, (int) ceil(max(count($debits), count($credits), 1) / $entriesPerPage));

            for ($index = 0; $index < $chunkCount; $index++) {
                $isLastChunk = $index === $chunkCount - 1;
                $balanceAmount = abs($debitTotal - $creditTotal);
                $balanceType = $debitTotal >= $creditTotal ? 'Debit Balance' : 'Credit Balance';

                $blocks[] = [
                    'account' => $account.($chunkCount > 1 ? ' (continued '.($index + 1).'/'.$chunkCount.')' : ''),
                    'debits' => array_slice($debits, $index * $entriesPerPage, $entriesPerPage),
                    'credits' => array_slice($credits, $index * $entriesPerPage, $entriesPerPage),
                    'debit_total' => $isLastChunk ? $debitTotal : 0,
                    'credit_total' => $isLastChunk ? $creditTotal : 0,
                    'balance' => $isLastChunk ? 'Balance: '.number_format($balanceAmount, 2)." ({$balanceType})" : 'Continued...',
                ];
            }
        }

        return $blocks;
    }

    private function pdfTextAt(float $x, float $y, string $text, int $size = 9): string
    {
        return "BT /F1 {$size} Tf {$x} {$y} Td ({$this->pdfText($text)}) Tj ET\n";
    }

    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8'));
    }

    private function bankAccountsWithBalances(): Collection
    {
        return DB::table('bank_accounts')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(function (object $account): object {
                $account->balance = $this->bankBalance((int) $account->id);

                return $account;
            });
    }

    private function bankBalance(int $accountId): float
    {
        $openingBalance = (float) DB::table('bank_accounts')->where('id', $accountId)->value('opening_balance');
        $movement = (float) DB::table('bank_transactions')
            ->where('bank_account_id', $accountId)
            ->sum(DB::raw("case when type in ('deposit','qr_payment','card_payment','cash_to_bank','bank_transfer_in') then amount else -amount end"));

        return $openingBalance + $movement;
    }

    private function defaultBankBalance(): float
    {
        $defaultAccountId = DB::table('bank_accounts')->where('is_default', true)->value('id');

        return $defaultAccountId ? $this->bankBalance((int) $defaultAccountId) : 0.0;
    }

    private function cashBalance(): float
    {
        $opening = DB::table('registers')->sum('opening_cash');
        $cashSales = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sale_payments.payment_method', 'cash')
            ->sum('sale_payments.amount');
        $cashIns = DB::table('cash_ins')->whereNull('deleted_at')->sum('amount');
        $cashOuts = DB::table('cash_outs')->whereNull('deleted_at')->sum('amount');
        $expenses = DB::table('expenses')->where('payment_method', 'cash')->whereNull('deleted_at')->sum('amount');

        return (float) ($opening + $cashSales + $cashIns - $cashOuts - $expenses);
    }

    private function emptyCashFlowRows(): mixed
    {
        return DB::query()
            ->fromSub(
                DB::table('sale_payments')
                    ->selectRaw('"Cash In" as flow_type, "" as reference, "" as party, paid_at as date, 0 as inflow, 0 as outflow, "" as note')
                    ->whereRaw('1 = 0'),
                'account_movements'
            )
            ->paginate(25)
            ->withQueryString();
    }

    private function emptyRows(): mixed
    {
        return DB::table('sales')
            ->selectRaw('invoice_no as reference, status as party, sale_date as date, total, paid_amount, due_amount, profit')
            ->whereRaw('1 = 0')
            ->paginate(25)
            ->withQueryString();
    }
}
