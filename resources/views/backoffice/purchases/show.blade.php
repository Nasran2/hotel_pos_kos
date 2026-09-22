<x-layouts.app :heading="'Purchase Details - ' . ($record->reference_no ?? 'No Ref')" title="Purchase Details">
    @php
    // Resolve supplier name
    $supplierName = '-';
    if (filled($record->supplier_id)) {
    $supplierName = collect($lookups['suppliers'] ?? [])->firstWhere('id', $record->supplier_id)->name ?? '-';
    }

    $purchaseDate = \Illuminate\Support\Carbon::parse($record->purchase_date)->format('d M Y, h:i A');

    $statusTone = $record->due_amount > 0 ? 'rose' : 'emerald';
    $statusText = $record->due_amount > 0 ? 'Due' : 'Paid';
    @endphp

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <!-- Header Section -->
            <section class="overflow-hidden rounded-3xl bg-white shadow-xl shadow-slate-200/40 ring-1 ring-slate-100">
                <div class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-blue-600 to-sky-500 px-6 py-8 text-white sm:px-8 sm:py-10">
                    <div class="absolute -right-10 -top-10 opacity-20">
                        <x-lucide name="shopping-cart" class="size-64" />
                    </div>

                    <div class="relative flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-widest text-blue-100 shadow-sm">Reference No</p>
                            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-5xl">{{ $record->reference_no ?: 'No Reference' }}</h2>
                            <p class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-50">
                                <x-lucide name="calendar" class="size-4" />
                                {{ $purchaseDate }}
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-3">
                            <span @class([ 'rounded-2xl px-5 py-2 text-sm font-black uppercase tracking-widest shadow-lg backdrop-blur-md' , 'bg-emerald-400/20 text-emerald-50 ring-1 ring-emerald-300/50'=> $statusTone === 'emerald',
                                'bg-rose-500/30 text-rose-50 ring-1 ring-rose-400/50' => $statusTone === 'rose',
                                ])>
                                {{ $statusText }}
                            </span>

                            <div class="flex items-center gap-2">
                                @can('purchases.edit')
                                <a class="flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/20" href="{{ route('backoffice.modules.edit', ['purchases', $record->id]) }}">
                                    <x-lucide name="edit" class="size-4" /> Edit
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Highlights -->
                <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 bg-white sm:grid-cols-4 sm:divide-y-0">
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-slate-400">Total</p>
                        <p class="mt-2 text-2xl font-black text-slate-800">{{ number_format($record->grand_total, 2) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-emerald-500">Paid</p>
                        <p class="mt-2 text-2xl font-black text-emerald-600">{{ number_format($record->paid_amount, 2) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-rose-500">Due</p>
                        <p class="mt-2 text-2xl font-black text-rose-600">{{ number_format($record->due_amount, 2) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-slate-400">Discount</p>
                        <p class="mt-2 text-2xl font-black text-slate-800">{{ number_format($record->discount_total, 2) }}</p>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="border-t border-slate-100 bg-slate-50/50 p-5 sm:p-6">
                    <div class="grid gap-4 sm:grid-cols-1">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Supplier</p>
                            <p class="mt-1 font-bold text-slate-800">{{ $supplierName }}</p>
                        </div>
                    </div>
                    @if(filled($record->notes))
                    <div class="mt-4 rounded-2xl border border-blue-100 bg-blue-50/60 p-4 shadow-sm">
                        <p class="text-xs font-black uppercase tracking-widest text-blue-600">Note</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ $record->notes }}</p>
                    </div>
                    @endif
                </div>
            </section>

            <!-- Purchase Items Section -->
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-lg shadow-slate-200/40">
                <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
                    <h3 class="text-lg font-black text-slate-800">Purchased Items</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Item</th>
                                <th class="px-6 py-4 text-right">Unit Cost</th>
                                <th class="px-6 py-4 text-right">Qty</th>
                                <th class="px-6 py-4 text-right">Discount</th>
                                <th class="px-6 py-4 text-right">Selling Price</th>
                                <th class="px-6 py-4 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($record->items as $item)
                            <tr class="transition hover:bg-slate-50/50">
                                <td class="px-6 py-4">
                                    <p class="font-bold text-slate-800">{{ $item->product_name ?? 'Unknown Product' }}</p>
                                    <p class="mt-0.5 text-xs font-medium text-slate-500">Barcode: {{ $item->barcode ?? '-' }}</p>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-600">{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="px-6 py-4 text-right font-black text-blue-600">{{ (float) $item->quantity }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-rose-500">{{ $item->discount_amount > 0 ? number_format($item->discount_amount, 2) : '-' }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-600">{{ $item->new_selling_price ? number_format($item->new_selling_price, 2) : '-' }}</td>
                                <td class="px-6 py-4 text-right font-black text-slate-900">{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-500 font-semibold">No items found for this purchase.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-slate-50/50 border-t border-slate-200">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-right text-xs font-black uppercase tracking-widest text-slate-500">Subtotal</td>
                                <td class="px-6 py-4 text-right font-black text-slate-900">{{ number_format($record->subtotal, 2) }}</td>
                            </tr>
                            @if($record->discount_total > 0)
                            <tr>
                                <td colspan="5" class="px-6 py-3 text-right text-xs font-black uppercase tracking-widest text-rose-500">Total Discount</td>
                                <td class="px-6 py-3 text-right font-black text-rose-600">-{{ number_format($record->discount_total, 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="5" class="px-6 py-5 text-right text-sm font-black uppercase tracking-widest text-slate-800">Grand Total</td>
                                <td class="px-6 py-5 text-right text-xl font-black text-blue-600">{{ number_format($record->grand_total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <!-- Sidebar / Linked Activity (Payments, Returns, etc) -->
        <aside class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-lg shadow-slate-200/40">
                <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-indigo-500">Linked Activity</p>
                            <h2 class="mt-1 text-lg font-black text-slate-900">Payments & More</h2>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-emerald-50 text-emerald-600 ring-emerald-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                P
                            </div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Payments</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Manage payments for this purchase.</p>
                            <a href="{{ route('backoffice.modules.index', ['purchase_payments', 'purchase_id' => $record->id]) }}" class="mt-3 inline-block rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-200">View Payments</a>
                        </div>
                    </div>

                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-rose-50 text-rose-600 ring-rose-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                R
                            </div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Purchase Returns</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Manage returns for this purchase.</p>
                            <a href="{{ route('backoffice.modules.index', ['purchase_returns', 'purchase_id' => $record->id]) }}" class="mt-3 inline-block rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-200">View Returns</a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</x-layouts.app>