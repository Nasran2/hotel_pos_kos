<x-layouts.app heading="Online Orders" title="Online Orders" :pos-fullscreen="true" :show-date-filter="false">
    @php
        $isRegisterOpen = (bool) $register;
    @endphp

    <div id="online-orders-root" class="h-screen overflow-hidden bg-gradient-to-br from-slate-50 to-slate-100 p-6" data-currency="{{ $currency }}">
        <div class="grid h-full grid-cols-[1fr_2fr_1.2fr_1.1fr] gap-6">
            <!-- Panel 1: Core Order Info -->
            <div class="flex flex-col gap-4 overflow-y-auto rounded-2xl bg-white p-6 shadow-lg border border-slate-100">
                <div class="space-y-6">
                    <div>
                        <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wider">Platform</h3>
                        <select class="form-control w-full border-2 border-slate-200 rounded-lg font-semibold" name="online_order_source_id" data-platform-select @disabled(! $isRegisterOpen)>
                            <option value="">Select platform</option>
                            @foreach($sources as $source)
                                <option value="{{ $source->id }}" data-commission-type="{{ $source->commission_type }}" data-commission-value="{{ $source->commission_value }}">
                                    {{ $source->name }}
                                </option>
                            @endforeach
                        </select>

                        <!-- Commission Badge -->
                        <div class="mt-4 flex items-center gap-4">
                            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-center text-xs font-bold text-white shadow-md" data-commission-badge>
                                18%
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wider">Commission</p>
                                <p class="text-sm font-bold text-slate-900" data-platform-name>Select platform</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-6">
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Order Reference</label>
                        <input class="form-control w-full border-2 border-slate-200 rounded-lg font-semibold" type="text" name="order_reference" placeholder="e.g., UBER-10025" required @disabled(! $isRegisterOpen)>
                    </div>

                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Customer Details</label>
                        <input class="form-control w-full border border-slate-200 rounded-lg text-sm" type="text" name="customer_name" placeholder="Customer name" required @disabled(! $isRegisterOpen)>
                        <input class="form-control w-full border border-slate-200 rounded-lg text-sm" type="text" name="customer_phone" placeholder="Phone number" @disabled(! $isRegisterOpen)>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Delivery Address</label>
                        <textarea class="form-control w-full border border-slate-200 rounded-lg text-sm h-16" name="delivery_address" placeholder="Full address..." @disabled(! $isRegisterOpen)></textarea>
                        <div class="mt-3 h-28 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-xs text-slate-500 font-semibold">
                            📍 Map Preview
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Special Notes</label>
                        <textarea class="form-control w-full border border-slate-200 rounded-lg text-sm h-16" name="notes" placeholder="Any special instructions..." @disabled(! $isRegisterOpen)></textarea>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Menu (Discovery & Selection) -->
            <div class="flex flex-col gap-4 overflow-hidden rounded-2xl bg-white p-6 shadow-lg border border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 uppercase tracking-wider">Menu Selection</h3>
                    <div class="flex gap-3">
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-3 text-lg">🔍</span>
                            <input class="form-control w-full border-2 border-slate-200 rounded-lg pl-10 font-semibold" type="text" placeholder="Search items..." data-product-search @disabled(! $isRegisterOpen)>
                        </div>
                        <select class="form-control border-2 border-slate-200 rounded-lg font-semibold w-32" data-category-filter @disabled(! $isRegisterOpen)>
                            <option value="">All Items</option>
                        </select>
                    </div>
                </div>

                <!-- Item Grid -->
                <div class="flex-1 overflow-y-auto pr-2">
                    <div class="grid grid-cols-4 gap-4">
                        @foreach($products as $product)
                            <div class="group rounded-xl border-2 border-slate-200 bg-gradient-to-br from-slate-50 to-white p-3 shadow-sm hover:shadow-lg hover:border-blue-400 transition cursor-pointer" data-product-card="{{ $product->id }}" data-product-name="{{ $product->name }}" data-product-price="{{ $product->selling_price }}">
                                <div class="flex items-center justify-center h-24 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg font-bold text-2xl text-white mb-3 group-hover:scale-105 transition">
                                    {{ strtoupper(substr($product->name, 0, 1)) }}
                                </div>
                                <p class="text-xs font-semibold text-slate-900 truncate mb-1">{{ substr($product->name, 0, 20) }}</p>
                                <p class="text-sm font-bold text-blue-600 mb-2">{{ $currency }} {{ number_format((float) $product->selling_price, 2) }}</p>
                                <button class="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold py-2 rounded-lg transition shadow-md" type="button" data-add-to-cart="{{ $product->id }}" @disabled(! $isRegisterOpen)>
                                    + ADD
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Panel 3: Itemised Cart -->
            <div class="flex flex-col gap-4 overflow-hidden rounded-2xl bg-white p-6 shadow-lg border border-slate-100">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Order Items</h3>
                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-600 font-bold border-b-2 border-slate-200">
                                <th class="text-left pb-2">Item</th>
                                <th class="text-center w-14 pb-2">Qty</th>
                                <th class="text-right w-20 pb-2">Total</th>
                            </tr>
                        </thead>
                        <tbody data-online-items class="divide-y divide-slate-100">
                            <tr class="text-slate-400 text-center h-12" data-online-empty-row>
                                <td colspan="3">No items added yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t-2 border-slate-200 pt-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Subtotal</span>
                        <span class="text-lg font-bold text-slate-900" data-online-subtotal>{{ $currency }} 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Panel 4: Cart & Payment -->
            <div class="flex flex-col gap-4 overflow-y-auto rounded-2xl bg-white p-6 shadow-lg border border-slate-100">
                <!-- Payment Status -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-3 uppercase tracking-wider">Status</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="py-2 px-3 rounded-lg font-bold text-xs border-2 border-gray-300 text-gray-600 hover:border-emerald-500 transition" data-payment-status="paid" data-toggle-payment>
                            PAID
                        </button>
                        <button type="button" class="py-2 px-3 rounded-lg font-bold text-xs border-2 border-emerald-500 bg-emerald-50 text-emerald-700" data-payment-status="unpaid" data-toggle-payment>
                            UNPAID
                        </button>
                    </div>
                    <input type="hidden" name="payment_status" value="cash_on_delivery" data-payment-status-input>
                </div>

                <!-- Fees Summary -->
                <div class="border-t border-slate-200 pt-4 space-y-2">
                    <div class="flex justify-between text-xs text-slate-600">
                        <span>Tax</span>
                        <span class="font-semibold text-slate-900">{{ $currency }} 0.00</span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-600">
                        <span>Delivery</span>
                        <span class="font-semibold text-slate-900">{{ $currency }} 0.00</span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-600">
                        <span>Other Fees</span>
                        <span class="font-semibold text-slate-900">{{ $currency }} 0.00</span>
                    </div>
                </div>

                <!-- Discount -->
                <div class="border-t border-slate-200 pt-4">
                    <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Discount</label>
                    <div class="flex gap-2">
                        <input class="form-control flex-1 border border-slate-200 rounded-lg text-sm" type="number" step="0.01" min="0" value="0" name="discount_value" data-online-discount-value placeholder="Amount" @disabled(! $isRegisterOpen)>
                        <select class="form-control w-20 border border-slate-200 rounded-lg text-sm font-semibold" name="discount_type" data-online-discount-type @disabled(! $isRegisterOpen)>
                            <option value="fixed">Fixed</option>
                            <option value="percentage">%</option>
                        </select>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Discount: <span class="font-bold text-slate-700" data-online-discount>{{ $currency }} 0.00</span></p>
                </div>

                <!-- Totals Section -->
                <div class="border-t border-slate-200 pt-4 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600 font-semibold">Net Profit</span>
                        <span class="text-lg font-bold text-emerald-600" data-online-net-profit>{{ $currency }} 0.00</span>
                    </div>
                    <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-lg p-4 border-2 border-emerald-200">
                        <p class="text-xs text-emerald-700 font-semibold uppercase tracking-wider mb-1">Grand Total</p>
                        <p class="text-3xl font-bold text-emerald-600" data-online-total>{{ $currency }} 0.00</p>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="border-t border-slate-200 pt-4">
                    <label class="block text-xs font-bold text-slate-700 mb-3 uppercase tracking-wider">Method</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach(['cash' => '💵', 'card' => '💳', 'online' => '🌐'] as $method => $icon)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="payment_method" value="{{ $method }}" class="sr-only peer" @if($method === 'online') checked @endif @disabled(! $isRegisterOpen)>
                                <div class="w-full py-3 px-2 rounded-lg border-2 border-slate-300 text-center text-lg peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition font-bold hover:border-slate-400">
                                    {{ $icon }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Submit Button -->
                <form method="POST" action="{{ route('online-orders.store') }}" class="mt-4" data-online-create-form>
                    @csrf
                    <input type="hidden" name="online_order_source_id" data-platform-select-value>
                    <input type="hidden" name="order_reference">
                    <input type="hidden" name="customer_name">
                    <input type="hidden" name="customer_phone">
                    <input type="hidden" name="delivery_address">
                    <input type="hidden" name="notes">
                    <input type="hidden" name="discount_value">
                    <input type="hidden" name="discount_type">
                    <input type="hidden" name="payment_status" value="cash_on_delivery">
                    <input type="hidden" name="payment_method" value="online">
                    <input type="hidden" name="order_status" value="new">
                    <input type="hidden" name="delivery_charge" value="0">
                    <input type="hidden" name="paid_amount" value="0">
                    <input type="hidden" data-online-items-payload name="items_payload">

                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold py-3 rounded-lg transition shadow-lg" @disabled(! $isRegisterOpen)>
                        ✓ Finalize Order
                    </button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const root = document.getElementById('online-orders-root');
                if (!root) return;

                const currency = root.dataset.currency || 'Rs.';
                const itemsBody = root.querySelector('[data-online-items]');
                const emptyRow = root.querySelector('[data-online-empty-row]');
                const platformSelect = root.querySelector('[data-platform-select]');
                const platformName = root.querySelector('[data-platform-name]');
                const commissionBadge = root.querySelector('[data-commission-badge]');
                const discountTypeInput = root.querySelector('[data-online-discount-type]');
                const discountValueInput = root.querySelector('[data-online-discount-value]');
                const subtotalEl = root.querySelector('[data-online-subtotal]');
                const discountEl = root.querySelector('[data-online-discount]');
                const totalEl = root.querySelector('[data-online-total]');
                const netProfitEl = root.querySelector('[data-online-net-profit]');
                const payloadInput = root.querySelector('[data-online-items-payload]');
                const paymentStatusButtons = root.querySelectorAll('[data-toggle-payment]');
                const paymentStatusInput = root.querySelector('[data-payment-status-input]');
                const createForm = root.querySelector('[data-online-create-form]');
                const platformSelectValue = root.querySelector('[data-platform-select-value]');

                const rows = [];

                const money = (value) => `${currency} ${Number(value || 0).toFixed(2)}`;

                // Platform selection
                platformSelect?.addEventListener('change', () => {
                    const selected = platformSelect.selectedOptions[0];
                    const name = selected.textContent || 'Select platform';
                    const type = selected.dataset.commissionType || 'percentage';
                    const value = selected.dataset.commissionValue || '0';

                    platformName.textContent = name;
                    commissionBadge.textContent = type === 'percentage' ? `${value}%` : `${value}`;
                    refresh();
                });

                // Payment status toggle
                paymentStatusButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        paymentStatusButtons.forEach((b) => {
                            b.classList.remove('border-emerald-500', 'bg-emerald-50', 'text-emerald-700', 'font-bold');
                            b.classList.add('border-gray-300', 'text-gray-600');
                        });
                        btn.classList.remove('border-gray-300', 'text-gray-600');
                        btn.classList.add('border-emerald-500', 'bg-emerald-50', 'text-emerald-700', 'font-bold');
                        const status = btn.dataset.paymentStatus;
                        const paymentStatus = status === 'paid' ? 'paid' : 'cash_on_delivery';
                        paymentStatusInput.value = paymentStatus;
                        if (createForm) {
                            createForm.querySelector('[name="payment_status"]').value = paymentStatus;
                        }
                    });
                });

                // Add to cart
                root.querySelectorAll('[data-add-to-cart]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const productId = Number(btn.dataset.addToCart);
                        const card = root.querySelector(`[data-product-card="${productId}"]`);
                        if (!card) return;

                        const id = productId;
                        const name = card.dataset.productName || '';
                        const price = Number(card.dataset.productPrice || 0);
                        const qty = 1;

                        if (id <= 0 || price < 0) return;

                        const existing = rows.find((item) => item.id === id && item.price === price);
                        if (existing) {
                            existing.qty += qty;
                        } else {
                            rows.push({id, name, qty, price});
                        }
                        refresh();
                    });
                });

                const refresh = () => {
                    itemsBody.querySelectorAll('tr[data-online-row]').forEach((row) => row.remove());

                    rows.forEach((item, index) => {
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-online-row', '1');
                        tr.innerHTML = `
                            <td class="text-slate-900 text-xs font-semibold truncate py-2">${item.name}</td>
                            <td>
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" class="text-slate-600 hover:text-slate-900 font-bold text-sm" data-qty-minus="${index}">−</button>
                                    <span class="w-6 text-center text-xs font-bold">${item.qty.toFixed(1)}</span>
                                    <button type="button" class="text-slate-600 hover:text-slate-900 font-bold text-sm" data-qty-plus="${index}">+</button>
                                </div>
                            </td>
                            <td class="text-right text-slate-900 font-bold text-xs">${money(item.qty * item.price)}</td>
                        `;
                        itemsBody.appendChild(tr);
                    });

                    if (rows.length === 0) {
                        emptyRow?.classList.remove('hidden');
                    } else {
                        emptyRow?.classList.add('hidden');
                    }

                    const subtotal = rows.reduce((sum, item) => sum + (item.qty * item.price), 0);
                    const discountValue = Number(discountValueInput?.value || 0);
                    const discount = (discountTypeInput?.value === 'percentage') ? subtotal * (discountValue / 100) : discountValue;
                    const total = Math.max(0, subtotal - Math.min(discount, subtotal));

                    // Get commission from platform
                    const platformOption = platformSelect?.selectedOptions[0];
                    let commission = 0;
                    if (platformOption) {
                        const type = platformOption.dataset.commissionType || 'percentage';
                        const value = Number(platformOption.dataset.commissionValue || 0);
                        commission = type === 'percentage' ? (subtotal * value / 100) : value;
                    }

                    const netProfit = total - commission;

                    subtotalEl.textContent = money(subtotal);
                    discountEl.textContent = money(Math.min(discount, subtotal));
                    totalEl.textContent = money(total);
                    netProfitEl.textContent = money(netProfit);
                    payloadInput.value = JSON.stringify(rows);
                };

                // Quantity controls
                itemsBody?.addEventListener('click', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) return;

                    const minusIndex = target.dataset.qtyMinus;
                    const plusIndex = target.dataset.qtyPlus;

                    if (minusIndex !== undefined) {
                        const index = Number(minusIndex);
                        if (rows[index]) {
                            rows[index].qty = Math.max(0.001, rows[index].qty - 0.5);
                            if (rows[index].qty < 0.001) rows.splice(index, 1);
                            refresh();
                        }
                    }

                    if (plusIndex !== undefined) {
                        const index = Number(plusIndex);
                        if (rows[index]) {
                            rows[index].qty += 0.5;
                            refresh();
                        }
                    }
                });

                [discountTypeInput, discountValueInput, platformSelect].forEach((element) => {
                    element?.addEventListener('input', refresh);
                    element?.addEventListener('change', refresh);
                });

                // Form submission handler
                createForm?.addEventListener('submit', (event) => {
                    event.preventDefault();

                    // Validate platform selection
                    if (!platformSelect?.value) {
                        alert('Please select a platform first.');
                        return;
                    }

                    // Validate items
                    if (rows.length === 0) {
                        alert('Please add at least one item to the order.');
                        return;
                    }

                    // Get visible form inputs
                    const customerNameInput = root.querySelector('input[name="customer_name"]');
                    const orderReferenceInput = root.querySelector('input[name="order_reference"]');
                    const customerPhoneInput = root.querySelector('input[name="customer_phone"]');
                    const deliveryAddressInput = root.querySelector('textarea[name="delivery_address"]');
                    const notesInput = root.querySelector('textarea[name="notes"]');

                    // Validate customer name
                    if (!customerNameInput?.value?.trim()) {
                        alert('Please enter customer name.');
                        return;
                    }

                    // Validate order reference
                    if (!orderReferenceInput?.value?.trim()) {
                        alert('Please enter order reference.');
                        return;
                    }

                    // Populate hidden form fields
                    createForm.querySelector('[name="online_order_source_id"]').value = platformSelect.value;
                    createForm.querySelector('[name="order_reference"]').value = orderReferenceInput.value;
                    createForm.querySelector('[name="customer_name"]').value = customerNameInput.value;
                    createForm.querySelector('[name="customer_phone"]').value = customerPhoneInput.value || '';
                    createForm.querySelector('[name="delivery_address"]').value = deliveryAddressInput.value || '';
                    createForm.querySelector('[name="notes"]').value = notesInput.value || '';
                    createForm.querySelector('[name="discount_value"]').value = discountValueInput.value;
                    createForm.querySelector('[name="discount_type"]').value = discountTypeInput.value;
                    createForm.querySelector('[name="payment_status"]').value = paymentStatusInput.value;
                    createForm.querySelector('[name="items_payload"]').value = JSON.stringify(rows);

                    // Submit the form
                    createForm.submit();
                });

                refresh();
            })();
        </script>
    @endpush
</x-layouts.app>
