<x-layouts.app heading="Online orders" title="Online orders" :pos-fullscreen="true" :show-date-filter="false">
    <div class="online-workspace grid gap-5" data-online-orders data-currency="{{ $currency }}" data-feed-url="{{ route('online-orders.feed') }}">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="online-eyebrow">Delivery & collection</p><h2 class="text-2xl font-bold text-slate-900">Every channel. One kitchen.</h2><p class="mt-1 text-sm text-slate-500">Enter an order from your platform, check payment, then send it to the kitchen.</p></div>
            <div class="flex flex-wrap gap-2"><a href="#online-order-list" class="btn-secondary">Track orders</a>@can('online_order_sources.view')<a href="{{ route('backoffice.modules.index', 'online_order_sources') }}" class="btn-secondary">Manage platforms</a>@endcan</div>
        </div>
        @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert"><strong>Please check your order.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @can('online_orders.create')
        <form method="POST" action="{{ route('online-orders.store') }}" data-online-create-form class="online-entry-grid grid items-start gap-5">
            @csrf
            <input type="hidden" name="order_status" value="new">
            <input type="hidden" name="items_payload" value="{{ old('items_payload', '[]') }}" data-online-payload>
            <div class="grid min-w-0 gap-5">
                <section class="pos-card online-panel">
                    <div class="online-section-heading"><span>1</span><div><h3>Choose the platform</h3><p>Use the reference shown on the platform to avoid entering an order twice.</p></div></div>
                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
                        @forelse($sources as $source)
                        <label class="online-source"><input type="radio" name="online_order_source_id" value="{{ $source->id }}" required @checked(old('online_order_source_id') == $source->id) data-source-name="{{ $source->name }}" data-commission-type="{{ $source->commission_type }}" data-commission="{{ $source->commission_value }}" data-payment-method="{{ $source->default_payment_method }}"><span><strong>{{ $source->name }}</strong><small>{{ $source->commission_type === 'percentage' ? number_format($source->commission_value, 0).'%' : $currency.' '.number_format($source->commission_value, 2) }} commission</small></span></label>
                        @empty<p class="col-span-full text-sm text-amber-700">Add an active platform in Settings → Online platforms before taking orders.</p>@endforelse
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="online-label">Platform order reference <input class="form-control" name="order_reference" value="{{ old('order_reference') }}" maxlength="255" required placeholder="For example: UBER-10025"></label>
                        <label class="online-label">Customer name <input class="form-control" name="customer_name" value="{{ old('customer_name') }}" maxlength="255" required placeholder="Name on the order"></label>
                        <label class="online-label">Phone <span class="sr-only">optional</span><input class="form-control" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" maxlength="50" placeholder="Optional · contact number"></label>
                        <label class="online-label">Delivery address / collection details <textarea class="form-control" name="delivery_address" rows="1" maxlength="2000" placeholder="Address, or customer collection">{{ old('delivery_address') }}</textarea></label>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Record orders received through the platform app, website or messages. Platform commission is configured in settings.</p>
                </section>
                <section class="pos-card online-panel">
                    <div class="online-section-heading"><span>2</span><div><h3>Add menu items</h3><p>Adjust quantities and platform prices in the order summary.</p></div></div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_12rem]"><input class="form-control" type="search" data-online-search placeholder="Search the menu…" aria-label="Search menu items"><select class="form-control" data-online-category aria-label="Menu category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                    <div class="online-menu mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach($products as $product)
                        <button type="button" class="online-product" data-online-product="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->selling_price }}" data-cost="{{ $product->cost_price }}" data-category="{{ $product->category_id }}" @disabled($product->maintain_stock && $product->stock_quantity <= 0)>
                            <span class="online-product-icon">{{ mb_substr($product->name, 0, 1) }}</span><strong>{{ $product->name }}</strong><span>{{ $currency }} {{ number_format($product->selling_price, 2) }}</span><small>{{ $product->maintain_stock && $product->stock_quantity <= 0 ? 'Out of stock' : '+ Add to order' }}</small>
                        </button>
                        @endforeach
                    </div>
                    <p class="hidden mt-5 text-center text-sm text-slate-500" data-online-no-products>No menu items match your search.</p>
                </section>
            </div>
            <aside class="pos-card online-panel online-summary grid gap-4">
                <div class="online-section-heading"><span>3</span><div><h3>Review & send</h3><p data-online-source-label>Select a platform to begin</p></div></div>
                <div class="online-cart" data-online-cart><p class="online-cart-empty">Your order is empty.<br><span>Choose items from the menu.</span></p></div>
                <label class="online-label">Kitchen instructions <textarea class="form-control" name="notes" rows="2" maxlength="2000" placeholder="Allergies, spice level, packaging…">{{ old('notes') }}</textarea></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="online-label">Discount type <select class="form-control" name="discount_type" data-online-discount-type><option value="fixed" @selected(old('discount_type') !== 'percentage')>Fixed amount</option><option value="percentage" @selected(old('discount_type') === 'percentage')>Percentage</option></select></label>
                    <label class="online-label">Discount <input class="form-control" type="number" min="0" step="0.01" name="discount_value" value="{{ old('discount_value', 0) }}" data-online-discount></label>
                    <label class="online-label col-span-2">Delivery charge <input class="form-control" type="number" min="0" step="0.01" name="delivery_charge" value="{{ old('delivery_charge', 0) }}" data-online-delivery></label>
                </div>
                <dl class="online-totals grid gap-2"><div><dt>Items subtotal</dt><dd data-online-subtotal>{{ $currency }} 0.00</dd></div><div><dt>Discount</dt><dd data-online-discount-total>− {{ $currency }} 0.00</dd></div><div><dt>Delivery</dt><dd data-online-delivery-total>{{ $currency }} 0.00</dd></div><div class="online-grand-total"><dt>Customer total</dt><dd data-online-total>{{ $currency }} 0.00</dd></div><div class="text-xs text-slate-500"><dt>Platform commission</dt><dd data-online-commission>{{ $currency }} 0.00</dd></div><div class="text-xs text-slate-500"><dt>Estimated profit after costs</dt><dd data-online-profit>{{ $currency }} 0.00</dd></div></dl>
                <div class="grid grid-cols-2 gap-3">
                    <label class="online-label">Payment status <select name="payment_status" class="form-control" data-online-payment-status>@foreach(['pending' => 'Not paid yet', 'paid' => 'Already paid', 'cash_on_delivery' => 'Cash on delivery', 'partially_paid' => 'Part payment'] as $value => $label)<option value="{{ $value }}" @selected(old('payment_status', 'pending') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="online-label">Payment method <select name="payment_method" class="form-control" data-online-payment-method>@foreach(['platform_payment' => 'Platform collects', 'cash' => 'Cash', 'card' => 'Card', 'bank' => 'Bank transfer', 'online' => 'Online payment'] as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', 'platform_payment') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="online-label col-span-2" data-online-part-payment hidden>Amount already paid <input type="number" class="form-control" name="paid_amount" step="0.01" min="0.01" value="{{ old('paid_amount') }}" data-online-paid></label>
                </div>
                <p class="online-payment-help text-xs text-slate-500" data-online-payment-help></p>
                <p class="hidden rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert" data-online-error></p>
                <button type="submit" class="btn-primary w-full" data-online-submit><x-lucide name="cooking-pot" class="size-5" />Accept &amp; send to kitchen</button>
                <p class="text-center text-xs text-slate-500">Saved immediately. Unpaid orders stay available for payment later.</p>
            </aside>
        </form>
        @endcan
        @if(auth()->user()->can('pos.access') || auth()->user()->can('kitchen.view'))
        <details class="online-kitchen"><summary>Live kitchen queue <span>View ready orders and send stop requests</span></summary><div class="mt-3"><x-kitchen-queue /></div></details>
        @endif
        @include('pos.partials.online-order-list')
    </div>
</x-layouts.app>
