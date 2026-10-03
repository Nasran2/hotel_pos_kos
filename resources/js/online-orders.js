const root = document.querySelector('[data-online-orders]');
if (root) {
    const money = (value) => `${root.dataset.currency} ${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const round = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
    const label = (value) => String(value ?? 'not_sent').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    const form = root.querySelector('[data-online-create-form]');
    if (form) {
        const productButtons = [...form.querySelectorAll('[data-online-product]')];
        const products = new Map(productButtons.map((button) => [Number(button.dataset.onlineProduct), { id: Number(button.dataset.onlineProduct), name: button.dataset.name, price: Number(button.dataset.price), cost: Number(button.dataset.cost), available: !button.disabled }]));
        const payload = form.querySelector('[data-online-payload]');
        const cart = form.querySelector('[data-online-cart]');
        const discount = form.querySelector('[data-online-discount]');
        const discountType = form.querySelector('[data-online-discount-type]');
        const delivery = form.querySelector('[data-online-delivery]');
        const paymentStatus = form.querySelector('[data-online-payment-status]');
        const paymentMethod = form.querySelector('[data-online-payment-method]');
        const paid = form.querySelector('[data-online-paid]');
        const error = form.querySelector('[data-online-error]');
        let rows = [];
        let submitting = false;
        let total = 0;
        try {
            const old = JSON.parse(payload.value);
            if (Array.isArray(old)) rows = old.filter((item) => item && products.has(Number(item.id))).map((item) => ({ id: Number(item.id), qty: Number(item.qty), price: Number(item.price) }));
        } catch { rows = []; }
        const calculate = () => {
            const source = form.querySelector('[name="online_order_source_id"]:checked');
            const subtotal = round(rows.reduce((sum, item) => sum + round(item.qty * item.price), 0));
            const discountAmount = round(Math.min(subtotal, discountType.value === 'percentage' ? subtotal * Number(discount.value || 0) / 100 : Number(discount.value || 0)));
            const commission = round(source ? source.dataset.commissionType === 'percentage' ? (subtotal - discountAmount) * Number(source.dataset.commission) / 100 : Number(source.dataset.commission) : 0);
            total = round(Math.max(0, subtotal - discountAmount + Number(delivery.value || 0)));
            const cost = rows.reduce((sum, item) => sum + products.get(item.id).cost * item.qty, 0);
            form.querySelector('[data-online-subtotal]').textContent = money(subtotal);
            form.querySelector('[data-online-discount-total]').textContent = `− ${money(discountAmount)}`;
            form.querySelector('[data-online-delivery-total]').textContent = money(delivery.value || 0);
            form.querySelector('[data-online-total]').textContent = money(total);
            form.querySelector('[data-online-commission]').textContent = money(commission);
            form.querySelector('[data-online-profit]').textContent = money(subtotal - cost - discountAmount - commission);
            form.querySelector('[data-online-source-label]').textContent = source ? `${source.dataset.sourceName} · ${rows.reduce((sum, item) => sum + item.qty, 0)} items` : 'Select a platform to begin';
            payload.value = JSON.stringify(rows);
            paid.max = total;
            paid.disabled = paymentStatus.value !== 'partially_paid';
            paid.required = !paid.disabled;
            form.querySelector('[data-online-part-payment]').hidden = paid.disabled;
            form.querySelector('[data-online-payment-help]').textContent = {
                pending: `Outstanding balance: ${money(total)}. Add payment after collection or platform settlement.`,
                cash_on_delivery: `Collect ${money(total)} on delivery. No payment is recorded yet.`,
                paid: `A receipt of ${money(total)} will be recorded using the selected payment method.`,
                partially_paid: `Remaining balance: ${money(Math.max(0, total - Number(paid.value || 0)))}.`,
            }[paymentStatus.value];
            discount.max = discountType.value === 'percentage' ? 100 : subtotal;
            for (const item of rows) {
                cart.querySelector(`[data-line-total="${item.id}"]`).textContent = money(round(item.qty * item.price));
            }
        };
        const render = () => {
            cart.innerHTML = rows.length ? rows.map((item) => `<div class="online-cart-line"><div class="flex items-start justify-between gap-3"><strong>${escape(products.get(item.id).name)}</strong><button type="button" data-online-remove="${item.id}" aria-label="Remove ${escape(products.get(item.id).name)}">Remove</button></div><div class="online-cart-controls"><label>Qty<div class="online-quantity"><button type="button" data-online-adjust="${item.id}" data-delta="-1" aria-label="Decrease ${escape(products.get(item.id).name)}">−</button><input type="number" step="0.001" min="0.001" max="99999" value="${item.qty}" data-online-quantity="${item.id}" aria-label="Quantity of ${escape(products.get(item.id).name)}" required><button type="button" data-online-adjust="${item.id}" data-delta="1" aria-label="Increase ${escape(products.get(item.id).name)}">+</button></div></label><label>Unit price<input type="number" min="0" max="9999999" step="0.01" value="${item.price}" data-online-price="${item.id}" aria-label="Price of ${escape(products.get(item.id).name)}" required></label><strong data-line-total="${item.id}"></strong></div></div>`).join('') : '<p class="online-cart-empty">Your order is empty.<br><span>Choose items from the menu.</span></p>';
            calculate();
        };
        form.addEventListener('click', (event) => {
            const product = event.target.closest('[data-online-product]');
            if (product && !product.disabled) {
                const id = Number(product.dataset.onlineProduct);
                const existing = rows.find((item) => item.id === id);
                if (existing) existing.qty = Math.min(99999, existing.qty + 1);
                else rows.push({ id, qty: 1, price: products.get(id).price });
                render();
            }
            const remove = event.target.closest('[data-online-remove]');
            if (remove) { rows = rows.filter((item) => item.id !== Number(remove.dataset.onlineRemove)); render(); }
            const adjust = event.target.closest('[data-online-adjust]');
            if (adjust) {
                const item = rows.find((item) => item.id === Number(adjust.dataset.onlineAdjust));
                item.qty = Math.min(99999, Math.round((item.qty + Number(adjust.dataset.delta)) * 1000) / 1000);
                if (item.qty <= 0) rows = rows.filter((row) => row.id !== item.id);
                render();
            }
        });
        form.addEventListener('input', (event) => {
            if (event.target.matches('[data-online-quantity], [data-online-price]')) {
                const key = event.target.hasAttribute('data-online-quantity') ? 'qty' : 'price';
                const id = Number(event.target.dataset.onlineQuantity ?? event.target.dataset.onlinePrice);
                rows.find((item) => item.id === id)[key] = Number(event.target.value);
            }
            calculate();
            error.classList.add('hidden');
        });
        form.addEventListener('change', (event) => {
            if (event.target.name === 'online_order_source_id') {
                const method = event.target.dataset.paymentMethod;
                if ([...paymentMethod.options].some((option) => option.value === method)) paymentMethod.value = method;
            }
            calculate();
        });
        const search = form.querySelector('[data-online-search]');
        const category = form.querySelector('[data-online-category]');
        const filter = () => {
            for (const button of productButtons) button.hidden = !button.dataset.name.toLowerCase().includes(search.value.trim().toLowerCase()) || (category.value && category.value !== button.dataset.category);
            form.querySelector('[data-online-no-products]').classList.toggle('hidden', productButtons.some((button) => !button.hidden));
        };
        search.addEventListener('input', filter);
        category.addEventListener('change', filter);
        form.addEventListener('submit', (event) => {
            if (submitting) { event.preventDefault(); return; }
            if (!rows.length || rows.some((item) => !products.get(item.id).available)) {
                event.preventDefault();
                error.textContent = rows.length ? 'Remove unavailable items before sending this order.' : 'Add at least one menu item before sending.';
                error.classList.remove('hidden');
                return;
            }
            calculate();
            submitting = true;
            const button = form.querySelector('[data-online-submit]');
            button.disabled = true;
            button.textContent = 'Saving & sending…';
        });
        render();
    }
    for (const button of root.querySelectorAll('[data-online-payment-open]')) {
        button.addEventListener('click', () => document.getElementById(button.dataset.onlinePaymentOpen).showModal());
    }
    for (const button of root.querySelectorAll('[data-online-payment-close]')) {
        button.addEventListener('click', () => button.closest('dialog').close());
    }
    const cancelDialog = root.querySelector('[data-online-cancel-dialog]');
    let cancelForm = null;
    const confirmed = new WeakSet();
    for (const stageForm of root.querySelectorAll('[data-online-stage-form]')) {
        stageForm.addEventListener('submit', (event) => {
            if (stageForm.elements.order_status.value === 'cancelled' && !confirmed.has(stageForm)) {
                event.preventDefault(); cancelForm = stageForm; cancelDialog.returnValue = 'back'; cancelDialog.showModal();
            }
        });
    }
    cancelDialog.addEventListener('close', () => {
        if (cancelDialog.returnValue === 'confirm' && cancelForm) { confirmed.add(cancelForm); cancelForm.requestSubmit(); }
        cancelForm = null;
    });
    const orderRows = new Map([...root.querySelectorAll('[data-online-order-id]')].map((row) => [Number(row.dataset.onlineOrderId), row]));
    const sync = root.querySelector('[data-online-sync]');
    let polling = false;
    const refresh = async () => {
        if (polling || document.hidden || !orderRows.size) return;
        polling = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const url = new URL(root.dataset.feedUrl, location.origin);
            [...orderRows.keys()].forEach((id) => url.searchParams.append('ids[]', id));
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
            if (!response.ok) throw new Error('Connection interrupted');
            const data = await response.json();
            for (const order of data.orders) {
                const row = orderRows.get(Number(order.id));
                if (!row) continue;
                row.querySelector('[data-order-stage]').textContent = label(order.order_status);
                row.querySelector('[data-order-stage]').dataset.stage = order.order_status;
                row.querySelector('[data-order-kitchen]').textContent = label(order.kitchen_status);
                row.querySelector('[data-order-payment]').textContent = label(order.payment_status);
                row.querySelector('[data-order-balance]').textContent = money(order.balance_amount);
                const pay = row.querySelector('[data-order-pay]');
                if (pay) pay.hidden = Number(order.balance_amount) <= 0 || order.order_status === 'cancelled';
                const stageForm = row.querySelector('[data-online-stage-form]');
                if (stageForm) {
                    stageForm.hidden = ['cancelled', 'delivered'].includes(order.order_status);
                    const select = stageForm.elements.order_status;
                    if (select.dataset.current !== order.order_status && document.activeElement !== select) {
                        const stages = { new: ['preparing', 'ready', 'cancelled'], preparing: ['ready', 'cancelled'], ready: ['out_for_delivery', 'delivered', 'cancelled'], out_for_delivery: ['delivered', 'cancelled'] };
                        select.replaceChildren(...[order.order_status, ...(stages[order.order_status] ?? [])].map((value) => new Option(label(value), value)));
                        select.dataset.current = order.order_status;
                    }
                }
            }
            sync.textContent = `Live · updated ${new Date().toLocaleTimeString()}. Kitchen and payment status are separate.`;
        } catch { sync.textContent = 'Connection interrupted. Retrying automatically; displayed statuses may be out of date.'; }
        finally { clearTimeout(timeout); polling = false; }
    };
    setInterval(refresh, 3000);
    document.addEventListener('visibilitychange', refresh);
    refresh();
}
