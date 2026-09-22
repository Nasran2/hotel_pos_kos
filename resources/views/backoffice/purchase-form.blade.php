@php
$isEdit = isset($record);
$selectedSupplier = $suppliers->firstWhere('id', old('supplier_id', $isEdit ? $record->supplier_id : null));
@endphp

<x-layouts.app heading="{{ $isEdit ? 'Edit Purchase' : 'Add Purchase' }}" title="Purchases" :show-date-filter="false">
    <form method="POST" action="{{ $isEdit ? route('backoffice.modules.update', [$module, $record->id]) : route('backoffice.modules.store', $module) }}" class="space-y-5" data-purchase-form>
        @csrf
        @if($isEdit)
        @method('PUT')
        @endif

        <section class="pos-card overflow-hidden p-0">
            <div class="border-b border-slate-100 bg-white px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Purchase Entry</p>
                        <h2 class="mt-1 text-xl font-black text-slate-900">Supplier & Bill Details</h2>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 p-5 md:grid-cols-2">
                <div class="grid gap-2 text-sm font-bold text-slate-800 md:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <span>Supplier</span>
                        <button type="button" class="btn-primary justify-center gap-2" data-supplier-modal-open>
                            <x-lucide name="circle-plus" class="size-4" />
                            Add new supplier
                        </button>
                    </div>
                    <label class="relative block">
                        <input type="hidden" name="supplier_id" value="{{ old('supplier_id', $isEdit ? $record->supplier_id : '') }}" data-supplier-id>
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
                        <input class="form-control w-full pl-10" type="search" value="{{ $selectedSupplier?->name }}" placeholder="Search supplier by name, company, phone..." data-supplier-search autocomplete="off" required>
                        <span class="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-40 hidden max-h-72 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-2xl" data-supplier-results></span>
                    </label>
                </div>

                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Reference No
                    <input class="form-control" name="reference_no" value="{{ old('reference_no', $isEdit ? $record->reference_no : '') }}" placeholder="Bill or supplier invoice number">
                </label>

                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Purchase Date
                    <input class="form-control" type="datetime-local" name="purchase_date" value="{{ old('purchase_date', $isEdit ? \Carbon\Carbon::parse($record->purchase_date)->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}" required>
                </label>
            </div>
        </section>

        <section class="pos-card overflow-visible p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-600">Products</p>
                    <h2 class="mt-1 text-xl font-black text-slate-900">Purchase Items</h2>
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
                    <button type="button" class="btn-primary justify-center gap-2" data-product-modal-open>
                        <x-lucide name="circle-plus" class="size-4" />
                        Add new product
                    </button>
                </div>

                <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-[1050px] w-full text-sm">
                        <thead class="bg-emerald-600 text-white">
                            <tr>
                                <th class="w-12 px-3 py-3 text-left font-black">#</th>
                                <th class="min-w-72 px-3 py-3 text-left font-black">Product Name</th>
                                <th class="w-32 px-3 py-3 text-right font-black">Quantity</th>
                                <th class="w-40 px-3 py-3 text-right font-black">Unit Cost</th>
                                <th class="w-36 px-3 py-3 text-right font-black">Discount %</th>
                                <th class="w-40 px-3 py-3 text-right font-black">Line Total</th>
                                <th class="w-44 px-3 py-3 text-right font-black">Selling Price</th>
                                <th class="w-14 px-3 py-3 text-center font-black"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white" data-purchase-items>
                            <tr data-empty-row>
                                <td colspan="8" class="px-4 py-10 text-center font-semibold text-slate-500">Search and add products to begin this purchase.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <div class="grid content-start gap-4 md:grid-cols-2">
                        <label class="grid gap-2 text-sm font-bold text-slate-800">
                            Payment Method <span class="text-red-500">*</span>
                            <select class="form-control" name="payment_method" {{ $isEdit ? 'disabled' : 'required' }}>
                                <option value="cash" @selected(old('payment_method')==='cash' )>Cash</option>
                                <option value="bank" @selected(old('payment_method')==='bank' )>Bank</option>
                                <option value="card" @selected(old('payment_method')==='card' )>Card</option>
                            </select>
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-800">
                            Payment Amount
                            <input class="form-control" type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', $isEdit ? $record->paid_amount : '0.00') }}" data-paid-amount data-clear-zero {{ $isEdit ? 'readonly' : '' }}>
                        </label>

                        <label class="grid gap-2 text-sm font-bold text-slate-800 md:col-span-2">
                            Additional Notes
                            <textarea class="form-control min-h-24" name="notes" placeholder="Optional purchase note">{{ old('notes', $isEdit ? $record->notes : '') }}</textarea>
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
                            <span class="font-black text-slate-700">Discount</span>
                            <span class="font-black text-red-600" data-discount-total>(-) 0.00</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-200 py-3">
                            <span class="font-black text-slate-700">Grand Total</span>
                            <span class="text-2xl font-black text-blue-600" data-grand-total>0.00</span>
                        </div>
                        <div class="flex items-center justify-between pt-3">
                            <span class="font-black text-slate-700">Due Amount</span>
                            <span class="text-2xl font-black text-red-600" data-due-amount>0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex gap-3">
            <button class="btn-primary">Save Purchase</button>
            <a class="btn-secondary" href="{{ route('backoffice.modules.index', $module) }}">Cancel</a>
        </div>
    </form>

    <div class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-product-modal>
        <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-md" data-product-modal-close></div>
        <div class="relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl ring-1 ring-slate-950/10">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 bg-slate-50 px-5 py-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Quick Create</p>
                    <h3 class="mt-1 text-xl font-black text-slate-900">Add New Product</h3>
                </div>
                <button type="button" class="grid size-9 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900" data-product-modal-close aria-label="Close product popup">
                    <x-lucide name="x" class="size-4" />
                </button>
            </div>

            <form method="POST" action="{{ route('backoffice.modules.store', 'products') }}" class="overflow-y-auto p-5" data-product-create-form>
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm font-bold text-slate-800 md:col-span-2">
                        Product Name
                        <input class="form-control" name="name" required placeholder="Enter product name">
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Category
                        <select class="form-control" name="category_id">
                            <option value="">Select category</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Barcode
                        <input class="form-control" name="barcode" placeholder="Auto-generated if empty">
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Cost Price
                        <input class="form-control" type="number" step="0.01" min="0" name="cost_price" value="0" required data-clear-zero>
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Selling Price
                        <input class="form-control" type="number" step="0.01" min="0" name="selling_price" value="0" required data-clear-zero>
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Opening Stock
                        <input class="form-control" type="number" step="0.001" min="0" name="stock_quantity" value="0" data-clear-zero>
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Alert Quantity
                        <input class="form-control" type="number" step="0.001" min="0" name="alert_quantity" value="0" data-clear-zero>
                    </label>

                    <input type="hidden" name="maintain_stock" value="1">
                    <input type="hidden" name="is_active" value="1">
                </div>

                <p class="mt-4 hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700" data-product-create-error></p>

                <div class="mt-5 flex justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="button" class="btn-secondary" data-product-modal-close>Cancel</button>
                    <button type="submit" class="btn-primary" data-product-create-submit>Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <div class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-supplier-modal>
        <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-md" data-supplier-modal-close></div>
        <div class="relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl ring-1 ring-slate-950/10">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 bg-slate-50 px-5 py-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Quick Create</p>
                    <h3 class="mt-1 text-xl font-black text-slate-900">Add New Supplier</h3>
                </div>
                <button type="button" class="grid size-9 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900" data-supplier-modal-close aria-label="Close supplier popup">
                    <x-lucide name="x" class="size-4" />
                </button>
            </div>

            <form method="POST" action="{{ route('backoffice.modules.store', 'suppliers') }}" class="overflow-y-auto p-5" data-supplier-create-form>
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm font-bold text-slate-800 md:col-span-2">
                        Supplier Name
                        <input class="form-control" name="name" required placeholder="Enter supplier name">
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Phone
                        <input class="form-control" name="phone" placeholder="Supplier phone number">
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Email
                        <input class="form-control" type="email" name="email" placeholder="Supplier email">
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800 md:col-span-2">
                        Company Name
                        <input class="form-control" name="company_name" placeholder="Company or business name">
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800 md:col-span-2">
                        Address
                        <textarea class="form-control min-h-20" name="address" placeholder="Supplier address"></textarea>
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800">
                        Opening Balance
                        <input class="form-control" type="number" step="0.01" min="0" name="opening_balance" value="0" data-clear-zero>
                    </label>

                    <label class="inline-flex cursor-pointer items-center gap-3 text-sm font-bold text-slate-800">
                        <input type="checkbox" name="is_active" value="1" class="peer sr-only" checked>
                        <span class="relative h-8 w-16 rounded-full bg-red-500 shadow-md transition after:absolute after:left-1 after:top-1 after:size-6 after:rounded-full after:bg-white after:shadow-md after:transition peer-checked:bg-green-500 peer-checked:after:translate-x-8"></span>
                        Active
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-slate-800 md:col-span-2">
                        Notes
                        <textarea class="form-control min-h-20" name="notes" placeholder="Optional supplier notes"></textarea>
                    </label>
                </div>

                <p class="mt-4 hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700" data-supplier-create-error></p>

                <div class="mt-5 flex justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="button" class="btn-secondary" data-supplier-modal-close>Cancel</button>
                    <button type="submit" class="btn-primary" data-supplier-create-submit>Save Supplier</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('[data-purchase-form]');
            if (!form) return;

            let products = @json($products);
            const suppliers = @json($suppliers);
            const search = form.querySelector('[data-product-search]');
            const results = form.querySelector('[data-product-results]');
            const supplierId = form.querySelector('[data-supplier-id]');
            const supplierSearch = form.querySelector('[data-supplier-search]');
            const supplierResults = form.querySelector('[data-supplier-results]');
            const supplierModal = document.querySelector('[data-supplier-modal]');
            const supplierCreateForm = document.querySelector('[data-supplier-create-form]');
            const supplierCreateError = document.querySelector('[data-supplier-create-error]');
            const supplierCreateSubmit = document.querySelector('[data-supplier-create-submit]');
            const body = form.querySelector('[data-purchase-items]');
            const emptyRow = form.querySelector('[data-empty-row]');
            const paidInput = form.querySelector('[data-paid-amount]');
            const productModal = document.querySelector('[data-product-modal]');
            const productCreateForm = document.querySelector('[data-product-create-form]');
            const productCreateError = document.querySelector('[data-product-create-error]');
            const productCreateSubmit = document.querySelector('[data-product-create-submit]');
            let rowIndex = 0;

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[char]);
            const money = (value) => Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const numberValue = (input) => Math.max(0, Number(input?.value || 0) || 0);
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

            const supplierLabel = (supplier) => {
                const company = supplier.company_name ? ` · ${supplier.company_name}` : '';
                return `${supplier.name}${company}`;
            };

            const renderSupplierResults = () => {
                const query = supplierSearch.value.trim().toLowerCase();
                supplierId.value = suppliers.some((supplier) => String(supplier.id) === String(supplierId.value) && supplierLabel(supplier) === supplierSearch.value) ? supplierId.value : '';

                if (!query) {
                    supplierResults.classList.add('hidden');
                    supplierResults.innerHTML = '';
                    return;
                }

                const matches = suppliers.filter((supplier) => [
                    supplier.name,
                    supplier.company_name,
                    supplier.phone,
                    supplier.email,
                ].some((value) => String(value || '').toLowerCase().includes(query))).slice(0, 10);

                supplierResults.innerHTML = matches.length ?
                    matches.map((supplier) => `
                        <button type="button" class="block w-full border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-blue-50" data-supplier-result-id="${supplier.id}">
                            <span class="block font-black text-slate-900">${escapeHtml(supplierLabel(supplier))}</span>
                            <span class="mt-0.5 block text-xs font-semibold text-slate-500">Phone: ${escapeHtml(supplier.phone || '-')} | Email: ${escapeHtml(supplier.email || '-')}</span>
                        </button>
                    `).join('') :
                    '<span class="block px-4 py-5 text-sm font-semibold text-slate-500">No suppliers found.</span>';
                supplierResults.classList.remove('hidden');
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
                    const unitCost = numberValue(row.querySelector('[data-unit-cost]'));
                    const discountPercent = Math.min(100, numberValue(row.querySelector('[data-discount-percent]')));
                    const gross = quantity * unitCost;
                    const discount = gross * discountPercent / 100;
                    const lineTotal = Math.max(0, gross - discount);

                    totalItems += quantity;
                    netTotal += gross;
                    discountTotal += discount;
                    grandTotal += lineTotal;
                    row.querySelector('[data-line-total]').textContent = money(lineTotal);
                });

                form.querySelector('[data-total-items]').textContent = totalItems.toLocaleString(undefined, {
                    minimumFractionDigits: 3,
                    maximumFractionDigits: 3
                });
                form.querySelector('[data-net-total]').textContent = money(netTotal);
                form.querySelector('[data-discount-total]').textContent = `(-) ${money(discountTotal)}`;
                form.querySelector('[data-grand-total]').textContent = money(grandTotal);
                form.querySelector('[data-due-amount]').textContent = money(Math.max(0, grandTotal - numberValue(paidInput)));
                emptyRow.classList.toggle('hidden', rows.length > 0);
            };

            const addProduct = (product, initialQty = 1, initialDiscount = 0, initialUnitCost = null, initialSellingPrice = null) => {
                const existing = body.querySelector(`[data-product-id="${product.id}"]`);
                if (existing) {
                    const quantityInput = existing.querySelector('[data-quantity]');
                    quantityInput.value = numberValue(quantityInput) + initialQty;
                    updateTotals();
                    return;
                }

                const index = rowIndex++;
                const row = document.createElement('tr');
                row.dataset.itemRow = '1';
                row.dataset.productId = product.id;
                row.className = 'hover:bg-slate-50';
                row.innerHTML = `
                    <td class="px-3 py-3 font-bold text-slate-500" data-row-number></td>
                    <td class="px-3 py-3">
                        <input type="hidden" name="items[${index}][product_id]" value="${product.id}">
                        <p class="font-black text-slate-900">${escapeHtml(product.name)}</p>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Barcode: ${escapeHtml(product.barcode || '-')}</p>
                    </td>
                    <td class="px-3 py-3"><input class="form-control min-h-10 text-right" type="number" step="0.001" min="0.001" name="items[${index}][quantity]" value="${initialQty}" data-quantity required></td>
                    <td class="px-3 py-3"><input class="form-control min-h-10 text-right" type="number" step="0.01" min="0" name="items[${index}][unit_cost]" value="${Number(initialUnitCost !== null ? initialUnitCost : (product.cost_price || 0)).toFixed(2)}" data-unit-cost data-clear-zero required></td>
                    <td class="px-3 py-3"><input class="form-control min-h-10 text-right" type="number" step="0.01" min="0" max="100" name="items[${index}][discount_percent]" value="${initialDiscount}" data-discount-percent data-clear-zero></td>
                    <td class="px-3 py-3 text-right font-black text-slate-900" data-line-total>0.00</td>
                    <td class="px-3 py-3"><input class="form-control min-h-10 text-right" type="number" step="0.01" min="0" name="items[${index}][new_selling_price]" value="${Number(initialSellingPrice !== null ? initialSellingPrice : (product.selling_price || 0)).toFixed(2)}" data-clear-zero></td>
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
                results.innerHTML = matches.length ?
                    matches.map((product) => `
                        <button type="button" class="block w-full border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-blue-50" data-result-id="${product.id}">
                            <span class="block font-black text-slate-900">${escapeHtml(product.name)}</span>
                            <span class="mt-0.5 block text-xs font-semibold text-slate-500">Barcode: ${escapeHtml(product.barcode || '-')}</span>
                        </button>
                    `).join('') :
                    '<div class="px-4 py-5 text-sm font-semibold text-slate-500">No products found.</div>';
                results.classList.remove('hidden');
            };

            supplierSearch.addEventListener('input', renderSupplierResults);
            supplierResults.addEventListener('click', (event) => {
                const button = event.target.closest('[data-supplier-result-id]');
                if (!button) return;

                const supplier = suppliers.find((item) => String(item.id) === button.dataset.supplierResultId);
                if (supplier) {
                    supplierId.value = supplier.id;
                    supplierSearch.value = supplierLabel(supplier);
                    supplierResults.classList.add('hidden');
                }
            });
            search.addEventListener('input', renderResults);
            document.querySelectorAll('[data-product-modal-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    productCreateError?.classList.add('hidden');
                    productCreateForm?.reset();
                    productModal?.classList.remove('hidden');
                    productModal?.classList.add('flex');
                    productCreateForm?.querySelector('[name="name"]')?.focus();
                });
            });
            document.querySelectorAll('[data-supplier-modal-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    supplierCreateError?.classList.add('hidden');
                    supplierCreateForm?.reset();
                    supplierCreateForm?.querySelector('[name="is_active"]')?.setAttribute('checked', 'checked');
                    supplierModal?.classList.remove('hidden');
                    supplierModal?.classList.add('flex');
                    supplierCreateForm?.querySelector('[name="name"]')?.focus();
                });
            });
            document.querySelectorAll('[data-supplier-modal-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    supplierModal?.classList.add('hidden');
                    supplierModal?.classList.remove('flex');
                });
            });
            document.querySelectorAll('[data-product-modal-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    productModal?.classList.add('hidden');
                    productModal?.classList.remove('flex');
                });
            });
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
            productCreateForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                productCreateError?.classList.add('hidden');
                productCreateSubmit.disabled = true;
                productCreateSubmit.textContent = 'Saving...';

                try {
                    const response = await fetch(productCreateForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(productCreateForm),
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        const errors = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'Unable to create product.'];
                        throw new Error(errors[0]);
                    }

                    products = [...products, payload.product].sort((first, second) => String(first.name).localeCompare(String(second.name)));
                    addProduct(payload.product);
                    productModal?.classList.add('hidden');
                    productModal?.classList.remove('flex');
                } catch (error) {
                    if (productCreateError) {
                        productCreateError.textContent = error.message || 'Unable to create product.';
                        productCreateError.classList.remove('hidden');
                    }
                } finally {
                    productCreateSubmit.disabled = false;
                    productCreateSubmit.textContent = 'Save Product';
                }
            });
            document.querySelectorAll('[data-clear-zero]').forEach(attachClearZero);
            supplierCreateForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                supplierCreateError?.classList.add('hidden');
                supplierCreateSubmit.disabled = true;
                supplierCreateSubmit.textContent = 'Saving...';

                try {
                    const response = await fetch(supplierCreateForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(supplierCreateForm),
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        const errors = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'Unable to create supplier.'];
                        throw new Error(errors[0]);
                    }

                    const supplier = payload.supplier;
                    suppliers.push(supplier);
                    suppliers.sort((first, second) => String(first.name).localeCompare(String(second.name)));
                    supplierId.value = supplier.id;
                    supplierSearch.value = supplierLabel(supplier);
                    supplierResults.classList.add('hidden');
                    supplierModal?.classList.add('hidden');
                    supplierModal?.classList.remove('flex');
                } catch (error) {
                    if (supplierCreateError) {
                        supplierCreateError.textContent = error.message || 'Unable to create supplier.';
                        supplierCreateError.classList.remove('hidden');
                    }
                } finally {
                    supplierCreateSubmit.disabled = false;
                    supplierCreateSubmit.textContent = 'Save Supplier';
                }
            });
            paidInput.addEventListener('input', updateTotals);
            document.addEventListener('click', (event) => {
                if (!results.contains(event.target) && event.target !== search) {
                    results.classList.add('hidden');
                }
                if (!supplierResults.contains(event.target) && event.target !== supplierSearch) {
                    supplierResults.classList.add('hidden');
                }
            });
            form.addEventListener('submit', (event) => {
                if (!supplierId.value) {
                    event.preventDefault();
                    supplierSearch.focus();
                    alert('Please select a supplier from the search results.');
                    return;
                }

                if (!body.querySelector('[data-item-row]')) {
                    event.preventDefault();
                    search.focus();
                    alert('Please add at least one product to the purchase.');
                }
            });

            updateTotals();

            let existingItems = @json($purchaseItems ?? []);
            if (existingItems && existingItems.length > 0) {
                existingItems.forEach(item => {
                    const product = products.find(p => String(p.id) === String(item.product_id));
                    if (product) {
                        addProduct(
                            product,
                            parseFloat(item.quantity),
                            parseFloat(item.discount_percent || 0),
                            parseFloat(item.unit_cost),
                            parseFloat(item.new_selling_price !== null ? item.new_selling_price : product.selling_price)
                        );
                    }
                });
            }
        });
    </script>
    @endpush
</x-layouts.app>