<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);

        $sales = DB::table('sales')->whereNull('deleted_at')->whereBetween('sale_date', [$from, $to]);
        $expenses = DB::table('expenses')->whereNull('deleted_at')->whereBetween('expense_date', [$from, $to]);
        $onlineOrders = DB::table('online_orders')->whereNull('deleted_at')->whereBetween('created_at', [$from, $to]);

        $values = [
            'total_sales' => (clone $sales)->sum('total'),
            'profit_loss' => (clone $sales)->sum('profit') - (clone $expenses)->sum('amount'),
            'total_expenses' => (clone $expenses)->sum('amount'),
            'total_takeaway_orders' => (clone $sales)->whereNull('restaurant_table_id')->count(),
            'total_orders' => (clone $sales)->count(),
            'online_orders' => (clone $onlineOrders)->count(),
            'online_sales' => (clone $onlineOrders)->sum('total'),
            'online_cod_pending' => (clone $onlineOrders)->where('payment_status', 'cash_on_delivery')->sum('balance_amount'),
            'online_commission' => (clone $onlineOrders)->sum('commission_amount'),
            'online_net_profit' => (clone $onlineOrders)->sum('total') - (clone $onlineOrders)->sum('commission_amount'),
            'due_bills' => (clone $sales)->where('due_amount', '>', 0)->sum('due_amount'),
            'cash_balance' => $this->cashBalance(),
            'bank_balance' => DB::table('bank_accounts')->sum('opening_balance') + DB::table('bank_transactions')->sum(DB::raw("case when type in ('deposit','qr_payment','card_payment','cash_to_bank','bank_transfer_in') then amount else -amount end")),
            'active_tables' => DB::table('restaurant_tables')->where('status', 'active')->count(),
            'hold_orders' => DB::table('hold_orders')->where('status', 'hold')->whereNull('deleted_at')->count(),
        ];

        $previousSales = (float) DB::table('sales')
            ->whereNull('deleted_at')
            ->whereBetween('sale_date', [$previousFrom, $previousTo])
            ->sum('total');

        $previousExpenses = (float) DB::table('expenses')
            ->whereNull('deleted_at')
            ->whereBetween('expense_date', [$previousFrom, $previousTo])
            ->sum('amount');

        $currentNet = (float) $values['total_sales'] - (float) $values['total_expenses'];
        $previousNet = $previousSales - $previousExpenses;
        $balanceGrowth = $this->growthPercentage($currentNet, $previousNet);

        $topSellingItems = DB::table('sale_items')
            ->select('product_name', DB::raw('sum(quantity) as quantity'), DB::raw('sum(line_total) as total'))
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->groupBy('product_name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();

        $waiterIncentives = DB::table('waiter_incentives')
            ->selectRaw('waiter_id, sum(amount) as incentive')
            ->whereBetween('earned_at', [$from, $to])
            ->groupBy('waiter_id');

        $waiterSummary = DB::table('sales')
            ->leftJoin('waiters', 'waiters.id', '=', 'sales.waiter_id')
            ->leftJoinSub($waiterIncentives, 'waiter_incentives', function ($join): void {
                $join->on('waiter_incentives.waiter_id', '=', 'sales.waiter_id');
            })
            ->selectRaw('coalesce(waiters.name, "No waiter") as name, count(sales.id) as orders, count(distinct sales.restaurant_table_id) as tables_handled, sum(sales.total) as total, sum(sales.profit) as profit, coalesce(max(waiter_incentives.incentive), 0) as incentive')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->groupBy('waiters.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $activityItems = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select('activity_logs.*', 'users.name as user_name')
            ->latest('activity_logs.created_at')
            ->limit(9)
            ->get()
            ->map(function (object $activity): object {
                $activity->user_name = User::visibleName($activity->user_name ?? null, 'System');

                return $activity;
            });

        [$chartLabels, $salesSeries, $profitSeries] = $this->buildMonthlyRevenueSeries($to);

        $weekProgress = [
            'Current week' => $this->salesTotalForRange(now()->startOfWeek(), now()->endOfWeek()),
            'Last week' => $this->salesTotalForRange(now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()),
            'Last month' => $this->salesTotalForRange(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()),
            '6 months ago' => $this->salesTotalForRange(now()->subMonths(6)->startOfMonth(), now()->subMonths(6)->endOfMonth()),
        ];

        $weekProgressMax = max(1.0, max($weekProgress));

        return view('backoffice.dashboard', compact(
            'values',
            'topSellingItems',
            'waiterSummary',
            'activityItems',
            'chartLabels',
            'salesSeries',
            'profitSeries',
            'weekProgress',
            'weekProgressMax',
            'balanceGrowth',
        ));
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
            'custom' => [Carbon::parse($request->input('from', today()))->startOfDay(), Carbon::parse($request->input('to', today()))->endOfDay()],
            default => [today()->startOfDay(), today()->endOfDay()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousRange(Carbon $from, Carbon $to): array
    {
        $durationInDays = max(1, $from->diffInDays($to) + 1);

        return [
            $from->copy()->subDays($durationInDays),
            $from->copy()->subSecond(),
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, float>, 2: array<int, float>}
     */
    private function buildMonthlyRevenueSeries(Carbon $to): array
    {
        $points = collect(range(6, 0))->map(fn (int $offset): Carbon => $to->copy()->startOfMonth()->subMonths($offset));

        $labels = $points->map(fn (Carbon $point): string => $point->format('M'))->values()->all();
        $salesSeries = [];
        $profitSeries = [];

        foreach ($points as $point) {
            $monthStart = $point->copy()->startOfMonth();
            $monthEnd = $point->copy()->endOfMonth();

            $salesSeries[] = (float) DB::table('sales')
                ->whereNull('deleted_at')
                ->whereBetween('sale_date', [$monthStart, $monthEnd])
                ->sum('total');

            $profitSeries[] = (float) DB::table('sales')
                ->whereNull('deleted_at')
                ->whereBetween('sale_date', [$monthStart, $monthEnd])
                ->sum('profit');
        }

        return [$labels, $salesSeries, $profitSeries];
    }

    private function salesTotalForRange(Carbon $from, Carbon $to): float
    {
        return (float) DB::table('sales')
            ->whereNull('deleted_at')
            ->whereBetween('sale_date', [$from, $to])
            ->sum('total');
    }

    private function growthPercentage(float $current, float $previous): float
    {
        if (abs($previous) < 0.0001) {
            if ($current === 0.0) {
                return 0.0;
            }

            return 100.0;
        }

        return (($current - $previous) / abs($previous)) * 100;
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
}
