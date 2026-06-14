<x-layouts.app :heading="$config['label'].' Details'" :title="$config['label']">
    @php
        $money = fn ($amount) => 'Rs. '.number_format((float) $amount, 2);
        $qty = fn ($amount) => rtrim(rtrim(number_format((float) $amount, 3), '0'), '.');
        $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y, h:i A') : '-';
        $isLowStock = (bool) $record->maintain_stock && (float) $record->stock_quantity <= (float) $record->alert_quantity;
    @endphp

    <div class="space-y-5">
        <section class="pos-card overflow-hidden p-0">
            <div class="border-b border-slate-200 bg-gradient-to-r from-blue-50 via-white to-slate-50 px-5 py-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Product Overview</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-900">{{ $record->name }}</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">{{ $categoryName ?: 'No category' }} • {{ $record->barcode ?: 'No barcode' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @can($config['permission_prefix'].'.edit')
                            <a class="btn-secondary" href="{{ route('backoffice.modules.edit', [$module, $record->id]) }}">Edit</a>
                        @endcan
                        <button class="btn-secondary" onclick="window.print()">Print</button>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 border-b border-slate-100 bg-white p-5 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase text-slate-500">Selling Price</p>
                    <p class="mt-2 font-black text-slate-900">{{ $money($record->selling_price) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase text-slate-500">Cost Price</p>
                    <p class="mt-2 font-black text-slate-900">{{ $money($record->cost_price) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase text-slate-500">Current Stock</p>
                    <p class="mt-2 font-black {{ $isLowStock ? 'text-red-600' : 'text-emerald-700' }}">{{ $qty($record->stock_quantity) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase text-slate-500">Alert Quantity</p>
                    <p class="mt-2 font-black text-slate-900">{{ $qty($record->alert_quantity) }}</p>
                </div>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-5">
                @foreach([
                    ['Sold Qty', $qty($summary['sold_qty'])],
                    ['Sales Total', $money($summary['sales_total'])],
                    ['Total Profit', $money($summary['profit_total'])],
                    ['Purchased Qty', $qty($summary['purchased_qty'])],
                    ['Purchase Total', $money($summary['purchase_total'])],
                ] as [$label, $value])
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-black uppercase tracking-wide text-slate-500">{{ $label }}</p>
                        <p class="mt-2 text-base font-black text-slate-900">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="pos-card p-0">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-lg font-black text-slate-900">Sales History</h3>
            </div>
            <div class="data-table-wrap">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Qty</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($saleItems as $item)
                            <tr>
                                <td class="px-4 py-3 font-black">{{ $item->invoice_no }}</td>
                                <td class="px-4 py-3">{{ $date($item->sale_date) }}</td>
                                <td class="px-4 py-3">{{ $item->customer_name ?: 'Walk-in Customer' }}</td>
                                <td class="px-4 py-3">{{ $qty($item->quantity) }}</td>
                                <td class="px-4 py-3">{{ $money($item->line_total) }}</td>
                                <td class="px-4 py-3 font-black text-emerald-700">{{ $money($item->profit) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center font-semibold text-slate-500">No sales history yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="pos-card p-0">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-lg font-black text-slate-900">Purchase History</h3>
                </div>
                <div class="data-table-wrap">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">Supplier</th>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchaseItems as $item)
                                <tr>
                                    <td class="px-4 py-3 font-black">{{ $item->reference_no ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $item->supplier_name ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $qty($item->quantity) }}</td>
                                    <td class="px-4 py-3">{{ $money($item->line_total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center font-semibold text-slate-500">No purchases yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="pos-card p-0">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-lg font-black text-slate-900">Stock Movement</h3>
                </div>
                <div class="data-table-wrap">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockMovements as $movement)
                                <tr>
                                    <td class="px-4 py-3">{{ $date($movement->created_at) }}</td>
                                    <td class="px-4 py-3">{{ str($movement->type)->headline() }}</td>
                                    <td class="px-4 py-3 font-black {{ (float) $movement->quantity < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $qty($movement->quantity) }}</td>
                                    <td class="px-4 py-3">{{ $qty($movement->balance_after) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center font-semibold text-slate-500">No stock movements yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
