<x-layouts.app heading="Online Orders" title="Online Orders" :pos-fullscreen="true" :show-date-filter="false">
    @php
        $isRegisterOpen = (bool) $register;
    @endphp

    <div id="online-orders-root" class="pos-neo space-y-4" data-currency="{{ $currency }}">
        @if(! $isRegisterOpen)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-700">
                Open register first. Online order creation is disabled until the register is open.
            </div>
        @endif

        <section class="pos-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-slate-900">Create Online Order</h2>
                    <p class="mt-1 text-xs font-bold text-slate-500">Use the same POS flow for Uber Eats, PickMe, Website, WhatsApp, and custom sources.</p>
                </div>
                <a href="{{ route('backoffice.modules.index', 'online_order_sources') }}" class="btn-secondary">Manage Platforms</a>
            </div>

            <form method="POST" action="{{ route('online-orders.store') }}" class="mt-4 grid gap-4" data-online-create-form>
                @csrf

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Platform / Source
                        <select class="form-control" name="online_order_source_id" required @disabled(! $isRegisterOpen)>
                            <option value="">Select source</option>
                            @foreach($sources as $source)
                                <option value="{{ $source->id }}" @selected(request('online_order_source_id') == $source->id)>
                                    {{ $source->name }} ({{ $source->commission_type === 'percentage' ? $source->commission_value.'%' : $currency.' '.number_format((float) $source->commission_value, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Order Reference
                        <input class="form-control" name="order_reference" placeholder="UBER-10025" required @disabled(! $isRegisterOpen)>
                    </label>

                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Customer Name
                        <input class="form-control" name="customer_name" placeholder="Customer name" required @disabled(! $isRegisterOpen)>
                    </label>

                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Customer Phone
                        <input class="form-control" name="customer_phone" placeholder="Phone number" @disabled(! $isRegisterOpen)>
                    </label>
                </div>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Delivery Address
                    <textarea class="form-control min-h-20" name="delivery_address" placeholder="Delivery address" @disabled(! $isRegisterOpen)></textarea>
                </label>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3">
                    <input class="form-control mb-3" type="text" placeholder="Search products..." data-online-product-search @disabled(! $isRegisterOpen)>
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_6rem_6rem_auto]">
                        <select class="form-control" data-online-product @disabled(! $isRegisterOpen)>
                            <option value="">Select product</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->selling_price }}">{{ $product->name }} ({{ $currency }} {{ number_format((float) $product->selling_price, 2) }})</option>
                            @endforeach
                        </select>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Qty</label>
                            <input class="form-control" type="number" step="0.001" min="0.001" value="1" data-online-qty @disabled(! $isRegisterOpen)>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1" style="height: 20px;"></label>
                            <button class="btn-primary w-full" type="button" data-online-add-item @disabled(! $isRegisterOpen)>Add</button>
                        </div>
                    </div>

                    <div class="mt-3 data-table-wrap">
                        <table class="data-table min-w-full">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-online-items>
                                <tr data-online-empty-row><td colspan="5" class="text-center text-slate-500">No items selected.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <input type="hidden" name="items_payload" data-online-items-payload>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Discount Type
                        <select class="form-control" name="discount_type" data-online-discount-type @disabled(! $isRegisterOpen)>
                            <option value="fixed">Fixed</option>
                            <option value="percentage">Percentage</option>
                        </select>
                    </label>

                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Discount Value
                        <input class="form-control" type="number" step="0.01" min="0" value="0" name="discount_value" data-online-discount-value @disabled(! $isRegisterOpen)>
                    </label>

                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Payment Status
                        <select class="form-control" name="payment_status" data-online-payment-status @disabled(! $isRegisterOpen)>
                            <option value="paid">Paid</option>
                            <option value="cash_on_delivery">Cash on Delivery</option>
                            <option value="pending">Pending</option>
                            <option value="partially_paid">Partially Paid</option>
                        </select>
                    </label>

                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Payment Method
                        <select class="form-control" name="payment_method" @disabled(! $isRegisterOpen)>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                            <option value="platform_payment">Platform Payment</option>
                        </select>
                    </label>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Paid Amount
                        <input class="form-control" type="number" step="0.01" min="0" value="0" name="paid_amount" @disabled(! $isRegisterOpen)>
                    </label>

                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Net Online Profit (Preview)
                        <input class="form-control" type="text" data-online-net-profit value="{{ $currency }} 0.00" readonly>
                    </label>
                </div>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Notes
                    <textarea class="form-control min-h-20" name="notes" placeholder="Any order note" @disabled(! $isRegisterOpen)></textarea>
                </label>

                <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-3 text-sm font-bold text-slate-700">
                    Subtotal: <span data-online-subtotal>{{ $currency }} 0.00</span> |
                    Discount: <span data-online-discount>{{ $currency }} 0.00</span> |
                    Total: <span data-online-total>{{ $currency }} 0.00</span>
                </div>

                <div class="flex justify-end">
                    <button class="btn-success" @disabled(! $isRegisterOpen)>Create Online Order</button>
                </div>
            </form>
        </section>

        <section class="pos-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-black text-slate-900">Online Order List</h2>
                <form method="GET" class="flex flex-wrap gap-2">
                    <input class="form-control max-w-48" name="search" value="{{ request('search') }}" placeholder="Search">
                    <select class="form-control max-w-44" name="source_id">
                        <option value="all">All Platforms</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}" @selected(request('source_id') == $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-control max-w-40" name="payment_status">
                        <option value="all">All Payment</option>
                        @foreach(['paid' => 'Paid', 'cash_on_delivery' => 'COD', 'pending' => 'Pending', 'partially_paid' => 'Partially Paid'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('payment_status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="form-control max-w-40" name="order_status">
                        <option value="all">All Status</option>
                        @foreach(['new' => 'New', 'preparing' => 'Preparing', 'ready' => 'Ready', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('order_status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn-secondary">Filter</button>
                </form>
            </div>

            <div class="mt-3 data-table-wrap">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Platform</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Commission</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td class="font-bold">{{ $order->order_reference }}</td>
                                <td>{{ $order->source_name }}</td>
                                <td>{{ $order->customer_name }}</td>
                                <td>{{ $order->customer_phone ?: '-' }}</td>
                                <td>{{ $currency }} {{ number_format((float) $order->total, 2) }}</td>
                                <td>{{ $currency }} {{ number_format((float) $order->paid_amount, 2) }}</td>
                                <td>{{ $currency }} {{ number_format((float) $order->balance_amount, 2) }}</td>
                                <td>{{ str($order->payment_status)->replace('_', ' ')->headline() }}</td>
                                <td>{{ str($order->order_status)->replace('_', ' ')->headline() }}</td>
                                <td>{{ $currency }} {{ number_format((float) $order->commission_amount, 2) }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        @can('online_orders.print')
                                            <a class="btn-mini" href="{{ route('online-orders.print', $order->id) }}" target="_blank">Print</a>
                                        @endcan
                                        @can('online_orders.add_payment')
                                            <button class="btn-mini" type="button" data-modal-open="payment-modal-{{ $order->id }}">Add Payment</button>
                                        @endcan
                                        @can('online_orders.edit')
                                            <form method="POST" action="{{ route('online-orders.status', $order->id) }}">
                                                @csrf
                                                <select class="form-control !min-h-8 !rounded-lg !px-2 !py-1 !text-xs" name="order_status" onchange="this.form.submit()">
                                                    @foreach(['new','preparing','ready','out_for_delivery','delivered','cancelled'] as $status)
                                                        <option value="{{ $status }}" @selected($order->order_status === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @endcan
                                    </div>

                                    @can('online_orders.add_payment')
                                        <div id="payment-modal-{{ $order->id }}" class="modal">
                                            <div class="modal-panel">
                                                <button class="modal-close" type="button" data-modal-close>×</button>
                                                <h2 class="text-xl font-black text-slate-900">Add Payment - {{ $order->order_reference }}</h2>
                                                <p class="mt-1 text-sm font-semibold text-slate-500">Balance: {{ $currency }} {{ number_format((float) $order->balance_amount, 2) }}</p>
                                                <form method="POST" action="{{ route('online-orders.payment', $order->id) }}" class="mt-4 grid gap-3">
                                                    @csrf
                                                    <label class="grid gap-2 text-sm font-black text-slate-700">
                                                        Amount
                                                        <input class="form-control" type="number" step="0.01" min="0.01" max="{{ $order->balance_amount }}" name="amount" required>
                                                    </label>
                                                    <label class="grid gap-2 text-sm font-black text-slate-700">
                                                        Method
                                                        <select class="form-control" name="payment_method">
                                                            <option value="cash">Cash</option>
                                                            <option value="bank">Bank</option>
                                                            <option value="card">Card</option>
                                                            <option value="online">Online</option>
                                                            <option value="platform_payment">Platform Payment</option>
                                                        </select>
                                                    </label>
                                                    <label class="grid gap-2 text-sm font-black text-slate-700">
                                                        Payment Date
                                                        <input class="form-control" type="datetime-local" name="payment_date" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                                                    </label>
                                                    <label class="grid gap-2 text-sm font-black text-slate-700">
                                                        Note
                                                        <textarea class="form-control min-h-20" name="note"></textarea>
                                                    </label>
                                                    <button class="btn-primary">Save Payment</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center text-slate-500">No online orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $orders->links() }}</div>
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                const root = document.getElementById('online-orders-root');
                if (!root) return;

                const currency = root.dataset.currency || 'Rs.';
                const itemsBody = root.querySelector('[data-online-items]');
                const emptyRow = root.querySelector('[data-online-empty-row]');
                const addButton = root.querySelector('[data-online-add-item]');
                const productSelect = root.querySelector('[data-online-product]');
                const productSearch = root.querySelector('[data-online-product-search]');
                const qtyInput = root.querySelector('[data-online-qty]');
                const discountTypeInput = root.querySelector('[data-online-discount-type]');
                const discountValueInput = root.querySelector('[data-online-discount-value]');
                const payloadInput = root.querySelector('[data-online-items-payload]');
                const subtotalEl = root.querySelector('[data-online-subtotal]');
                const discountEl = root.querySelector('[data-online-discount]');
                const totalEl = root.querySelector('[data-online-total]');
                const netProfitEl = root.querySelector('[data-online-net-profit]');
                const sourceSelect = root.querySelector('[name="online_order_source_id"]');

                const rows = [];

                const money = (value) => `${currency} ${Number(value || 0).toFixed(2)}`;

                const commissionFromSource = () => {
                    if (!sourceSelect || !sourceSelect.selectedOptions[0]) return 0;
                    const text = sourceSelect.selectedOptions[0].textContent || '';
                    const percentMatch = text.match(/\(([-\d.]+)%\)/);
                    if (percentMatch) return {type: 'percentage', value: Number(percentMatch[1])};
                    const fixedMatch = text.match(/\((.+)\)/);
                    if (fixedMatch) {
                        const clean = fixedMatch[1].replace(/[A-Za-z\s.]/g, '');
                        const fixed = Number(clean.replace(',', ''));
                        if (!Number.isNaN(fixed)) return {type: 'fixed', value: fixed};
                    }
                    return {type: 'percentage', value: 0};
                };

                const refresh = () => {
                    itemsBody.querySelectorAll('tr[data-online-row]').forEach((row) => row.remove());

                    rows.forEach((item, index) => {
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-online-row', '1');
                        tr.innerHTML = `
                            <td>${item.name}</td>
                            <td>${item.qty.toFixed(3)}</td>
                            <td>${money(item.price)}</td>
                            <td>${money(item.qty * item.price)}</td>
                            <td><button type="button" class="text-red-600" data-remove-index="${index}">Remove</button></td>
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
                    const commissionConfig = commissionFromSource();
                    const commission = commissionConfig.type === 'percentage' ? (subtotal - Math.min(discount, subtotal)) * (commissionConfig.value / 100) : commissionConfig.value;
                    const netProfit = total - commission;

                    subtotalEl.textContent = money(subtotal);
                    discountEl.textContent = money(Math.min(discount, subtotal));
                    totalEl.textContent = money(total);
                    netProfitEl.value = money(netProfit);
                    payloadInput.value = JSON.stringify(rows);
                };

                addButton?.addEventListener('click', () => {
                    if (!productSelect?.value) {
                        return;
                    }

                    const selected = productSelect.selectedOptions[0];
                    const id = Number(productSelect.value);
                    const qty = Number(qtyInput?.value || 0);
                    const price = Number(selected.dataset.price || 0);
                    const name = selected.dataset.name || selected.textContent;

                    if (id <= 0 || qty <= 0 || price < 0) {
                        return;
                    }

                    const existing = rows.find((item) => item.id === id && item.price === price);
                    if (existing) {
                        existing.qty += qty;
                    } else {
                        rows.push({id, name, qty, price});
                    }

                    refresh();
                });

                itemsBody?.addEventListener('click', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) return;

                    const removeIndex = target.dataset.removeIndex;
                    if (removeIndex === undefined) return;
                    rows.splice(Number(removeIndex), 1);
                    refresh();
                });

                // Product search filter
                productSearch?.addEventListener('input', (e) => {
                    const searchTerm = (e.target.value || '').toLowerCase();
                    Array.from(productSelect.options).forEach((option) => {
                        if (option.value === '') {
                            option.style.display = '';
                        } else {
                            const text = (option.textContent || '').toLowerCase();
                            option.style.display = text.includes(searchTerm) ? '' : 'none';
                        }
                    });
                });

                // Product search filter
                productSearch?.addEventListener('input', (e) => {
                    const searchTerm = (e.target.value || '').toLowerCase();
                    Array.from(productSelect.options).forEach((option) => {
                        if (option.value === '') {
                            option.style.display = '';
                        } else {
                            const text = (option.textContent || '').toLowerCase();
                            option.style.display = text.includes(searchTerm) ? '' : 'none';
                        }
                    });
                });
                // Product search filter
                productSearch?.addEventListener('input', (e) => {
                    const searchTerm = (e.target.value || '').toLowerCase();
                    Array.from(productSelect.options).forEach((option) => {
                        if (option.value === '') {
                            option.style.display = '';
                        } else {
                            const text = (option.textContent || '').toLowerCase();
                            option.style.display = text.includes(searchTerm) ? '' : 'none';
                        }
                    });
                });
                [discountTypeInput, discountValueInput, sourceSelect].forEach((element) => {
                    element?.addEventListener('input', refresh);
                    element?.addEventListener('change', refresh);
                });

                root.querySelectorAll('[data-online-create-form]').forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        if (rows.length === 0) {
                            event.preventDefault();
                            alert('Add at least one item.');
                        }
                    });
                });

                refresh();
            })();
        </script>
    @endpush
</x-layouts.app>
