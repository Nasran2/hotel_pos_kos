<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(Request $request, string $module): View
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.view");

        $query = DB::table($config['table'])->latest();

        if ($module === 'users') {
            $query = $query->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
                ->select('users.*', 'user_roles.role_id')
                ->orderByDesc('users.id');
        }

        if ($this->hasSoftDeletes($config['table'])) {
            $query->whereNull('deleted_at');
        }

        if ($module === 'expenses') {
            $query->where(function ($expenseQuery): void {
                $expenseQuery->whereNull('source_type')
                    ->orWhere('source_type', '!=', 'sale')
                    ->orWhereExists(function ($saleQuery): void {
                        $saleQuery->selectRaw('1')
                            ->from('sales')
                            ->whereColumn('sales.id', 'expenses.source_id')
                            ->whereNull('sales.deleted_at');
                    });
            });
        }

        if ($request->filled('search')) {
            $query->where(function ($builder) use ($config, $request): void {
                foreach ($config['search'] ?? [] as $column) {
                    $builder->orWhere($column, 'like', '%'.$request->string('search')->toString().'%');
                }
            });
        }

        $range = $request->input('range');
        if (filled($range) && $range !== 'all_time' && $module !== 'waiters') {
            [$from, $to] = match ($range) {
                'today' => [today()->startOfDay(), today()->endOfDay()],
                'yesterday' => [today()->subDay()->startOfDay(), today()->subDay()->endOfDay()],
                'this_week' => [today()->startOfWeek(), today()->endOfWeek()],
                'last_week' => [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()],
                'this_month' => [today()->startOfMonth(), today()->endOfMonth()],
                'last_month' => [today()->subMonth()->startOfMonth(), today()->subMonth()->endOfMonth()],
                'custom' => [\Carbon\Carbon::parse($request->input('from', today()))->startOfDay(), \Carbon\Carbon::parse($request->input('to', today()))->endOfDay()],
                default => [today()->startOfDay(), today()->endOfDay()],
            };

            $dateColumn = match ($module) {
                'sales' => 'sale_date',
                'purchases' => 'purchase_date',
                'expenses' => 'expense_date',
                'damage_write_offs' => 'write_off_date',
                default => 'created_at',
            };

            $query->whereBetween($config['table'].'.'.$dateColumn, [$from, $to]);
        }

        foreach (($config['filters'] ?? []) as $column => $source) {
            if ($request->filled($column) && $request->input($column) !== 'all') {
                if ($column === 'stock_status') {
                    match ($request->input($column)) {
                        'Low Stock' => $query->whereColumn('stock_quantity', '<=', 'alert_quantity'),
                        'Out of Stock' => $query->where('stock_quantity', '<=', 0),
                        default => null,
                    };
                } else {
                    $query->where($column, $request->input($column));
                }
            }
        }

        if ($module === 'waiters') {
            $earnedIncentives = DB::table('waiter_incentives')
                ->selectRaw('waiter_id, sum(amount) as earned_incentive')
                ->groupBy('waiter_id');

            $paidIncentives = DB::table('expenses')
                ->selectRaw('source_id as waiter_id, sum(amount) as paid_incentive')
                ->whereNull('deleted_at')
                ->where('source_type', 'waiter_incentive_payment')
                ->groupBy('source_id');

            $query
                ->leftJoinSub($earnedIncentives, 'earned_incentives', function ($join): void {
                    $join->on('earned_incentives.waiter_id', '=', 'waiters.id');
                })
                ->leftJoinSub($paidIncentives, 'paid_incentives', function ($join): void {
                    $join->on('paid_incentives.waiter_id', '=', 'waiters.id');
                })
                ->selectRaw('waiters.*, coalesce(earned_incentives.earned_incentive, 0) as earned_incentive, coalesce(paid_incentives.paid_incentive, 0) as paid_incentive, greatest(coalesce(earned_incentives.earned_incentive, 0) - coalesce(paid_incentives.paid_incentive, 0), 0) as payable_incentive');
        }

        if ($module === 'customers') {
            $customerDue = DB::table('sales')
                ->selectRaw('customer_id, sum(due_amount) as sale_due_amount')
                ->whereNull('deleted_at')
                ->groupBy('customer_id');

            $query
                ->leftJoinSub($customerDue, 'customer_due', function ($join): void {
                    $join->on('customer_due.customer_id', '=', 'customers.id');
                })
                ->selectRaw('customers.*, coalesce(customers.opening_balance, 0) + coalesce(customer_due.sale_due_amount, 0) as due_balance');
        }

        $records = $query->paginate((int) $this->setting('display.items_per_page', 10))->withQueryString();

        return view('backoffice.index', [
            'module' => $module,
            'config' => $config,
            'records' => $records,
            'lookups' => $this->lookups($config),
        ]);
    }

    public function create(string $module): View
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.create");

        if ($module === 'purchases') {
            return view('backoffice.purchase-form', [
                'module' => $module,
                'config' => $config,
                'suppliers' => DB::table('suppliers')->whereNull('deleted_at')->orderBy('name')->get(),
                'categories' => DB::table('categories')->whereNull('deleted_at')->where('is_active', true)->orderBy('name')->get(),
                'products' => DB::table('products')
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name', 'sku', 'barcode', 'cost_price', 'selling_price', 'stock_quantity']),
            ]);
        }

        return view('backoffice.form', [
            'module' => $module,
            'config' => $config,
            'record' => null,
            'lookups' => $this->lookups($config),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request, string $module): RedirectResponse|JsonResponse
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.create");

        if ($module === 'purchases') {
            return $this->storePurchase($request, $module, $config);
        }

        $payload = $this->validatedPayload($request, $module, $config);

        return DB::transaction(function () use ($request, $module, $config, $payload): RedirectResponse|JsonResponse {
            if ($module === 'users') {
                $user = User::query()->create(Arr::except($payload, ['role_id']));
                $user->roles()->sync([(int) $payload['role_id']]);
                $recordId = $user->id;
            } elseif ($module === 'roles') {
                $role = Role::query()->create(Arr::except($payload, ['permissions']));
                $role->permissions()->sync(Permission::query()->whereIn('name', $request->input('permissions', []))->pluck('id'));
                $recordId = $role->id;
            } else {
                $recordId = DB::table($config['table'])->insertGetId($this->stamp($payload));
                $this->afterStore($module, $recordId, $payload);
            }

            ActivityLog::record('create', $module, "{$config['label']} record created.", ['id' => $recordId]);

            if (in_array($module, ['categories', 'products', 'suppliers'], true) && $request->expectsJson()) {
                $responseKey = str($module)->singular()->toString();
                $columns = match ($module) {
                    'categories' => ['id', 'name', 'description', 'is_active'],
                    'products' => ['id', 'name', 'sku', 'barcode', 'cost_price', 'selling_price', 'stock_quantity'],
                    default => ['id', 'name', 'phone', 'email', 'company_name', 'address', 'opening_balance', 'is_active'],
                };

                return response()->json([
                    $responseKey => DB::table($config['table'])
                        ->where('id', $recordId)
                        ->first($columns),
                ], 201);
            }

            if ($module === 'products' && $request->input('save_action') === 'add_new') {
                return redirect()->route('backoffice.modules.create', $module)->with('status', 'Product saved. Add the next product.');
            }

            if ($module === 'products') {
                return redirect()->route('backoffice.modules.index', $module)->with('status', 'Product saved.');
            }

            return redirect()->route('backoffice.modules.show', [$module, $recordId])->with('status', "{$config['label']} saved.");
        });
    }

    public function show(string $module, int $id): View
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.view");
        $record = $this->record($config, $id);

        if ($module === 'sales') {
            $record->deducted_amount = (float) DB::table('sale_deductions')->where('sale_id', $id)->sum('amount');
            $record->items = DB::table('sale_items')->where('sale_id', $id)->get();

            return view('backoffice.sales.show', [
                'module' => $module,
                'config' => $config,
                'record' => $record,
                'lookups' => $this->lookups($config),
                'history' => $this->history($module, $id),
            ]);
        }

        if ($module === 'customers') {
            return view('backoffice.customers.show', [
                'module' => $module,
                'config' => $config,
                'record' => $record,
                ...$this->customerShowData($id),
            ]);
        }

        if ($module === 'products') {
            return view('backoffice.products.show', [
                'module' => $module,
                'config' => $config,
                'record' => $record,
                ...$this->productShowData($id),
            ]);
        }

        if ($module === 'waiters') {
            return view('backoffice.waiters.show', [
                'module' => $module,
                'config' => $config,
                'record' => $record,
                'lookups' => $this->lookups($config),
                'history' => $this->history($module, $id),
                ...$this->waiterShowData($id),
            ]);
        }

        return view('backoffice.show', [
            'module' => $module,
            'config' => $config,
            'record' => $record,
            'lookups' => $this->lookups($config),
            'history' => $this->history($module, $id),
        ]);
    }

    public function printSale(string $module, int $id): View
    {
        abort_unless($module === 'sales', 404);

        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.print");

        $sale = DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoin('waiters', 'waiters.id', '=', 'sales.waiter_id')
            ->leftJoin('restaurant_tables', 'restaurant_tables.id', '=', 'sales.restaurant_table_id')
            ->where('sales.id', $id)
            ->whereNull('sales.deleted_at')
            ->select([
                'sales.*',
                'customers.name as customer_name',
                'waiters.name as waiter_name',
                'restaurant_tables.number as table_number',
            ])
            ->firstOrFail();

        $items = DB::table('sale_items')
            ->where('sale_id', $id)
            ->orderBy('id')
            ->get();

        $payments = DB::table('sale_payments')
            ->where('sale_id', $id)
            ->orderBy('paid_at')
            ->get();

        return view('backoffice.sales.print', [
            'sale' => $sale,
            'items' => $items,
            'payments' => $payments,
            'settings' => $this->receiptSettings(),
        ]);
    }

    public function edit(string $module, int $id): View
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.edit");

        if ($module === 'sales') {
            $record = $this->record($config, $id);
            $saleItems = DB::table('sale_items')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('sale_items.sale_id', $id)
                ->select([
                    'sale_items.*',
                    'products.name as product_name',
                    'products.barcode',
                    'products.cost_price',
                    'products.selling_price'
                ])
                ->get();

            return view('backoffice.sale-form', [
                'module' => $module,
                'config' => $config,
                'record' => $record,
                'saleItems' => $saleItems,
                'customers' => DB::table('customers')->whereNull('deleted_at')->orderBy('name')->get(),
                'waiters' => DB::table('waiters')->whereNull('deleted_at')->orderBy('name')->get(),
                'tables' => DB::table('restaurant_tables')->whereNull('deleted_at')->orderBy('number')->get(),
                'products' => DB::table('products')
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name', 'sku', 'barcode', 'cost_price', 'selling_price', 'stock_quantity']),
            ]);
        }

        return view('backoffice.form', [
            'module' => $module,
            'config' => $config,
            'record' => $this->record($config, $id),
            'lookups' => $this->lookups($config),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, string $module, int $id): RedirectResponse
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.edit");
        $payload = $this->validatedPayload($request, $module, $config, $id);

        return DB::transaction(function () use ($request, $module, $config, $payload, $id): RedirectResponse {
            if ($module === 'users') {
                $user = User::query()->findOrFail($id);
                $user->update(Arr::except($payload, ['role_id']));
                $user->roles()->sync([(int) $payload['role_id']]);
            } elseif ($module === 'roles') {
                $role = Role::query()->findOrFail($id);
                $role->update(Arr::except($payload, ['permissions']));
                $role->permissions()->sync(Permission::query()->whereIn('name', $request->input('permissions', []))->pluck('id'));
            } elseif ($module === 'sales') {
                $sale = DB::table('sales')->where('id', $id)->first();
                $oldItems = DB::table('sale_items')->where('sale_id', $id)->get();
                
                // Revert old stock
                foreach ($oldItems as $oldItem) {
                    $product = DB::table('products')->where('id', $oldItem->product_id)->first();
                    if ($product && $product->maintain_stock) {
                        DB::table('products')->where('id', $product->id)->increment('stock_quantity', $oldItem->quantity);
                        DB::table('stock_movements')->where('source_type', 'sale')->where('source_id', $id)->where('product_id', $product->id)->delete();
                    }
                }
                
                DB::table('sale_items')->where('sale_id', $id)->delete();
                
                $items = $request->input('items', []);
                $subtotal = 0;
                $profit = 0;
                $now = now();
                $discountAmount = (float) $request->input('discount_amount', 0);
                
                foreach ($items as $item) {
                    $product = DB::table('products')->where('id', $item['product_id'])->first();
                    $quantity = (float) $item['quantity'];
                    $unitPrice = (float) $item['unit_price'];
                    $discountPercent = (float) ($item['discount_percent'] ?? 0);
                    
                    $gross = $quantity * $unitPrice;
                    $itemDiscount = $gross * ($discountPercent / 100);
                    $lineTotal = max(0, $gross - $itemDiscount);
                    
                    $productCost = $product ? (float) $product->cost_price : 0.0;
                    $itemProfit = (($unitPrice - $productCost) * $quantity) - $itemDiscount;
                    
                    $subtotal += $gross;
                    $profit += $itemProfit;
                    
                    DB::table('sale_items')->insert([
                        'sale_id' => $id,
                        'product_id' => $product ? $product->id : ($item['product_id'] ?? null),
                        'product_name' => $product ? $product->name : ($item['product_name'] ?? 'Unknown Product'),
                        'quantity' => $quantity,
                        'unit_cost' => $productCost,
                        'unit_price' => $unitPrice,
                        'discount_type' => $discountPercent > 0 ? 'percentage' : null,
                        'discount_amount' => $itemDiscount,
                        'line_total' => $lineTotal,
                        'profit' => $itemProfit,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    
                    if ($product && $product->maintain_stock) {
                        $balance = $product->stock_quantity - $quantity;
                        DB::table('products')->where('id', $product->id)->update(['stock_quantity' => $balance, 'updated_at' => $now]);
                        DB::table('stock_movements')->insert([
                            'product_id' => $product->id,
                            'type' => 'sale',
                            'quantity' => -abs($quantity),
                            'balance_after' => $balance,
                            'source_type' => 'sale',
                            'source_id' => $id,
                            'note' => $sale->invoice_no,
                            'created_at' => $now,
                        ]);
                    }
                }
                
                $total = max(0, $subtotal - $discountAmount + $sale->service_charge);
                $paid = (float) $sale->paid_amount;
                $due = max(0, $total - $paid);
                $status = $due > 0 ? 'due' : 'paid';
                
                // Proportionally reduce profit by global discount
                $profit = $profit - $discountAmount;
                
                DB::table('sales')->where('id', $id)->update([
                    'customer_id' => $request->input('customer_id') ?: null,
                    'waiter_id' => $request->input('waiter_id') ?: null,
                    'restaurant_table_id' => $request->input('restaurant_table_id') ?: null,
                    'sale_date' => $request->input('sale_date'),
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'due_amount' => $due,
                    'profit' => $profit,
                    'status' => $request->input('status', $status),
                    'note' => $request->input('note'),
                    'updated_at' => $now,
                ]);
            } else {
                DB::table($config['table'])->where('id', $id)->update([...$payload, 'updated_at' => now()]);
                if ($module === 'expenses') {
                    $this->syncExpenseBankTransaction($id, $payload);
                }
            }

            ActivityLog::record('update', $module, "{$config['label']} record updated.", ['id' => $id]);

            return redirect()->route('backoffice.modules.show', [$module, $id])->with('status', "{$config['label']} updated.");
        });
    }

    public function destroy(string $module, int $id): RedirectResponse
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.delete");

        DB::transaction(function () use ($module, $config, $id): void {
            $this->beforeDestroy($module, $id);

            if ($module === 'users') {
                User::query()->findOrFail($id)->delete();
            } elseif ($module === 'roles') {
                Role::query()->findOrFail($id)->delete();
            } elseif ($this->hasSoftDeletes($config['table'])) {
                DB::table($config['table'])->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table($config['table'])->where('id', $id)->delete();
            }

            ActivityLog::record('delete', $module, "{$config['label']} record deleted.", ['id' => $id]);
        });

        return redirect()->route('backoffice.modules.index', $module)->with('status', "{$config['label']} deleted.");
    }

    public function payments(Request $request, string $module, int $id)
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.edit");

        if ($module === 'waiters') {
            $waiter = DB::table('waiters')->where('id', $id)->whereNull('deleted_at')->firstOrFail();
            $earned = (float) DB::table('waiter_incentives')->where('waiter_id', $id)->sum('amount');
            $paid = (float) DB::table('expenses')
                ->whereNull('deleted_at')
                ->where('source_type', 'waiter_incentive_payment')
                ->where('source_id', $id)
                ->sum('amount');

            return view('backoffice.partials.waiter-payments', [
                'waiter' => $waiter,
                'earned' => $earned,
                'paid' => $paid,
                'payable' => max(0, $earned - $paid),
                'payments' => DB::table('expenses')
                    ->whereNull('deleted_at')
                    ->where('source_type', 'waiter_incentive_payment')
                    ->where('source_id', $id)
                    ->orderByDesc('expense_date')
                    ->get(),
                'module' => $module,
            ]);
        }

        if ($module !== 'sales') {
            abort(404);
        }

        $sale = DB::table('sales')->where('id', $id)->firstOrFail();

        $payments = DB::table('sale_payments')->where('sale_id', $id)->orderByDesc('paid_at')->get();
        $deductions = DB::table('sale_deductions')->where('sale_id', $id)->orderByDesc('deduction_date')->get();

        return view('backoffice.partials.sale-payments', [
            'sale' => $sale,
            'payments' => $payments,
            'deductions' => $deductions,
            'module' => $module,
        ]);
    }

    public function toggleActive(Request $request, string $module, int $id): JsonResponse
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.edit");

        if (($config['fields']['is_active']['type'] ?? null) !== 'boolean') {
            abort(404);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'in:0,1'],
        ]);

        DB::table($config['table'])->where('id', $id)->firstOrFail();

        DB::table($config['table'])
            ->where('id', $id)
            ->update(['is_active' => (int) $validated['is_active'], 'updated_at' => now()]);

        $record = DB::table($config['table'])->where('id', $id)->first();

        ActivityLog::record('update', $module, "{$config['label']} active status updated.", [
            'id' => $id,
            'is_active' => (int) $validated['is_active'],
        ]);

        return response()->json([
            'ok' => true,
            'record' => $record,
            'waiter' => $module === 'waiters' ? $record : null,
        ]);
    }

    public function storePayment(Request $request, string $module, int $id): RedirectResponse|JsonResponse
    {
        $config = $this->module($module);
        $this->authorize("{$config['permission_prefix']}.edit");

        if ($module === 'waiters') {
            $validated = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'payment_method' => ['required', 'in:cash,bank,card'],
                'paid_at' => ['nullable', 'date'],
                'note' => ['nullable', 'string'],
            ]);

            $waiter = DB::table('waiters')->where('id', $id)->whereNull('deleted_at')->firstOrFail();
            $earned = (float) DB::table('waiter_incentives')->where('waiter_id', $id)->sum('amount');
            $paid = (float) DB::table('expenses')
                ->whereNull('deleted_at')
                ->where('source_type', 'waiter_incentive_payment')
                ->where('source_id', $id)
                ->sum('amount');
            $payable = max(0, $earned - $paid);
            $amount = (float) $validated['amount'];

            if ($amount <= 0) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'No payable waiter incentive balance is available.'], 422);
                }

                return back()->withErrors('No payable waiter incentive balance is available.');
            }

            if ($amount > $payable) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Payment amount cannot be greater than the payable waiter incentive balance.'], 422);
                }

                return back()->withErrors('Payment amount cannot be greater than the payable waiter incentive balance.');
            }

            DB::transaction(function () use ($amount, $id, $module, $validated, $waiter): void {
                $categoryId = $this->expenseCategoryId('Waiter Incentive');

                $expenseId = DB::table('expenses')->insertGetId([
                    'expense_category_id' => $categoryId,
                    'user_id' => auth()->id(),
                    'amount' => $amount,
                    'payment_method' => $validated['payment_method'],
                    'expense_date' => $validated['paid_at'] ?? now(),
                    'note' => $validated['note'] ?: 'Waiter incentive payment for '.$waiter->name,
                    'attachment_path' => null,
                    'source_type' => 'waiter_incentive_payment',
                    'source_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                ActivityLog::record('payment', $module, 'Waiter incentive payment recorded.', [
                    'waiter_id' => $id,
                    'expense_id' => $expenseId,
                    'amount' => $amount,
                ]);
            });

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'message' => 'Waiter incentive payment saved as an expense.']);
            }

            return back()->with('status', 'Waiter incentive payment saved as an expense.');
        }

        if ($module !== 'sales') {
            abort(404);
        }

        $validated = $request->validate([
            'payment_amount' => ['nullable', 'required_without:deduction_amount', 'numeric', 'min:0.01'],
            'deduction_amount' => ['nullable', 'required_without:payment_amount', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'in:cash,card,bank,qr'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $sale = DB::table('sales')->where('id', $id)->firstOrFail();

        DB::transaction(function () use ($validated, $sale, $id): void {
            $paidAt = $validated['paid_at'] ?? now();
            $method = $validated['payment_method'] ?? 'cash';
            $paymentAmount = (float) ($validated['payment_amount'] ?? 0);
            $deductionAmount = (float) ($validated['deduction_amount'] ?? 0);

            if ($paymentAmount > 0) {
                DB::table('sale_payments')->insert([
                    'sale_id' => $id,
                    'payment_method' => $method,
                    'amount' => $paymentAmount,
                    'received_amount' => $paymentAmount,
                    'change_amount' => 0,
                    'fee_amount' => 0,
                    'paid_at' => $paidAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($deductionAmount > 0) {
                $deductionId = DB::table('sale_deductions')->insertGetId([
                    'sale_id' => $id,
                    'amount' => $deductionAmount,
                    'payment_method' => $method,
                    'deduction_date' => $paidAt,
                    'note' => $validated['note'] ?? 'Sale deduction',
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $channelLabel = $this->saleChannelLabel((string) ($sale->order_channel ?? 'pos'));
                $categoryId = DB::table('expense_categories')->where('name', 'Sale Deduction')->value('id')
                    ?: DB::table('expense_categories')->insertGetId($this->stamp(['name' => 'Sale Deduction', 'is_active' => true]));

                DB::table('expenses')->insert($this->stamp([
                    'expense_category_id' => $categoryId,
                    'user_id' => auth()->id(),
                    'amount' => $deductionAmount,
                    'payment_method' => $method,
                    'expense_date' => $paidAt,
                    'note' => trim('Sale deduction for invoice '.($sale->invoice_no ?? $id).' ('.$channelLabel.'). '.($validated['note'] ?? '')),
                    'source_type' => 'sale_deduction',
                    'source_id' => $deductionId,
                ]));
            }

            $deductionTotal = (float) DB::table('sale_deductions')->where('sale_id', $id)->sum('amount');
            $newPaid = (float) $sale->paid_amount + $paymentAmount;
            $newDue = max(0.0, (float) $sale->total - $newPaid - $deductionTotal);

            DB::table('sales')->where('id', $id)->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'status' => $newDue > 0 ? 'due' : 'paid',
                'updated_at' => now(),
            ]);

            ActivityLog::record('payment', 'sales', 'Sale payment/deduction recorded.', [
                'sale_id' => $id,
                'invoice_no' => $sale->invoice_no ?? null,
                'order_channel' => $sale->order_channel ?? null,
                'payment_amount' => $paymentAmount,
                'deduction_amount' => $deductionAmount,
            ]);
        });

        return back()->with('status', 'Recorded.');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function storePurchase(Request $request, string $module, array $config): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'reference_no' => ['nullable', 'string'],
            'purchase_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank,card'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.new_selling_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = collect($validated['items'])->map(function (array $item): array {
            $quantity = (float) $item['quantity'];
            $unitCost = (float) $item['unit_cost'];
            $discountPercent = (float) ($item['discount_percent'] ?? 0);
            $gross = $quantity * $unitCost;
            $discountAmount = round($gross * $discountPercent / 100, 2);
            $lineTotal = max(0, round($gross - $discountAmount, 2));

            return [
                'product_id' => (int) $item['product_id'],
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'line_total' => $lineTotal,
                'new_selling_price' => isset($item['new_selling_price']) && $item['new_selling_price'] !== ''
                    ? (float) $item['new_selling_price']
                    : null,
            ];
        });

        $subtotal = round($items->sum(fn (array $item): float => $item['quantity'] * $item['unit_cost']), 2);
        $discountTotal = round($items->sum('discount_amount'), 2);
        $grandTotal = round($items->sum('line_total'), 2);
        $paidAmount = min((float) ($validated['paid_amount'] ?? 0), $grandTotal);
        $dueAmount = max(0, $grandTotal - $paidAmount);

        return DB::transaction(function () use ($config, $items, $module, $validated, $subtotal, $discountTotal, $grandTotal, $paidAmount, $dueAmount): RedirectResponse {
            $purchaseId = DB::table('purchases')->insertGetId($this->stamp([
                'supplier_id' => $validated['supplier_id'],
                'user_id' => auth()->id(),
                'reference_no' => $validated['reference_no'] ?? null,
                'purchase_date' => $validated['purchase_date'],
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => 0,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'notes' => $validated['notes'] ?? null,
            ]));

            foreach ($items as $item) {
                DB::table('purchase_items')->insert($this->stamp([
                    'purchase_id' => $purchaseId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'discount_type' => $item['discount_percent'] > 0 ? 'percentage' : null,
                    'discount_amount' => $item['discount_amount'],
                    'tax_amount' => 0,
                    'line_total' => $item['line_total'],
                    'updates_selling_price' => $item['new_selling_price'] !== null,
                    'new_selling_price' => $item['new_selling_price'],
                ]));

                DB::table('products')->where('id', $item['product_id'])->increment('stock_quantity', $item['quantity']);
                DB::table('products')->where('id', $item['product_id'])->update([
                    'cost_price' => $item['unit_cost'],
                    ...($item['new_selling_price'] !== null ? ['selling_price' => $item['new_selling_price']] : []),
                    'updated_at' => now(),
                ]);
            }

            if ($paidAmount > 0) {
                DB::table('purchase_payments')->insert($this->stamp([
                    'purchase_id' => $purchaseId,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $paidAmount,
                    'paid_at' => $validated['purchase_date'],
                    'note' => 'Initial purchase payment.',
                ]));
            }

            ActivityLog::record('create', $module, "{$config['label']} record created.", [
                'id' => $purchaseId,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
            ]);

            return redirect()->route('backoffice.modules.show', [$module, $purchaseId])->with('status', "{$config['label']} saved.");
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function module(string $module): array
    {
        abort_unless(array_key_exists($module, config('hotelpos.modules')), 404);

        return config("hotelpos.modules.{$module}");
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, string $module, array $config, ?int $id = null): array
    {
        $rules = [];

        foreach ($config['fields'] as $name => $field) {
            if ($field['virtual'] ?? false) {
                continue;
            }

            $rule = [];
            $rule[] = ($field['required'] ?? false) || (($field['required_on_create'] ?? false) && $id === null) ? 'required' : 'nullable';
            $rule[] = match ($field['type']) {
                'email' => 'email',
                'number' => 'numeric',
                'boolean' => 'boolean',
                'datetime-local' => 'date',
                'file' => 'file|max:4096',
                default => 'string',
            };

            if (($field['unique'] ?? false) === true) {
                $rule[] = Rule::unique($config['table'], $name)->ignore($id);
            }

            $rules[$name] = $rule;
        }

        if ($module === 'users') {
            $rules['password'] = [$id === null ? 'required' : 'nullable', 'confirmed', 'min:6'];
            $rules['role_id'] = ['required', 'exists:roles,id'];
        }

        $validated = $request->validate($rules);

        foreach ($config['fields'] as $name => $field) {
            if (($field['type'] ?? null) === 'boolean') {
                $validated[$name] = $request->boolean($name);
            }

            if (($field['type'] ?? null) === 'select' && blank($validated[$name] ?? null)) {
                $validated[$name] = null;
            }

            if (($field['type'] ?? null) === 'number' && blank($validated[$name] ?? null)) {
                $validated[$name] = 0;
            }

            if (($field['type'] ?? null) === 'datetime-local' && filled($validated[$name] ?? null)) {
                $validated[$name] = Carbon::parse($validated[$name]);
            }

            if (($field['type'] ?? null) === 'file' && $request->hasFile($name)) {
                $validated[$name] = $request->file($name)->store('uploads', 'public');
            } elseif (($field['type'] ?? null) === 'file' && $id !== null) {
                unset($validated[$name]);
            }
        }

        if (array_key_exists('password', $validated)) {
            if (blank($validated['password'])) {
                unset($validated['password']);
            } else {
                $validated['password'] = Hash::make($validated['password']);
            }
        }

        if ($module === 'products') {
            if (blank($validated['barcode'] ?? null)) {
                $validated['barcode'] = $this->nextBarcode();
            }

            $validated['sku'] = $validated['barcode'];
        }

        if ($module === 'roles') {
            $validated['permissions'] = $request->input('permissions', []);
        }

        if ($module === 'expenses') {
            if (($validated['payment_method'] ?? null) === 'bank') {
                $validated['bank_account_id'] = $validated['bank_account_id']
                    ?? DB::table('bank_accounts')->where('is_default', true)->value('id');
            } else {
                $validated['bank_account_id'] = null;
            }
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function lookups(array $config): array
    {
        $lookups = [];

        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? null) === 'select' && isset($field['source'])) {
                $source = config("hotelpos.modules.{$field['source']}.table");
                $orderColumn = DB::getSchemaBuilder()->hasColumn($source, 'name') ? 'name' : 'number';
                $query = DB::table($source)->orderBy($orderColumn);
                if ($this->hasSoftDeletes($source)) {
                    $query->whereNull('deleted_at');
                }
                $lookups[$field['source']] = $query->get();
            }
        }

        return $lookups;
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionGroups(): array
    {
        return Permission::query()->orderBy('category')->orderBy('label')->get()->groupBy('category')->all();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function record(array $config, int $id): object
    {
        $record = DB::table($config['table'])->where('id', $id)->first() ?? abort(404);

        if (array_key_exists('role_id', (array) $record)) {
            return $record;
        }

        if ($config['table'] !== 'users') {
            return $record;
        }

        $roleId = DB::table('user_roles')->where('user_id', $id)->value('role_id');

        if ($roleId !== null) {
            $record->role_id = $roleId;
        }

        return $record;
    }

    private function hasSoftDeletes(string $table): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, 'deleted_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptSettings(): array
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
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function stamp(array $payload): array
    {
        return [...$payload, 'created_at' => now(), 'updated_at' => now()];
    }

    private function afterStore(string $module, int $recordId, array $payload): void
    {
        if ($module === 'expenses') {
            $this->syncExpenseBankTransaction($recordId, $payload);

            return;
        }

        if ($module !== 'damage_write_offs') {
            return;
        }

        $product = DB::table('products')->where('id', $payload['product_id'])->first();
        $costAmount = (float) $product->cost_price * (float) $payload['quantity'];
        $categoryId = DB::table('expense_categories')->where('name', 'Damage Write-Off')->value('id')
            ?: DB::table('expense_categories')->insertGetId($this->stamp(['name' => 'Damage Write-Off', 'is_active' => true]));

        $expenseId = DB::table('expenses')->insertGetId($this->stamp([
            'expense_category_id' => $categoryId,
            'user_id' => auth()->id(),
            'amount' => $costAmount,
            'payment_method' => 'cash',
            'expense_date' => $payload['write_off_date'],
            'note' => 'Automatic expense from damage write-off.',
            'source_type' => 'damage_write_off',
            'source_id' => $recordId,
        ]));

        DB::table('damage_write_offs')->where('id', $recordId)->update(['expense_id' => $expenseId, 'cost_amount' => $costAmount]);

        if ($product->maintain_stock) {
            $newStock = max(0, (float) $product->stock_quantity - (float) $payload['quantity']);
            DB::table('products')->where('id', $product->id)->update(['stock_quantity' => $newStock, 'updated_at' => now()]);
            DB::table('stock_movements')->insert($this->stamp([
                'product_id' => $product->id,
                'type' => 'damage_write_off',
                'quantity' => -abs((float) $payload['quantity']),
                'balance_after' => $newStock,
                'source_type' => 'damage_write_off',
                'source_id' => $recordId,
                'note' => $payload['reason'],
            ]));
        }
    }

    private function syncExpenseBankTransaction(int $expenseId, array $payload): void
    {
        DB::table('bank_transactions')
            ->where('source_type', 'expense')
            ->where('source_id', $expenseId)
            ->delete();

        if (($payload['payment_method'] ?? null) !== 'bank' || blank($payload['bank_account_id'] ?? null)) {
            return;
        }

        DB::table('bank_transactions')->insert($this->stamp([
            'bank_account_id' => $payload['bank_account_id'],
            'type' => 'bank_expense',
            'amount' => $payload['amount'],
            'transaction_date' => $payload['expense_date'] ?? now(),
            'source_type' => 'expense',
            'source_id' => $expenseId,
            'note' => $payload['note'] ?? 'Expense paid from bank',
        ]));
    }

    private function beforeDestroy(string $module, int $id): void
    {
        if ($module === 'expenses') {
            DB::table('bank_transactions')
                ->where('source_type', 'expense')
                ->where('source_id', $id)
                ->delete();

            return;
        }

        if ($module !== 'sales') {
            return;
        }

        $sale = DB::table('sales')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
        $items = DB::table('sale_items')->where('sale_id', $id)->get();
        foreach ($items as $item) {
            $product = DB::table('products')->where('id', $item->product_id)->lockForUpdate()->first();
            if ($product && $product->maintain_stock) {
                $newStock = (float) $product->stock_quantity + (float) $item->quantity;

                DB::table('products')->where('id', $product->id)->update([
                    'stock_quantity' => $newStock,
                    'updated_at' => now(),
                ]);

                DB::table('stock_movements')->insert($this->stamp([
                    'product_id' => $product->id,
                    'type' => 'sale_delete',
                    'quantity' => abs((float) $item->quantity),
                    'balance_after' => $newStock,
                    'source_type' => 'sale',
                    'source_id' => $id,
                    'note' => 'Restocked after deleting sale '.($sale->invoice_no ?? "#{$id}"),
                ]));
            }
        }

        $deductionIds = DB::table('sale_deductions')->where('sale_id', $id)->pluck('id');
        $expenseIds = DB::table('expenses')
            ->where(function ($query) use ($deductionIds, $id): void {
                $query->where(function ($saleExpense) use ($id): void {
                    $saleExpense->where('source_type', 'sale')
                        ->where('source_id', $id);
                });

                if ($deductionIds->isNotEmpty()) {
                    $query->orWhere(function ($deductionExpense) use ($deductionIds): void {
                        $deductionExpense->where('source_type', 'sale_deduction')
                            ->whereIn('source_id', $deductionIds);
                    });
                }
            })
            ->pluck('id');

        if ($expenseIds->isNotEmpty()) {
            DB::table('bank_transactions')
                ->where('source_type', 'expense')
                ->whereIn('source_id', $expenseIds)
                ->delete();

            DB::table('expenses')
                ->whereIn('id', $expenseIds)
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        DB::table('bank_transactions')
            ->where('source_type', 'sale')
            ->where('source_id', $id)
            ->delete();

        DB::table('sale_payments')->where('sale_id', $id)->delete();
        DB::table('sale_deductions')->where('sale_id', $id)->delete();
        DB::table('waiter_incentives')->where('sale_id', $id)->delete();
    }

    /**
     * @return array<string, string>
     */
    private function history(string $module, int $id): array
    {
        if ($module === 'sales') {
            return $this->salesHistory($id);
        }

        $latestActivity = DB::table('activity_logs')
            ->where('module', $module)
            ->where('properties', 'like', '%"id":'.$id.'%')
            ->latest('created_at')
            ->first();

        if (! $latestActivity) {
            return [];
        }

        return [
            'Create' => (string) $latestActivity->description,
            'Update' => 'Last activity at '.(string) $latestActivity->created_at,
            'Delete' => 'No delete record linked.',
            'Payments' => 'No payment data linked.',
            'Reports' => 'No report data linked.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerShowData(int $customerId): array
    {
        $range = request()->string('range', 'all_time')->toString();
        
        [$from, $to] = match ($range) {
            'today' => [\Carbon\Carbon::today()->startOfDay(), \Carbon\Carbon::today()->endOfDay()],
            'yesterday' => [\Carbon\Carbon::yesterday()->startOfDay(), \Carbon\Carbon::yesterday()->endOfDay()],
            'this_week' => [\Carbon\Carbon::now()->startOfWeek(), \Carbon\Carbon::now()->endOfWeek()],
            'last_week' => [\Carbon\Carbon::now()->subWeek()->startOfWeek(), \Carbon\Carbon::now()->subWeek()->endOfWeek()],
            'this_month' => [\Carbon\Carbon::now()->startOfMonth(), \Carbon\Carbon::now()->endOfMonth()],
            'last_month' => [\Carbon\Carbon::now()->subMonth()->startOfMonth(), \Carbon\Carbon::now()->subMonth()->endOfMonth()],
            'custom' => [\Carbon\Carbon::parse(request()->input('from', today()))->startOfDay(), \Carbon\Carbon::parse(request()->input('to', today()))->endOfDay()],
            default => [\Carbon\Carbon::create(2000, 1, 1)->startOfDay(), \Carbon\Carbon::now()->endOfDay()],
        };

        $sales = DB::table('sales')
            ->leftJoin('restaurant_tables', 'restaurant_tables.id', '=', 'sales.restaurant_table_id')
            ->leftJoin('waiters', 'waiters.id', '=', 'sales.waiter_id')
            ->where('sales.customer_id', $customerId)
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->latest('sales.sale_date')
            ->limit(50)
            ->get([
                'sales.id',
                'sales.invoice_no',
                'sales.sale_date',
                'sales.total',
                'sales.paid_amount',
                'sales.due_amount',
                'sales.discount_amount',
                'sales.profit',
                'sales.status',
                'restaurant_tables.number as table_number',
                'waiters.name as waiter_name',
            ]);

        $payments = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.customer_id', $customerId)
            ->whereNull('sales.deleted_at')
            ->whereBetween('sale_payments.paid_at', [$from, $to])
            ->latest('sale_payments.paid_at')
            ->limit(50)
            ->get([
                'sales.invoice_no',
                'sale_payments.payment_method',
                'sale_payments.amount',
                'sale_payments.received_amount',
                'sale_payments.paid_at',
            ]);

        $summary = DB::table('sales')
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw('count(*) as sales_count, coalesce(sum(total), 0) as total_spend, coalesce(sum(paid_amount), 0) as total_paid, coalesce(sum(due_amount), 0) as sale_due, coalesce(sum(discount_amount), 0) as total_discount, max(sale_date) as last_visit')
            ->first();

        $paymentTotal = (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.customer_id', $customerId)
            ->whereNull('sales.deleted_at')
            ->sum('sale_payments.amount');

        $openingBalance = (float) DB::table('customers')->where('id', $customerId)->value('opening_balance');

        return [
            'sales' => $sales,
            'payments' => $payments,
            'summary' => [
                'sales_count' => (int) ($summary->sales_count ?? 0),
                'total_spend' => (float) ($summary->total_spend ?? 0),
                'total_paid' => (float) ($summary->total_paid ?? 0),
                'payment_total' => $paymentTotal,
                'due_balance' => $openingBalance + (float) ($summary->sale_due ?? 0),
                'total_discount' => (float) ($summary->total_discount ?? 0),
                'last_visit' => $summary->last_visit ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productShowData(int $productId): array
    {
        $categoryName = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.id', $productId)
            ->value('categories.name');

        $saleItems = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sale_items.product_id', $productId)
            ->whereNull('sales.deleted_at')
            ->latest('sales.sale_date')
            ->limit(25)
            ->get([
                'sales.invoice_no',
                'sales.sale_date',
                'sales.status',
                'customers.name as customer_name',
                'sale_items.quantity',
                'sale_items.unit_price',
                'sale_items.line_total',
                'sale_items.profit',
            ]);

        $purchaseItems = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchase_items.product_id', $productId)
            ->whereNull('purchases.deleted_at')
            ->latest('purchases.purchase_date')
            ->limit(25)
            ->get([
                'purchases.reference_no',
                'purchases.purchase_date',
                'suppliers.name as supplier_name',
                'purchase_items.quantity',
                'purchase_items.unit_cost',
                'purchase_items.line_total',
            ]);

        $stockMovements = DB::table('stock_movements')
            ->where('product_id', $productId)
            ->latest('created_at')
            ->limit(30)
            ->get(['type', 'quantity', 'balance_after', 'source_type', 'source_id', 'note', 'created_at']);

        $writeOffs = DB::table('damage_write_offs')
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->latest('write_off_date')
            ->limit(15)
            ->get(['quantity', 'cost_amount', 'reason', 'write_off_date', 'note']);

        $salesSummary = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.product_id', $productId)
            ->whereNull('sales.deleted_at')
            ->selectRaw('coalesce(sum(sale_items.quantity), 0) as sold_qty, coalesce(sum(sale_items.line_total), 0) as sales_total, coalesce(sum(sale_items.profit), 0) as profit_total')
            ->first();

        $purchaseSummary = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.product_id', $productId)
            ->whereNull('purchases.deleted_at')
            ->selectRaw('coalesce(sum(purchase_items.quantity), 0) as purchased_qty, coalesce(sum(purchase_items.line_total), 0) as purchase_total')
            ->first();

        return [
            'categoryName' => $categoryName,
            'saleItems' => $saleItems,
            'purchaseItems' => $purchaseItems,
            'stockMovements' => $stockMovements,
            'writeOffs' => $writeOffs,
            'summary' => [
                'sold_qty' => (float) ($salesSummary->sold_qty ?? 0),
                'sales_total' => (float) ($salesSummary->sales_total ?? 0),
                'profit_total' => (float) ($salesSummary->profit_total ?? 0),
                'purchased_qty' => (float) ($purchaseSummary->purchased_qty ?? 0),
                'purchase_total' => (float) ($purchaseSummary->purchase_total ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function salesHistory(int $saleId): array
    {
        $itemCount = (int) DB::table('sale_items')->where('sale_id', $saleId)->count();
        $itemQuantity = (float) DB::table('sale_items')->where('sale_id', $saleId)->sum('quantity');
        $itemTotal = (float) DB::table('sale_items')->where('sale_id', $saleId)->sum('line_total');

        $paymentCount = (int) DB::table('sale_payments')->where('sale_id', $saleId)->count();
        $paymentTotal = (float) DB::table('sale_payments')->where('sale_id', $saleId)->sum('amount');
        $lastPaymentAt = DB::table('sale_payments')->where('sale_id', $saleId)->latest('paid_at')->value('paid_at');

        $deductionIds = DB::table('sale_deductions')->where('sale_id', $saleId)->pluck('id');
        $deductionCount = $deductionIds->count();
        $deductionTotal = (float) DB::table('sale_deductions')->where('sale_id', $saleId)->sum('amount');
        $lastDeductionAt = DB::table('sale_deductions')->where('sale_id', $saleId)->latest('deduction_date')->value('deduction_date');
        $expenseCount = $deductionIds->isNotEmpty()
            ? (int) DB::table('expenses')->where('source_type', 'sale_deduction')->whereIn('source_id', $deductionIds)->whereNull('deleted_at')->count()
            : 0;
        $expenseTotal = $deductionIds->isNotEmpty()
            ? (float) DB::table('expenses')->where('source_type', 'sale_deduction')->whereIn('source_id', $deductionIds)->whereNull('deleted_at')->sum('amount')
            : 0.0;

        $stockMoveCount = (int) DB::table('stock_movements')
            ->where('source_type', 'sale')
            ->where('source_id', $saleId)
            ->count();
        $stockMovedQty = (float) DB::table('stock_movements')
            ->where('source_type', 'sale')
            ->where('source_id', $saleId)
            ->sum(DB::raw('abs(quantity)'));

        $profitAmount = (float) (DB::table('sales')->where('id', $saleId)->value('profit') ?? 0);

        $incentive = DB::table('waiter_incentives')
            ->leftJoin('waiters', 'waiters.id', '=', 'waiter_incentives.waiter_id')
            ->where('sale_id', $saleId)
            ->select('waiter_incentives.amount', 'waiter_incentives.percentage', 'waiters.name as waiter_name')
            ->first();

        $activityCount = (int) DB::table('activity_logs')
            ->where('module', 'sales')
            ->where('properties', 'like', '%"sale_id":'.$saleId.'%')
            ->count();
        $lastActivity = DB::table('activity_logs')
            ->where('module', 'sales')
            ->where('properties', 'like', '%"sale_id":'.$saleId.'%')
            ->latest('created_at')
            ->first();

        return [
            'Sale items' => $itemCount > 0
                ? $itemCount.' item rows, qty '.number_format($itemQuantity, 3).' (Rs. '.number_format($itemTotal, 2).')'
                : 'No sale items linked.',
            'Payments' => $paymentCount > 0
                ? $paymentCount.' payment(s), Rs. '.number_format($paymentTotal, 2).($lastPaymentAt ? ' | Last: '.$lastPaymentAt : '')
                : 'No payments linked.',
            'Deductions' => $deductionCount > 0
                ? $deductionCount.' deduction(s), Rs. '.number_format($deductionTotal, 2).($lastDeductionAt ? ' | Last: '.$lastDeductionAt : '')
                : 'No deductions linked.',
            'Expense records' => $expenseCount > 0
                ? $expenseCount.' expense record(s), Rs. '.number_format($expenseTotal, 2)
                : 'No deduction expenses linked.',
            'Stock effect' => $stockMoveCount > 0
                ? $stockMoveCount.' stock movement(s), qty '.number_format($stockMovedQty, 3)
                : 'No stock movements linked.',
            'Profit records' => 'Profit for this sale: Rs. '.number_format($profitAmount, 2),
            'Waiter incentive' => $incentive
                ? (($incentive->waiter_name ?? 'Waiter').' earned Rs. '.number_format((float) $incentive->amount, 2).' ('.number_format((float) $incentive->percentage, 2).'%)')
                : 'No waiter incentive linked.',
            'Activity log' => $activityCount > 0
                ? $activityCount.' activity log(s) | Last: '.($lastActivity->description ?? 'Updated')
                : 'No activity logs linked.',
        ];
    }

    private function saleChannelLabel(string $channel): string
    {
        return match (strtolower($channel)) {
            'online' => 'Online sale',
            'pos' => 'Normal POS sale',
            default => str($channel)->replace('_', ' ')->headline().' sale',
        };
    }

    private function nextBarcode(): string
    {
        $next = (int) $this->setting('barcode.next_number', 1001);

        DB::table('settings')->updateOrInsert(
            ['group' => 'barcode', 'key' => 'next_number'],
            ['value' => (string) ($next + 1), 'type' => 'integer', 'updated_at' => now(), 'created_at' => now()]
        );

        return str_pad((string) $next, (int) $this->setting('barcode.length', 6), '0', STR_PAD_LEFT);
    }

    private function expenseCategoryId(string $name): int
    {
        $category = DB::table('expense_categories')
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->first();

        if ($category) {
            return (int) $category->id;
        }

        return DB::table('expense_categories')->insertGetId([
            'name' => $name,
            'description' => $name.' expenses',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        [$group, $settingKey] = explode('.', $key, 2);

        return DB::table('settings')->where('group', $group)->where('key', $settingKey)->value('value') ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function waiterShowData(int $waiterId): array
    {
        $range = request()->string('range', 'today')->toString();
        
        [$from, $to] = match ($range) {
            'yesterday' => [\Carbon\Carbon::yesterday()->startOfDay(), \Carbon\Carbon::yesterday()->endOfDay()],
            'this_week' => [\Carbon\Carbon::now()->startOfWeek(), \Carbon\Carbon::now()->endOfWeek()],
            'last_week' => [\Carbon\Carbon::now()->subWeek()->startOfWeek(), \Carbon\Carbon::now()->subWeek()->endOfWeek()],
            'this_month' => [\Carbon\Carbon::now()->startOfMonth(), \Carbon\Carbon::now()->endOfMonth()],
            'last_month' => [\Carbon\Carbon::now()->subMonth()->startOfMonth(), \Carbon\Carbon::now()->subMonth()->endOfMonth()],
            'all_time' => [\Carbon\Carbon::create(2000, 1, 1)->startOfDay(), \Carbon\Carbon::now()->endOfDay()],
            'custom' => [\Carbon\Carbon::parse(request()->input('from', today()))->startOfDay(), \Carbon\Carbon::parse(request()->input('to', today()))->endOfDay()],
            default => [\Carbon\Carbon::today()->startOfDay(), \Carbon\Carbon::today()->endOfDay()],
        };

        $query = DB::table('sales')
            ->leftJoin('restaurant_tables', 'restaurant_tables.id', '=', 'sales.restaurant_table_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.waiter_id', $waiterId)
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to]);

        $sales = (clone $query)
            ->latest('sales.sale_date')
            ->limit(50)
            ->get([
                'sales.id',
                'sales.invoice_no',
                'sales.sale_date',
                'sales.total',
                'sales.paid_amount',
                'sales.due_amount',
                'sales.status',
                'restaurant_tables.number as table_number',
                'customers.name as customer_name',
            ]);

        $stats = [
            'tables_served' => (clone $query)->distinct('sales.restaurant_table_id')->count('sales.restaurant_table_id'),
            'orders_handled' => (clone $query)->count('sales.id'),
            'sales_amount' => (float) (clone $query)->sum('sales.total'),
            'profit_generated' => (float) (clone $query)->sum('sales.profit'),
        ];

        $incentiveAmount = (float) DB::table('waiter_incentives')
            ->join('sales', 'sales.id', '=', 'waiter_incentives.sale_id')
            ->where('sales.waiter_id', $waiterId)
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->sum('waiter_incentives.amount');

        $stats['incentive_amount'] = $incentiveAmount;

        return [
            'sales' => $sales,
            'stats' => $stats,
        ];
    }
}
