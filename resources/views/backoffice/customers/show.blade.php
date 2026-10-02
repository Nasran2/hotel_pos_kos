<x-layouts.app :heading="'Customer Details - ' . $record->name" title="Customer Details">
    @php
        $statusValue = (bool) ($record->is_active ?? true);
        
        $salesList = $sales ?? [];
        $paymentList = $payments ?? [];
        $customerSummary = (object) ($summary ?? [
            'sales_count' => 0,
            'total_spend' => 0,
            'total_paid' => 0,
            'due_balance' => 0,
            'total_discount' => 0,
            'last_visit' => null,
        ]);
    @endphp

    <x-slot name="pageFilter">
        <x-date-filter />
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <!-- Header Section -->
            <section class="overflow-hidden rounded-3xl bg-white shadow-xl shadow-slate-200/40 ring-1 ring-slate-100">
                <div class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-blue-600 to-sky-500 px-6 py-8 text-white sm:px-8 sm:py-10">
                    <div class="absolute -right-10 -top-10 opacity-20">
                        <x-lucide name="users" class="size-64" />
                    </div>
                    
                    <div class="relative flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-widest text-blue-100 shadow-sm">Customer Profile</p>
                            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-5xl">{{ $record->name }}</h2>
                            <p class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-50">
                                <x-lucide name="phone" class="size-4" />
                                {{ $record->phone ?? 'No phone' }}
                            </p>
                            @if(filled($record->email))
                                <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-50">
                                    <x-lucide name="mail" class="size-4" />
                                    {{ $record->email }}
                                </p>
                            @endif
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
                                @can('customers.edit')
                                    <a class="flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/20" href="{{ route('backoffice.modules.edit', ['customers', $record->id]) }}">
                                        <x-lucide name="edit" class="size-4" /> Edit
                                    </a>
                                @endcan
                                @can('customers.print')
                                    <button class="flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-blue-600 shadow-lg transition hover:bg-blue-50" onclick="window.print()">
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
                        <p class="text-xs font-black uppercase tracking-widest text-slate-400">Total Spend</p>
                        <p class="mt-2 text-2xl font-black text-slate-800">{{ number_format($customerSummary->total_spend, 2) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-emerald-500">Total Paid</p>
                        <p class="mt-2 text-2xl font-black text-emerald-600">{{ number_format($customerSummary->total_paid, 2) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-rose-500">Due Balance</p>
                        <p class="mt-2 text-2xl font-black text-rose-600">{{ number_format($customerSummary->due_balance, 2) }}</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-amber-500">Total Discount</p>
                        <p class="mt-2 text-2xl font-black text-amber-600">{{ number_format($customerSummary->total_discount, 2) }}</p>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="border-t border-slate-100 bg-slate-50/50 p-5 sm:p-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Customer Type</p>
                            <p class="mt-1 font-bold text-slate-800">{{ ($record->is_walk_in ?? false) ? 'Walk-in' : 'Registered' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Opening Balance</p>
                            <p class="mt-1 font-bold text-slate-800">{{ number_format($record->opening_balance, 2) }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Default Discount</p>
                            <p class="mt-1 font-bold text-slate-800">{{ number_format($record->default_discount, 2) }}</p>
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
                    <h3 class="text-lg font-black text-slate-800">Sales History</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Invoice / Date</th>
                                <th class="px-6 py-4">Table</th>
                                <th class="px-6 py-4 text-right">Total</th>
                                <th class="px-6 py-4 text-right">Paid</th>
                                <th class="px-6 py-4 text-right">Due</th>
                                <th class="px-6 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($salesList as $sale)
                                <tr class="transition hover:bg-slate-50/50">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('backoffice.modules.show', ['sales', $sale->id]) }}" class="font-bold text-blue-600 hover:underline">{{ $sale->invoice_no }}</a>
                                        <p class="mt-0.5 text-xs font-medium text-slate-500">{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y, h:i A') }}</p>
                                    </td>
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
                                    <td class="px-6 py-4 text-right font-black text-slate-900">{{ number_format($sale->total, 2) }}</td>
                                    <td class="px-6 py-4 text-right font-semibold text-slate-700">{{ number_format($sale->paid_amount, 2) }}</td>
                                    <td class="px-6 py-4 text-right font-semibold {{ $sale->due_amount > 0 ? 'text-rose-600' : 'text-slate-500' }}">{{ number_format($sale->due_amount, 2) }}</td>
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
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-slate-500 font-semibold">No sales found for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Payment History Section -->
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-lg shadow-slate-200/40">
                <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
                    <h3 class="text-lg font-black text-slate-800">Payment History</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Invoice</th>
                                <th class="px-6 py-4">Method</th>
                                <th class="px-6 py-4 text-right">Received</th>
                                <th class="px-6 py-4 text-right">Applied</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($paymentList as $payment)
                                <tr class="transition hover:bg-slate-50/50">
                                    <td class="px-6 py-4 font-semibold text-slate-800">{{ \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d M Y, h:i A') }}</td>
                                    <td class="px-6 py-4 font-bold text-slate-600">{{ $payment->invoice_no }}</td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold uppercase tracking-wide text-slate-700">
                                            {{ $payment->payment_method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-black text-slate-900">{{ number_format($payment->received_amount, 2) }}</td>
                                    <td class="px-6 py-4 text-right font-black text-emerald-600">{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500 font-semibold">No payments found for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Sidebar Summary -->
        <aside class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-lg shadow-slate-200/40">
                <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-blue-500">Summary Info</p>
                            <h2 class="mt-1 text-lg font-black text-slate-900">Period Metrics</h2>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-indigo-50 text-indigo-600 ring-indigo-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                I
                            </div>
                            <div class="mt-2 w-px flex-1 bg-slate-200"></div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Invoices Generated</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Total sales count: {{ number_format($customerSummary->sales_count) }}</p>
                        </div>
                    </div>
                    
                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-emerald-50 text-emerald-600 ring-emerald-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                P
                            </div>
                            <div class="mt-2 w-px flex-1 bg-slate-200"></div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Total Paid</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Amount collected: {{ number_format($customerSummary->total_paid, 2) }}</p>
                        </div>
                    </div>
                    
                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-rose-50 text-rose-600 ring-rose-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                D
                            </div>
                            <div class="mt-2 w-px flex-1 bg-slate-200"></div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Amount Due</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">Outstanding balance: {{ number_format($customerSummary->due_balance, 2) }}</p>
                        </div>
                    </div>

                    <div class="group flex gap-4">
                        <div class="relative flex flex-col items-center">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl ring-1 bg-slate-50 text-slate-600 ring-slate-200 font-black text-sm shadow-sm transition group-hover:scale-110">
                                V
                            </div>
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-black text-slate-800">Last Visit</p>
                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">
                                {{ $customerSummary->last_visit ? \Illuminate\Support\Carbon::parse($customerSummary->last_visit)->format('d M Y, h:i A') : 'No visits yet' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</x-layouts.app>
