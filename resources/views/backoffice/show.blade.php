<x-layouts.app :heading="$config['label'].' Details'" :title="$config['label']">
    @php
        $recordId = $record->id ?? null;

        $statusValue = strtolower((string) ($record->status ?? ''));
        $statusTone = match ($statusValue) {
            'paid', 'active', 'available', 'completed' => 'emerald',
            'due', 'pending', 'hold' => 'amber',
            'deleted', 'cancelled', 'inactive' => 'red',
            default => 'blue',
        };

        $salesPrimaryFields = ['invoice_no', 'customer_id', 'waiter_id', 'restaurant_table_id', 'sale_date', 'subtotal', 'discount_amount', 'total', 'paid_amount', 'due_amount', 'status', 'note'];
        $salesMetaFields = ['customer_id', 'waiter_id', 'restaurant_table_id', 'sale_date'];
        $salesSummaryCards = [
            ['label' => 'Invoice', 'value' => $record->invoice_no ?? '-'],
            ['label' => 'Total', 'value' => isset($record->total) ? number_format((float) $record->total, 2) : '-'],
            ['label' => 'Paid', 'value' => isset($record->paid_amount) ? number_format((float) $record->paid_amount, 2) : '-'],
            ['label' => 'Deducted', 'value' => isset($record->deducted_amount) ? number_format((float) $record->deducted_amount, 2) : '-'],
            ['label' => 'Due', 'value' => isset($record->due_amount) ? number_format((float) $record->due_amount, 2) : '-'],
        ];
    @endphp

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="pos-card overflow-hidden p-0 ring-1 ring-slate-100">
            <div class="border-b border-slate-200 bg-gradient-to-r from-blue-50 via-white to-slate-50 px-5 py-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Record Overview</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-900">{{ $config['label'] }} Record</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">
                            @if($recordId)
                                Reference #{{ $recordId }}
                            @else
                                Record details
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if(($record->status ?? null) !== null)
                            <span @class([
                                'rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide',
                                'bg-emerald-50 text-emerald-700' => $statusTone === 'emerald',
                                'bg-amber-50 text-amber-700' => $statusTone === 'amber',
                                'bg-red-50 text-red-700' => $statusTone === 'red',
                                'bg-blue-50 text-blue-700' => $statusTone === 'blue',
                            ])>
                                {{ $record->status }}
                            </span>
                        @endif
                        @can($config['permission_prefix'].'.edit')
                            <a class="btn-secondary" href="{{ route('backoffice.modules.edit', [$module, $record->id]) }}">Edit</a>
                        @endcan
                        @can($config['permission_prefix'].'.print')
                            <button class="btn-secondary" onclick="window.print()">Print</button>
                        @endcan
                    </div>
                </div>
            </div>

            @if($module === 'sales')
                <div class="grid gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach($salesSummaryCards as $stat)
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                            <p class="text-[11px] font-black uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                            <p class="mt-1 text-lg font-black text-slate-900">{{ $stat['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-3 border-b border-slate-100 bg-white px-5 py-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($salesMetaFields as $name)
                        @php
                            $field = $config['fields'][$name] ?? null;
                            $value = $record->{$name} ?? null;

                            if (($field['type'] ?? null) === 'select' && isset($field['source'])) {
                                $sourceRows = $lookups[$field['source']] ?? collect();
                                $match = $sourceRows->firstWhere('id', $value);
                                $value = $match->name ?? $match->number ?? $value;
                            }

                            if (($field['type'] ?? null) === 'datetime-local' && filled($value)) {
                                $value = \Illuminate\Support\Carbon::parse($value)->format('d M Y, h:i A');
                            }
                        @endphp

                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-black uppercase tracking-wide text-slate-500">{{ $field['label'] ?? str($name)->headline() }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-900">{{ filled($value) ? $value : '-' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="border-b border-slate-100 bg-slate-50/40 px-5 py-3">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                    {{ $module === 'sales' ? 'Record Details' : 'Record Details' }}
                </p>
            </div>

            <dl class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach($config['fields'] as $name => $field)
                    @if(!($field['virtual'] ?? false) && (!in_array($name, $salesPrimaryFields, true) || $module !== 'sales'))
                        @can($config['permission_prefix'].'.field.'.$name)
                            @php
                                $value = $record->{$name} ?? null;

                                if (($field['type'] ?? null) === 'select' && isset($field['source'])) {
                                    $sourceRows = $lookups[$field['source']] ?? collect();
                                    $match = $sourceRows->firstWhere('id', $value);
                                    $value = $match->name ?? $match->number ?? $value;
                                }

                                if (($field['type'] ?? null) === 'datetime-local' && filled($value)) {
                                    $value = \Illuminate\Support\Carbon::parse($value)->format('d M Y, h:i A');
                                }

                                if (($field['type'] ?? null) === 'boolean') {
                                    $value = (bool) $value ? 'Yes' : 'No';
                                }

                                if (is_numeric($value) && in_array($name, ['subtotal', 'discount_amount', 'total', 'paid_amount', 'due_amount', 'profit', 'service_charge', 'tax_amount'], true)) {
                                    $value = number_format((float) $value, 2);
                                }
                                
                                if ($name === 'password') {
                                    $value = '*********';
                                }

                                $display = filled($value) ? $value : '-';
                            @endphp

                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                                <dt class="text-[11px] font-black uppercase tracking-wide text-slate-500">{{ $field['label'] }}</dt>
                                <dd class="mt-2 text-base font-bold text-slate-900">{{ $display }}</dd>
                            </div>
                        @endcan
                    @endif
                @endforeach

                @if($module === 'sales' && filled($record->note ?? null))
                    <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-blue-100 bg-blue-50/60 p-4 shadow-sm">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-blue-700">Note</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ $record->note }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <aside class="pos-card overflow-hidden p-0 ring-1 ring-slate-100">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Linked Activity</p>
                        <h2 class="mt-1 text-lg font-black text-slate-900">Complete History</h2>
                    </div>
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-black text-blue-700">
                        {{ count($config['history'] ?? []) }} Items
                    </span>
                </div>
            </div>

            <div class="space-y-3 p-5">
                @foreach(($config['history'] ?? ['Create', 'Update', 'Delete', 'Payments', 'Reports']) as $item)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 grid size-8 place-items-center rounded-xl bg-blue-50 text-sm font-black text-blue-700">
                                {{ substr($item, 0, 1) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-black text-slate-900">{{ $item }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $history[$item] ?? 'No linked records yet.' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>
</x-layouts.app>
