@php
    $range = request('range', 'today');
    $fromValue = request('from', $from->toDateString());
    $toValue = request('to', $to->toDateString());
    $countLabels = ['active waiters', 'sales count', 'records', 'transactions', 'products', 'due bills', 'invoices'];
    $categoryReports = ['sales', 'due-bills', 'profit-loss', 'stock', 'damage-write-offs', 'product-sales'];
    $showCategory = in_array($report, $categoryReports, true);
    $exportQuery = request()->query();

    $tableHeadings = match ($report) {
        'sales', 'due-bills' => ['Date', 'Invoice', 'Customer', 'Total', 'Paid', 'Due', 'Status'],
        'purchases', 'supplier-due' => ['Date', 'Reference', 'Supplier', 'Total', 'Paid', 'Due'],
        'expenses', 'card-fees' => ['Date', 'Category', 'Method', 'Amount', 'Note'],
        'cash-flow' => ['Date', 'Type', 'Reference', 'Method / Party', 'Cash In', 'Outflow'],
        't-accounts' => ['Date', 'Reference', 'Description', 'Debit Account', 'Credit Account', 'Debit', 'Credit', 'Download'],
        'waiters' => ['Waiter Name', 'Sales Count', 'Last Incentive', 'Sales Amount', 'Total Incentive'],
        'stock' => ['Barcode', 'Product', 'Last Update', 'Stock Qty', 'Alert Qty'],
        'product-sales' => ['Product Name', 'Sale Count', 'Total Sale Amount', 'Total Cost', 'Total Profit', 'Action'],
        'payment-methods', 'qr-payments' => ['Method', 'Transactions', 'Last Payment', 'Total', 'Fee'],
        'online-orders' => ['Date', 'Reference', 'Platform', 'Total', 'Paid', 'Balance'],
        'register-closing' => ['Closed At', 'Cashier', 'Opened At', 'Expected', 'Actual', 'Difference'],
        default => ['Reference', 'Party / Qty', 'Date', 'Total', 'Paid / Profit', 'Due / Fee'],
    };
@endphp

