@php
    $selectedCustomer = $customers->firstWhere('id', old('customer_id', $record->customer_id));
    $selectedWaiter = $waiters->firstWhere('id', old('waiter_id', $record->waiter_id));
    $selectedTable = $tables->firstWhere('id', old('restaurant_table_id', $record->restaurant_table_id));
@endphp

<x-layouts.app heading="Edit Sale" title="Sales" :show-date-filter="false">
    <form method="POST" action="{{ route('backoffice.modules.update', ['module' => $module, 'id' => $record->id]) }}" class="space-y-5" data-sale-form>
        @csrf
        @method('PUT')
        <input type="hidden" name="invoice_no" value="{{ old('invoice_no', $record->invoice_no) }}">

        <section class="pos-card overflow-hidden p-0">
            <div class="border-b border-slate-100 bg-white px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Sale Entry</p>
                        <h2 class="mt-1 text-xl font-black text-slate-900">Sale & Customer Details ({{ $record->invoice_no }})</h2>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 p-5 md:grid-cols-3">
                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Customer
                    <select class="form-control" name="customer_id">
                        <option value="">Walk-in Customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected($selectedCustomer?->id === $customer->id)>{{ $customer->name }} ({{ $customer->phone }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Waiter
                    <select class="form-control" name="waiter_id">
                        <option value="">None</option>
                        @foreach($waiters as $waiter)
                            <option value="{{ $waiter->id }}" @selected($selectedWaiter?->id === $waiter->id)>{{ $waiter->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Table
                    <select class="form-control" name="restaurant_table_id">
                        <option value="">None</option>
                        @foreach($tables as $table)
                            <option value="{{ $table->id }}" @selected($selectedTable?->id === $table->id)>{{ $table->number }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Sale Date
                    <input class="form-control" type="datetime-local" name="sale_date" value="{{ old('sale_date', \Carbon\Carbon::parse($record->sale_date)->format('Y-m-d\TH:i')) }}" required>
                </label>
                
                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Status
                    <select class="form-control" name="status">
                        <option value="paid" @selected(old('status', $record->status) === 'paid')>Paid</option>
                        <option value="due" @selected(old('status', $record->status) === 'due')>Due</option>
                        <option value="deleted" @selected(old('status', $record->status) === 'deleted')>Deleted</option>
                    </select>
                </label>
            </div>
        </section>

        <section class="pos-card overflow-visible p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-600">Products</p>
                    <h2 class="mt-1 text-xl font-black text-slate-900">Sale Items</h2>
                </div>
                <div class="text-sm font-bold text-slate-500">
                    Search by product name or barcode.
                </div>
            </div>

            <div class="p-5">
                <div class="relative flex flex-col gap-3 lg:flex-row">
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
                        <input class="form-control w-full pl-10" type="search" placeholder="Search products to add..." data-product-search autocomplete="off">
                        <div class="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-30 hidden max-h-80 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-2xl" data-product-results></div>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-[1050px] w-full text-sm">
                        <thead class="bg-emerald-600 text-white">
                            <tr>
                                <th class="w-12 px-3 py-3 text-left font-black">#</th>
                                <th class="min-w-72 px-3 py-3 text-left font-black">Product Name</th>
                                <th class="w-32 px-3 py-3 text-right font-black">Quantity</th>
                                <th class="w-40 px-3 py-3 text-right font-black">Unit Price</th>
                                <th class="w-36 px-3 py-3 text-right font-black">Discount %</th>
                                <th class="w-40 px-3 py-3 text-right font-black">Line Total</th>
                                <th class="w-14 px-3 py-3 text-center font-black"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white" data-sale-items>
                            <tr data-empty-row class="hidden">
                                <td colspan="7" class="px-4 py-10 text-center font-semibold text-slate-500">Search and add products to begin this sale.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <div class="grid content-start gap-4">
                        <label class="grid gap-2 text-sm font-bold text-slate-800">
                            Sale Notes
                            <textarea class="form-control min-h-24" name="note" placeholder="Optional sale note">{{ old('note', $record->note) }}</textarea>
                        </label>
                    </div>

                    <div class="rounded-lg bg-slate-50 p-5">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                            <span class="font-black text-slate-700">Total Items</span>
                            <span class="font-black text-slate-900" data-total-items>0.000</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-200 py-3">
                            <span class="font-black text-slate-700">Net Total Amount</span>
                            <span class="font-black text-slate-900" data-net-total>0.00</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-200 py-3">
                            <span class="font-black text-slate-700">Discount Amount</span>
                            <div class="flex gap-2 text-right">
                                <span class="font-black text-red-600" data-discount-total>(-) {{ number_format($record->discount_amount, 2) }}</span>
                                <input type="number" step="0.01" min="0" name="discount_amount" value="{{ old('discount_amount', $record->discount_amount) }}" class="form-control min-h-8 w-24 px-2 py-1 text-sm" data-global-discount>
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-200 py-3">
                            <span class="font-black text-slate-700">Grand Total</span>
                            <span class="text-2xl font-black text-blue-600" data-grand-total>0.00</span>
                        </div>
                        <div class="flex items-center justify-between pt-3">
                            <span class="font-black text-slate-700">Previously Paid</span>
                            <span class="text-xl font-black text-emerald-600" data-paid-amount>{{ number_format($record->paid_amount, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-3">
                            <span class="font-black text-slate-700">New Due Amount</span>
                            <span class="text-xl font-black text-rose-600" data-due-amount>0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex gap-3">
            <button class="btn-primary">Update Sale</button>
            <a class="btn-secondary" href="{{ route('backoffice.modules.index', $module) }}">Cancel</a>
        </div>
    </form>

    @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-sale-form]');
            if (!form) return;

            let products = @json($products);
            const initialItems = @json($saleItems);
            const search = form.querySelector('[data-product-search]');
            const results = form.querySelector('[data-product-results]');
            const body = form.querySelector('[data-sale-items]');
            const emptyRow = form.querySelector('[data-empty-row]');
            const globalDiscountInput = form.querySelector('[data-global-discount]');
            const paidAmount = {{ (float) $record->paid_amount }};
            let rowIndex = 0;

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[char]);
            
            const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const numberValue = (input) => Math.max(0, Number(input?.value || input || 0) || 0);
            
            const attachClearZero = (input) => {
                input.addEventListener('focus', () => {
                    if (Number(input.value || 0) === 0) {
                        input.value = '';
                    }
                });
                input.addEventListener('blur', () => {
                    if (input.value === '') {
                        input.value = '0';
                    }
                });
            };

            const updateTotals = () => {
                const rows = [...body.querySelectorAll('[data-item-row]')];
                let totalItems = 0;
                let netTotal = 0;
                let discountTotal = 0;
                let grandTotal = 0;

                rows.forEach((row, index) => {
                    row.querySelector('[data-row-number]').textContent = index + 1;
                    const quantity = numberValue(row.querySelector('[data-quantity]'));
                    const unitPrice = numberValue(row.querySelector('[data-unit-price]'));
                    const discountPercent = Math.min(100, numberValue(row.querySelector('[data-discount-percent]')));
                    const gross = quantity * unitPrice;
                    const discount = gross * discountPercent / 100;
                    const lineTotal = Math.max(0, gross - discount);

                    totalItems += quantity;
                    netTotal += gross;
                    discountTotal += discount;
                    grandTotal += lineTotal;
                    row.querySelector('[data-line-total]').textContent = money(lineTotal);
                });

                // Apply global discount
                const globalDiscount = numberValue(globalDiscountInput);
                grandTotal = Math.max(0, grandTotal - globalDiscount);

                form.querySelector('[data-total-items]').textContent = totalItems.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                form.querySelector('[data-net-total]').textContent = money(netTotal);
                form.querySelector('[data-discount-total]').textContent = `(-) ${money(discountTotal + globalDiscount)}`;
                form.querySelector('[data-grand-total]').textContent = money(grandTotal);
                form.querySelector('[data-due-amount]').textContent = money(Math.max(0, grandTotal - paidAmount));
                emptyRow.classList.toggle('hidden', rows.length > 0);
            };

            const addProduct = (product, initialData = null) => {
                if (!initialData) {
                    const existing = body.querySelector(`[data-product-id="${product.id}"]`);
                    if (existing) {
                        const quantityInput = existing.querySelector('[data-quantity]');
                        quantityInput.value = numberValue(quantityInput) + 1;
                        updateTotals();
                        return;
                    }
                }

                const index = rowIndex++;
                const row = document.createElement('tr');
                const quantity = initialData ? initialData.quantity : 1;
                const unitPrice = initialData ? initialData.unit_price : product.selling_price;
                const discountPercent = initialData && initialData.discount_amount > 0 
                    ? ((initialData.discount_amount / (quantity * unitPrice)) * 100) 
                    : 0;
                    
                row.dataset.itemRow = '1';
                row.dataset.productId = product.id;
                row.className = 'hover:bg-slate-50';
                row.innerHTML = `
                    <td class="px-3 py-3 font-bold text-slate-500" data-row-number></td>
                    <td class="px-3 py-3">
                        <input type="hidden" name="items[${index}][product_id]" value="${product.id}">
                        <p class="font-black text-slate-900">${escapeHtml(product.product_name || product.name)}</p>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Barcode: ${escapeHtml(product.barcode || '-')}</p>
                    </td>
                    <td class="px-3 py-3"><input class="form-control min-h-10 text-right" type="number" step="0.001" min="0.001" name="items[${index}][quantity]" value="${quantity}" data-quantity required></td>
                    <td class="px-3 py-3"><input class="form-control min-h-10 text-right" type="number" step="0.01" min="0" name="items[${index}][unit_price]" value="${Number(unitPrice || 0).toFixed(2)}" data-unit-price data-clear-zero required></td>
                    <td class="px-3 py-3"><input class="form-control min-h-10 text-right" type="number" step="0.01" min="0" max="100" name="items[${index}][discount_percent]" value="${Number(discountPercent || 0).toFixed(2)}" data-discount-percent data-clear-zero></td>
                    <td class="px-3 py-3 text-right font-black text-slate-900" data-line-total>0.00</td>
                    <td class="px-3 py-3 text-center"><button type="button" class="rounded-lg px-3 py-2 text-lg font-black text-red-600 transition hover:bg-red-50" data-remove-row>&times;</button></td>
                `;

                body.appendChild(row);
                row.querySelectorAll('input').forEach((input) => input.addEventListener('input', updateTotals));
                row.querySelectorAll('[data-clear-zero]').forEach(attachClearZero);
                row.querySelector('[data-remove-row]').addEventListener('click', () => {
                    row.remove();
                    updateTotals();
                });
                updateTotals();
            };

            const renderResults = () => {
                const query = search.value.trim().toLowerCase();
                if (!query) {
                    results.classList.add('hidden');
                    results.innerHTML = '';
                    return;
                }

                const matches = products.filter((product) => [product.name, product.barcode].some((value) => String(value || '').toLowerCase().includes(query))).slice(0, 10);
                results.innerHTML = matches.length
                    ? matches.map((product) => `
                        <button type="button" class="block w-full border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-blue-50" data-result-id="${product.id}">
                            <span class="block font-black text-slate-900">${escapeHtml(product.name)}</span>
                            <span class="mt-0.5 block text-xs font-semibold text-slate-500">Barcode: ${escapeHtml(product.barcode || '-')} | Price: ${escapeHtml(product.selling_price)}</span>
                        </button>
                    `).join('')
                    : '<div class="px-4 py-5 text-sm font-semibold text-slate-500">No products found.</div>';
                results.classList.remove('hidden');
            };

            search.addEventListener('input', renderResults);
            
            results.addEventListener('click', (event) => {
                const button = event.target.closest('[data-result-id]');
                if (!button) return;

                const product = products.find((item) => String(item.id) === button.dataset.resultId);
                if (product) {
                    addProduct(product);
                    search.value = '';
                    results.classList.add('hidden');
                    search.focus();
                }
            });
            
            globalDiscountInput.addEventListener('input', updateTotals);
            
            document.addEventListener('click', (event) => {
                if (!results.contains(event.target) && event.target !== search) {
                    results.classList.add('hidden');
                }
            });
            
            form.addEventListener('submit', (event) => {
                if (!body.querySelector('[data-item-row]')) {
                    event.preventDefault();
                    search.focus();
                    alert('Please add at least one product to the sale.');
                }
            });

            // Load initial items
            if (initialItems && initialItems.length > 0) {
                initialItems.forEach(item => {
                    const product = products.find(p => p.id === item.product_id);
                    if (product) {
                        addProduct(product, item);
                    }
                });
            } else {
                emptyRow.classList.remove('hidden');
            }
        });
        </script>
    @endpush
</x-layouts.app>
