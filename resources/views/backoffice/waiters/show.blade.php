<x-layouts.app :heading="'Waiter Details - ' . $record->name" title="Waiter Details">
    @php
        $statusValue = (bool) ($record->is_active ?? true);
        
        $salesList = $sales ?? [];
        $waiterStats = $stats ?? [
            'tables_served' => 0,
            'orders_handled' => 0,
            'sales_amount' => 0,
            'profit_generated' => 0,
            'incentive_amount' => 0,
        ];
    @endphp

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <!-- Header Section -->
            <section class="overflow-hidden rounded-3xl bg-white shadow-xl shadow-slate-200/40 ring-1 ring-slate-100">
                <div class="relative overflow-hidden bg-gradient-to-br from-violet-600 via-purple-600 to-fuchsia-500 px-6 py-8 text-white sm:px-8 sm:py-10">
                    <div class="absolute -right-10 -top-10 opacity-20">
                        <x-lucide name="concierge-bell" class="size-64" />
                    </div>
                    
                    <div class="relative flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-widest text-purple-100 shadow-sm">Waiter Profile</p>
                            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-5xl">{{ $record->name }}</h2>
                            <p class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-purple-50">
                                <x-lucide name="phone" class="size-4" />
                                {{ $record->phone ?? 'No phone' }}
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-3">
                            <span @class([
                                'rounded-2xl px-5 py-2 text-sm font-black uppercase tracking-widest shadow-lg backdrop-blur-md',
                                'bg-emerald-400/20 text-emerald-50 ring-1 ring-emerald-300/50' => $statusValue,
                                'bg-slate-500/30 text-slate-50 ring-1 ring-slate-400/50' => !$statusValue,
                            ])>
                                {{ $statusValue ? 'Active' : 'Inactive' }}
                            </span>
                            
                            <div class="flex items-center gap-2">
                                @can('waiters.edit')
                                    <a class="flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/20" href="{{ route('backoffice.modules.edit', ['waiters', $record->id]) }}">
                                        <x-lucide name="edit" class="size-4" /> Edit
                                    </a>
                                @endcan
                                @can('waiters.print')
                                    <button class="flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-purple-600 shadow-lg transition hover:bg-purple-50" onclick="window.print()">
                                        <x-lucide name="printer" class="size-4" /> Print
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Highlights -->
                <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 bg-white sm:grid-cols-4 sm:divide-y-0">
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-slate-400">Orders Handled</p>
                        <p class="mt-2 text-2xl font-black text-slate-800">{{ number_format($waiterStats['orders_handled']) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-blue-500">Sales Amount</p>
                        <p class="mt-2 text-2xl font-black text-blue-600">{{ number_format($waiterStats['sales_amount'], 2) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-emerald-500">Profit Generated</p>
                        <p class="mt-2 text-2xl font-black text-emerald-600">{{ number_format($waiterStats['profit_generated'], 2) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-fuchsia-500">Incentive Earned</p>
                        <p class="mt-2 text-2xl font-black text-fuchsia-600">{{ number_format($waiterStats['incentive_amount'], 2) }}</p>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="border-t border-slate-100 bg-slate-50/50 p-5 sm:p-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400">NIC</p>
                            <p class="mt-1 font-bold text-slate-800">{{ filled($record->nic) ? $record->nic : '-' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Incentive Percentage</p>
                            <p class="mt-1 font-bold text-slate-800">{{ number_format($record->incentive_percentage, 2) }}%</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Available in POS</p>
                            <p class="mt-1 font-bold text-slate-800">{{ $record->is_available ? 'Yes' : 'No' }}</p>
                        </div>
                    </div>
                    @if(filled($record->address))
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">Address</p>
                            <p class="mt-1 text-sm font-semibold text-slate-700">{{ $record->address }}</p>
                        </div>
                    @endif
                </div>
            </section>

            <!-- Sales List Section -->
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-lg shadow-slate-200/40">
                <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
                    <h3 class="text-lg font-black text-slate-800">Sales Handled (Max 50)</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Invoice / Date</th>
                                <th class="px-6 py-4">Customer</th>
                                <th class="px-6 py-4">Table</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($salesList as $sale)
                                <tr class="transition hover:bg-slate-50/50">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('backoffice.modules.show', ['sales', $sale->id]) }}" class="font-bold text-purple-600 hover:underline">{{ $sale->invoice_no }}</a>
                                        <p class="mt-0.5 text-xs font-medium text-slate-500">{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y, h:i A') }}</p>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-slate-700">{{ $sale->customer_name ?? 'Walk-In' }}</td>
                                    <td class="px-6 py-4">
                                        @if($sale->table_number)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">
                                                <x-lucide name="utensils" class="size-3" />
                                                {{ $sale->table_number }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $saleStatusTone = match (strtolower($sale->status)) {
                                                'paid' => 'bg-emerald-50 text-emerald-700',
                                                'due' => 'bg-rose-50 text-rose-700',
                                                'deleted' => 'bg-slate-50 text-slate-700',
                                                default => 'bg-blue-50 text-blue-700',
                                            };
                                        @endphp
                                        <span class="rounded-full px-2.5 py-1 text-xs font-black uppercase tracking-wide {{ $saleStatusTone }}">
                                            {{ $sale->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-black text-slate-900">{{ number_format($sale->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500 font-semibold">No sales handled in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Sidebar Activity / Summary -->
        <aside class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-lg shadow-slate-200/40">
                <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-fuchsia-500">Summary Info</p>
                            <h2 class="mt-1 text-lg font-black text-slate-900">Period Metrics</h2>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-violet-50 text-violet-600 ring-violet-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                T
                            </div>
                            <div class="mt-2 w-px flex-1 bg-slate-200"></div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Tables Handled</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Unique tables served: {{ number_format($waiterStats['tables_served']) }}</p>
                        </div>
                    </div>
                    
                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-blue-50 text-blue-600 ring-blue-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                O
                            </div>
                            <div class="mt-2 w-px flex-1 bg-slate-200"></div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Orders Handled</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Total orders: {{ number_format($waiterStats['orders_handled']) }}</p>
                        </div>
                    </div>
                    
                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-emerald-50 text-emerald-600 ring-emerald-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                S
                            </div>
                            <div class="mt-2 w-px flex-1 bg-slate-200"></div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Sales Generated</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Total amount: {{ number_format($waiterStats['sales_amount'], 2) }}</p>
                        </div>
                    </div>

                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-amber-50 text-amber-600 ring-amber-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                P
                            </div>
                            <div class="mt-2 w-px flex-1 bg-slate-200"></div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Profit Generated</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Net profit margin: {{ number_format($waiterStats['profit_generated'], 2) }}</p>
                        </div>
                    </div>
                    
                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-fuchsia-50 text-fuchsia-600 ring-fuchsia-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                I
                            </div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Incentive Total</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Amount earned: {{ number_format($waiterStats['incentive_amount'], 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</x-layouts.app>
