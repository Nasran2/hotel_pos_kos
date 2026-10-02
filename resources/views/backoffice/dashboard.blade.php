<x-layouts.app heading="Dashboard" title="Dashboard">
    @php
        $summaryCards = [
            ['key' => 'total_sales', 'label' => 'Total Sales', 'icon' => 'wallet', 'tone' => 'blue', 'hint' => 'Range revenue'],
            ['key' => 'profit_loss', 'label' => 'Profit / Loss', 'icon' => 'trending-up', 'tone' => ($values['profit_loss'] ?? 0) < 0 ? 'red' : 'green', 'hint' => ($values['profit_loss'] ?? 0) < 0 ? 'Needs review' : 'Net result'],
            ['key' => 'total_expenses', 'label' => 'Total Expenses', 'icon' => 'badge-dollar-sign', 'tone' => 'orange', 'hint' => 'Recorded spend'],
            ['key' => 'total_orders', 'label' => 'Orders', 'icon' => 'package', 'tone' => 'blue', 'isCurrency' => false, 'hint' => 'Completed bills'],
            ['key' => 'total_takeaway_orders', 'label' => 'Takeaway orders', 'icon' => 'receipt', 'tone' => 'blue', 'isCurrency' => false, 'hint' => 'Takeaway service'],
            ['key' => 'online_orders', 'label' => 'Online Orders', 'icon' => 'smartphone', 'tone' => 'indigo', 'isCurrency' => false, 'hint' => 'Platform orders'],
            ['key' => 'online_sales', 'label' => 'Online Sales', 'icon' => 'globe', 'tone' => 'cyan', 'hint' => 'Gross online value'],
            ['key' => 'online_cod_pending', 'label' => 'Online COD Pending', 'icon' => 'hourglass', 'tone' => 'orange', 'hint' => 'Awaiting collection'],
            ['key' => 'online_net_profit', 'label' => 'Online Net Profit', 'icon' => 'line-chart', 'tone' => 'green', 'hint' => 'After commission'],
            ['key' => 'cash_balance', 'label' => 'Cash Balance', 'icon' => 'banknote', 'tone' => 'green', 'hint' => 'Drawer value'],
            ['key' => 'bank_balance', 'label' => 'Bank Balance', 'icon' => 'landmark', 'tone' => 'blue', 'hint' => 'Bank movement'],
            ['key' => 'hold_orders', 'label' => 'Hold Orders', 'icon' => 'pause-circle', 'tone' => 'orange', 'isCurrency' => false, 'hint' => 'Open tables'],
        ];

        $currency = 'Rs.';
        $totalTopSales = max(1, (float) $topSellingItems->sum('quantity'));
        $isNegativeGrowth = $balanceGrowth < 0;
    @endphp

    <div class="page-intro">
        <div><p class="eyebrow">YOUR RESTAURANT AT A GLANCE</p><h2>Every detail. One place.</h2><p>Sales, service, and the numbers that keep your business moving.</p></div>
        @can('kitchen.view')<a href="{{ route('kod.index') }}" class="service-badge"><x-lucide name="cooking-pot" class="size-5" /> Kitchen display <span>↗</span></a>@endcan
    </div>
    <div class="space-y-6 dashboard-content">
        <section class="dashboard-metrics grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($summaryCards as $card)
                <article class="summary-card {{ $loop->index > 3 ? 'summary-card-compact' : '' }} summary-card-{{ $card['tone'] }}">
                    <div class="flex h-full items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="summary-card-title">{{ $card['label'] }}</p>
                            <p class="summary-card-value {{ $card['key'] === 'profit_loss' && ($values['profit_loss'] ?? 0) < 0 ? 'text-red-600' : '' }}">
                                @if(($card['isCurrency'] ?? true) === false)
                                    {{ number_format((float) ($values[$card['key']] ?? 0), 0) }}
                                @else
                                    {{ $currency }} {{ number_format((float) ($values[$card['key']] ?? 0), 2) }}
                                @endif
                            </p>

                            <p class="summary-card-trend">
                                {{ $card['hint'] }}
                            </p>
                        </div>

                        <div class="summary-card-icon">
                            <x-lucide :name="$card['icon']" class="size-6" />
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]">
            <article class="pos-card min-w-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Revenue Overview</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Sales and profit for the recent months.</p>
                    </div>
                    <a href="{{ route('reports.show', 'sales') }}" class="text-sm font-bold text-slate-400 transition hover:text-blue-600">See All</a>
                </div>

                <div class="mt-5 grid gap-5 2xl:grid-cols-[15rem_minmax(0,1fr)]">
                    <div class="grid content-start gap-4 rounded-lg border border-slate-100 bg-slate-50 p-4">
                        @foreach($weekProgress as $label => $value)
                            <div>
                                <div class="mb-2 flex items-center justify-between text-sm font-bold text-slate-500">
                                    <span>{{ $label }}</span>
                                    <span>{{ number_format((float) $value / 1000, 1) }}K</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-value" style="width: {{ min(100, ((float) $value / $weekProgressMax) * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="h-72 min-w-0 rounded-lg border border-slate-100 bg-slate-50 p-4">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </article>

            <article class="pos-card min-w-0 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-bold text-slate-500">Balance</p>
                        <p class="mt-1 text-3xl font-black text-slate-800">{{ $currency }} {{ number_format((float) (($values['cash_balance'] ?? 0) + ($values['bank_balance'] ?? 0)), 2) }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-black {{ $isNegativeGrowth ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }}">
                        {{ $isNegativeGrowth ? '↓' : '↑' }} {{ number_format(abs($balanceGrowth), 2) }}%
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <p class="text-slate-500">Income</p>
                        <p class="mt-1 font-black text-slate-800">{{ $currency }} {{ number_format((float) ($values['total_sales'] ?? 0), 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-red-50 px-3 py-2">
                        <p class="text-red-500">Expenses</p>
                        <p class="mt-1 font-black text-red-600">{{ $currency }} {{ number_format((float) ($values['total_expenses'] ?? 0), 2) }}</p>
                    </div>
                </div>

                <div class="mx-auto h-56 w-full max-w-[16rem]">
                    <canvas id="balanceDonut"></canvas>
                </div>

                <div class="space-y-2">
                    <h3 class="text-sm font-black uppercase tracking-wide text-slate-500">Top selling items</h3>
                    @forelse($topSellingItems as $item)
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                            <span class="font-semibold text-slate-700">{{ $item->product_name }}</span>
                            <span class="font-black text-emerald-600">↑ {{ number_format(((float) $item->quantity / $totalTopSales) * 100, 1) }}%</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No items sold in this period.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
            <article class="pos-card min-w-0">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Waiter Performance</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Sales, profit, and incentives by waiter.</p>
                    </div>
                    <a href="{{ route('backoffice.modules.index', 'waiters') }}" class="text-sm font-bold text-slate-400 transition hover:text-blue-600">See All</a>
                </div>

                <div class="data-table-wrap">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th>Waiter Name</th>
                                <th>Tables handled</th>
                                <th>Orders count</th>
                                <th>Sales amount</th>
                                <th>Profit generated</th>
                                <th>Incentive</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($waiterSummary as $row)
                                @php
                                    $status = (int) $row->orders === 0 ? 'Offline' : ((int) $row->orders > 8 ? 'Busy' : 'Active');
                                    $statusClass = $status === 'Busy' ? 'badge-warning' : ($status === 'Offline' ? 'badge-danger' : 'badge-success');
                                @endphp
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td>{{ number_format((int) $row->tables_handled) }}</td>
                                    <td>{{ number_format((int) $row->orders) }}</td>
                                    <td>{{ $currency }} {{ number_format((float) $row->total, 2) }}</td>
                                    <td>{{ $currency }} {{ number_format((float) $row->profit, 2) }}</td>
                                    <td>{{ $currency }} {{ number_format((float) $row->incentive, 2) }}</td>
                                    <td><span class="badge {{ $statusClass }}">{{ $status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-slate-500">No waiter activity for this range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="pos-card min-w-0">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Activity Log</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Latest system actions.</p>
                    </div>
                    <a href="{{ route('activity-logs.index') }}" class="text-sm font-bold text-slate-400 transition hover:text-blue-600">See All</a>
                </div>
                <div class="activity-feed max-h-[31rem] space-y-3 overflow-y-auto pr-1">
                    @forelse($activityItems as $activity)
                        <article class="activity-item">
                            <div class="activity-avatar">{{ strtoupper(substr($activity->user_name ?? 'S', 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-700">{{ $activity->description }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ str($activity->module)->headline() }} • {{ str($activity->action)->headline() }}</p>
                            </div>
                            <time class="shrink-0 text-xs font-semibold text-slate-400">{{ \Carbon\Carbon::parse($activity->created_at)->format('H:i') }}</time>
                        </article>
                    @empty
                        <p class="text-sm text-slate-500">No activity yet.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
        <script>
            (() => {
                if (typeof Chart === 'undefined') {
                    return;
                }

                const revenueCanvas = document.getElementById('revenueChart');
                if (revenueCanvas) {
                    new Chart(revenueCanvas, {
                        type: 'bar',
                        data: {
                            labels: @json($chartLabels),
                            datasets: [
                                { label: 'Sales', data: @json($salesSeries), borderRadius: 10, backgroundColor: '#2563EB' },
                                { label: 'Profit', data: @json($profitSeries), borderRadius: 10, backgroundColor: '#D4A843' },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top' } },
                            scales: {
                                x: { grid: { display: false } },
                                y: { grid: { color: '#e2e8f0' }, ticks: { color: '#64748b' } },
                            },
                        },
                    });
                }

                const donutCanvas = document.getElementById('balanceDonut');
                if (donutCanvas) {
                    new Chart(donutCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: ['Cash', 'Bank', 'Expenses'],
                            datasets: [{
                                data: [
                                    Number(@json((float) ($values['cash_balance'] ?? 0))),
                                    Number(@json((float) ($values['bank_balance'] ?? 0))),
                                    Number(@json((float) ($values['total_expenses'] ?? 0))),
                                ],
                                backgroundColor: ['#0F2747', '#16A34A', '#DC2626'],
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            cutout: '72%',
                            plugins: { legend: { display: false } },
                        },
                    });
                }
            })();
        </script>
    @endpush
</x-layouts.app>