<x-layouts.app :heading="$title" :title="$title" :show-date-filter="false">
    <div class="space-y-5">
        <form method="GET" class="pos-card">
            @if($report === 'cash-flow' && request('account_flow'))
                <input type="hidden" name="account_flow" value="{{ request('account_flow') }}">
            @endif
            <div class="flex flex-wrap items-end gap-4">
                <label class="grid gap-1">
                    <span class="text-sm font-black text-slate-600">From</span>
                    <input class="form-control min-w-52" type="date" name="from" value="{{ $fromValue }}" data-date-from required>
                </label>
                <label class="grid gap-1">
                    <span class="text-sm font-black text-slate-600">To</span>
                    <input class="form-control min-w-52" type="date" name="to" value="{{ $toValue }}" data-date-to required>
                </label>
                <label class="grid gap-1">
                    <span class="text-sm font-black text-slate-600">Quick Filter</span>
                    <select class="form-control min-w-60" name="range" data-date-range>
                        @foreach(config('hotelpos.date_filters') as $value => $label)
                            <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @if($showCategory)
                    <label class="grid gap-1">
                        <span class="text-sm font-black text-slate-600">Category</span>
                        <select class="form-control min-w-64" name="category_id">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) $categoryId === (int) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if($report === 't-accounts')
                    <label class="grid gap-1">
                        <span class="text-sm font-black text-slate-600">T Account</span>
                        <select class="form-control min-w-64" name="t_account">
                            @foreach($tAccounts as $value => $label)
                                <option value="{{ $value }}" @selected($tAccount === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button class="btn-primary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('reports.show', $report) }}">Reset</a>
                @can("reports.$report.export")
                    @if($report === 't-accounts')
                        <a class="btn-primary bg-emerald-600 hover:bg-emerald-700" href="{{ route('reports.export', ['report' => $report, 'format' => 'excel'] + $exportQuery) }}">Excel</a>
                        <a class="btn-primary" href="{{ route('reports.export', ['report' => $report, 'format' => 'pdf'] + $exportQuery) }}">PDF</a>
                    @else
                        <button class="btn-primary bg-emerald-600 hover:bg-emerald-700" type="button">Excel</button>
                        <button class="btn-primary" type="button">PDF</button>
                    @endif
                @endcan
            </div>
        </form>

        @if($report === 'cash-flow' && $accountFlow === 'bank')
            @can('accounts.bank_transfer.create')
                <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1.4fr_1fr]">
                    <form method="POST" action="{{ route('accounts.bank-transfers.store') }}" class="pos-card space-y-4" data-bank-transfer-form data-cash-balance="{{ (float) $cashInHand }}" data-default-bank-balance="{{ (float) $defaultBankBalance }}">
                        @csrf
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-black text-slate-950">Bank Transfer</h2>
                                <p class="mt-1 text-sm font-semibold text-slate-500">Move cash into bank, bank into cash, or default bank to another account.</p>
                            </div>
                            <div class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-black text-blue-700" data-transfer-source-balance>
                                Available: {{ number_format($cashInHand, 2) }}
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2 text-sm font-black text-slate-700">
                                Transfer Type
                                <select class="form-control" name="transfer_type" data-transfer-type>
                                    <option value="cash_to_bank" @selected(old('transfer_type') === 'cash_to_bank')>Cash to Bank</option>
                                    <option value="bank_to_bank" @selected(old('transfer_type') === 'bank_to_bank')>Bank to Bank</option>
                                    <option value="bank_to_cash" @selected(old('transfer_type') === 'bank_to_cash')>Bank to Cash</option>
                                </select>
                            </label>

                            <label class="grid gap-2 text-sm font-black text-slate-700" data-transfer-target-wrap>
                                Transfer To
                                <select class="form-control" name="to_bank_account_id" required>
                                    @foreach($bankAccounts as $account)
                                        <option value="{{ $account->id }}" data-default="{{ $account->is_default ? '1' : '0' }}" data-balance="{{ (float) $account->balance }}" @selected((int) old('to_bank_account_id') === (int) $account->id)>
                                            {{ $account->name }}{{ $account->account_no ? ' - '.$account->account_no : '' }}{{ $account->is_default ? ' (Default)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="grid gap-2 text-sm font-black text-slate-700">
                                Amount
                                <input class="form-control" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
                            </label>

                            <label class="grid gap-2 text-sm font-black text-slate-700">
                                Note
                                <input class="form-control" type="text" name="note" value="{{ old('note') }}" placeholder="Optional">
                            </label>
                        </div>

                        <button class="btn-primary" type="submit">Save Transfer</button>
                    </form>

                    <form method="POST" action="{{ route('accounts.bank-accounts.store') }}" class="pos-card space-y-4" data-bank-account-form>
                        @csrf
                        <div>
                            <h2 class="text-lg font-black text-slate-950">Add Bank Account</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Account number and opening balance are optional.</p>
                        </div>

                        <div class="grid gap-4">
                            <label class="grid gap-2 text-sm font-black text-slate-700">
                                Account Name
                                <input class="form-control" type="text" name="name" required>
                            </label>
                            <label class="grid gap-2 text-sm font-black text-slate-700">
                                Account Number
                                <input class="form-control" type="text" name="account_no">
                            </label>
                            <label class="grid gap-2 text-sm font-black text-slate-700">
                                Opening Balance
                                <input class="form-control" type="number" step="0.01" min="0" name="opening_balance" placeholder="0.00">
                            </label>
                        </div>

                        <button class="btn-secondary w-full justify-center" type="submit">Add Account</button>
                    </form>
                </section>
            @endcan
        @endif

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($totals as $label => $value)
                @php
                    $tone = str_contains($label, 'due') || str_contains($label, 'out') || str_contains($label, 'expense') ? 'text-red-600' : (str_contains($label, 'paid') || str_contains($label, 'profit') || str_contains($label, 'in') ? 'text-emerald-700' : 'text-slate-950');
                @endphp
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold capitalize text-slate-500">{{ str($label)->headline() }}</p>
                    <p class="mt-2 text-2xl font-black {{ $tone }}">
                        @if(in_array($label, $countLabels, true))
                            {{ number_format($value, 0) }}
                        @else
                            {{ number_format($value, 2) }}
                        @endif
                    </p>
                </article>
            @endforeach
        </section>

        <div class="pos-card p-0">
            <div class="data-table-wrap">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            @foreach($tableHeadings as $heading)
                                <th class="px-4 py-3">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @if(in_array($report, ['sales', 'due-bills'], true))
                                <tr>
                                    <td class="px-4 py-3">{{ optional(\Carbon\Carbon::parse($row->date))->toDateString() }}</td>
                                    <td class="px-4 py-3 font-bold">{{ $row->reference ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $row->party ?? 'Walk-in' }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) ($row->total ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-emerald-700">{{ number_format((float) ($row->paid_amount ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-red-600">{{ number_format((float) ($row->due_amount ?? 0), 2) }}</td>
                                    <td class="px-4 py-3"><span class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-black text-emerald-700">{{ ((float) ($row->due_amount ?? 0)) > 0 ? 'Due' : 'Paid' }}</span></td>
                                </tr>
                            @elseif($report === 'cash-flow')
                                <tr>
                                    <td class="px-4 py-3">{{ optional(\Carbon\Carbon::parse($row->date))->toDateString() }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-black {{ ($row->flow_type ?? '') === 'Cash In' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $row->flow_type ?? '-' }}</span></td>
                                    <td class="px-4 py-3 font-bold">{{ $row->reference ?? '-' }}</td>
                                    <td class="px-4 py-3 capitalize">{{ $row->party ?? '-' }}</td>
                                    <td class="px-4 py-3 text-emerald-700">{{ number_format((float) ($row->inflow ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-red-600">{{ number_format((float) ($row->outflow ?? 0), 2) }}</td>
                                </tr>
                            @elseif($report === 't-accounts')
                                @php
                                    $singleExportQuery = array_merge($exportQuery, ['transaction' => $row['transaction_id']]);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">{{ optional(\Carbon\Carbon::parse($row['date']))->toDateString() }}</td>
                                    <td class="px-4 py-3 font-bold">{{ $row['reference'] }}</td>
                                    <td class="px-4 py-3">{{ $row['description'] }}</td>
                                    <td class="px-4 py-3 text-emerald-700">{{ $row['debit_account'] }}</td>
                                    <td class="px-4 py-3 text-red-600">{{ $row['credit_account'] }}</td>
                                    <td class="px-4 py-3 text-emerald-700">{{ number_format((float) $row['debit_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-red-600">{{ number_format((float) $row['credit_amount'], 2) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-2">
                                            <a class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-black text-emerald-700" href="{{ route('reports.export', ['report' => $report, 'format' => 'excel'] + $singleExportQuery) }}">Excel</a>
                                            <a class="rounded-md bg-blue-50 px-2 py-1 text-xs font-black text-blue-700" href="{{ route('reports.export', ['report' => $report, 'format' => 'pdf'] + $singleExportQuery) }}">PDF</a>
                                        </div>
                                    </td>
                                </tr>
                            @elseif($report === 'waiters')
                                <tr>
                                    <td class="px-4 py-3 font-bold">{{ $row->reference ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) ($row->party ?? 0), 0) }}</td>
                                    <td class="px-4 py-3">{{ $row->date ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) ($row->total ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-emerald-700">{{ number_format((float) ($row->paid_amount ?? 0), 2) }}</td>
                                </tr>
                            @elseif($report === 'product-sales')
                                <tr>
                                    <td class="px-4 py-3 font-bold">{{ $row->reference ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) ($row->party ?? 0), 0) }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) ($row->total ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-red-600">{{ number_format((float) ($row->due_amount ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-emerald-700">{{ number_format((float) ($row->profit ?? 0), 2) }}</td>
                                    <td class="px-4 py-3">
                                        <button type="button" class="rounded-md bg-blue-50 px-3 py-1.5 text-xs font-black text-blue-700 hover:bg-blue-100" data-view-bills="{{ $row->invoices }}">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td class="px-4 py-3 font-bold">{{ $row->reference ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $row->party ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $row->date ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ number_format((float) ($row->total ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-emerald-700">{{ number_format((float) ($row->paid_amount ?? $row->profit ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-red-600">{{ number_format((float) ($row->due_amount ?? 0), 2) }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="{{ count($tableHeadings) }}" class="px-4 py-10 text-center text-slate-500">No report data for this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>
        </div>

        <div class="pos-card">
            <h2 class="text-lg font-black text-slate-950">Daily Summary</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Records</th>
                            <th class="px-4 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailySummary as $summary)
                            <tr>
                                <td class="px-4 py-2 font-bold">{{ $summary->date }}</td>
                                <td class="px-4 py-2">{{ number_format((float) ($summary->records ?? 0), 0) }}</td>
                                <td class="px-4 py-2">{{ number_format((float) ($summary->total ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">No daily summary for this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($report === 'product-sales')
        <div id="view-bills-modal" class="modal fixed inset-0 z-[100] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300 [&.open]:opacity-100 [&.open]:pointer-events-auto">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" data-modal-close></div>
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl transition-transform duration-300 scale-95 [&.open]:scale-100">
                <div class="border-b border-slate-100 px-5 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-900">Sold Invoices</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" data-modal-close>
                        <x-lucide name="x" class="size-5" />
                    </button>
                </div>
                <div class="p-5 max-h-[60vh] overflow-y-auto">
                    <div id="view-bills-content" class="text-sm font-medium text-slate-700 leading-relaxed break-words"></div>
                </div>
                <div class="border-t border-slate-100 bg-slate-50 px-5 py-3 text-right">
                    <button type="button" class="btn-secondary" data-modal-close>Close</button>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const modal = document.getElementById('view-bills-modal');
                    const modalInner = modal.querySelector('.scale-95');
                    const content = document.getElementById('view-bills-content');
                    
                    document.querySelectorAll('[data-view-bills]').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const billsStr = btn.dataset.viewBills;
                            if (!billsStr) {
                                content.innerHTML = '<p class="text-slate-500">No bills found.</p>';
                            } else {
                                const bills = billsStr.split('||').map(b => b.split('|'));
                                let html = '<table class="data-table min-w-full border border-slate-200 rounded-lg overflow-hidden">';
                                html += '<thead><tr class="bg-slate-50"><th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">Invoice Number</th><th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">Total Invoice</th><th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase">Action</th></tr></thead><tbody class="divide-y divide-slate-100">';
                                bills.forEach(b => {
                                    if(b.length === 3) {
                                        const [id, invoice, total] = b;
                                        html += `<tr>
                                            <td class="px-4 py-2 font-bold text-slate-700">${invoice}</td>
                                            <td class="px-4 py-2 font-medium text-slate-600">${parseFloat(total).toFixed(2)}</td>
                                            <td class="px-4 py-2 text-right">
                                                <a href="/manage/sales/${id}" target="_blank" class="rounded-md bg-blue-50 px-3 py-1.5 text-xs font-black text-blue-700 hover:bg-blue-100 inline-block">View</a>
                                            </td>
                                        </tr>`;
                                    }
                                });
                                html += '</tbody></table>';
                                content.innerHTML = html;
                            }
                            modal.classList.add('open');
                            modalInner.classList.add('open');
                        });
                    });

                    document.querySelectorAll('#view-bills-modal [data-modal-close]').forEach(btn => {
                        btn.addEventListener('click', () => {
                            modal.classList.remove('open');
                            modalInner.classList.remove('open');
                        });
                    });
                });
            </script>
        @endpush
    @endif
</x-layouts.app>
