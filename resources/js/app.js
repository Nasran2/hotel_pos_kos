const token = document.querySelector('meta[name="csrf-token"]')?.content;
const sidebar = document.querySelector('[data-sidebar]');
const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');

if (sidebar) {
    const isDrawerOnly = sidebar.dataset.sidebarDrawerOnly === '1';

    const setMobileOpen = (isOpen) => {
        sidebar.classList.toggle('is-mobile-open', isOpen);
        sidebarOverlay?.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('overflow-hidden', isOpen);
    };

    const setCollapsed = (isCollapsed) => {
        sidebar.classList.toggle('is-collapsed', isCollapsed);
        localStorage.setItem('hotel-pos-sidebar-collapsed', isCollapsed ? '1' : '0');
    };

    if (!isDrawerOnly) {
        setCollapsed(localStorage.getItem('hotel-pos-sidebar-collapsed') === '1');
    } else {
        sidebar.classList.remove('is-collapsed');
    }

    document.querySelector('[data-sidebar-open]')?.addEventListener('click', () => setMobileOpen(true));
    document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setMobileOpen(false));
    sidebarOverlay?.addEventListener('click', () => setMobileOpen(false));

    document.querySelector('[data-sidebar-collapse]')?.addEventListener('click', () => {
        setCollapsed(!sidebar.classList.contains('is-collapsed'));
    });

    const groups = [...sidebar.querySelectorAll('[data-sidebar-menu-group]')];

    const openGroup = (targetGroup) => {
        groups.forEach((group) => {
            const isTarget = group === targetGroup;
            group.classList.toggle('is-open', isTarget);
            group.querySelector('[data-sidebar-group-toggle]')?.setAttribute('aria-expanded', isTarget ? 'true' : 'false');
        });
    };

    const activeGroup = groups.find((group) => group.dataset.active === '1');
    if (activeGroup) {
        openGroup(activeGroup);
    }

    groups.forEach((group) => {
        group.querySelector('[data-sidebar-group-toggle]')?.addEventListener('click', () => {
            if (!isDrawerOnly && sidebar.classList.contains('is-collapsed') && window.matchMedia('(min-width: 1024px)').matches) {
                return;
            }

            if (group.classList.contains('is-open')) {
                group.classList.remove('is-open');
                group.querySelector('[data-sidebar-group-toggle]')?.setAttribute('aria-expanded', 'false');
                return;
            }

            openGroup(group);
        });
    });

    sidebar.querySelectorAll('.sidebar-submenu-link').forEach((link) => {
        link.addEventListener('click', () => setMobileOpen(false));
    });
}

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-date-range]').forEach((rangeSelect) => {
    const form = rangeSelect.closest('form');
    const fromInput = form?.querySelector('[data-date-from]');
    const toInput = form?.querySelector('[data-date-to]');

    if (!fromInput || !toInput) {
        return;
    }

    const calculateDateRange = (range) => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const getDateString = (date) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        switch (range) {
            case 'today': {
                return { from: getDateString(today), to: getDateString(today) };
            }
            case 'yesterday': {
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                return { from: getDateString(yesterday), to: getDateString(yesterday) };
            }
            case 'this_week': {
                const start = new Date(today);
                const day = start.getDay();
                const diff = start.getDate() - day;
                start.setDate(diff);
                const end = new Date(start);
                end.setDate(end.getDate() + 6);
                return { from: getDateString(start), to: getDateString(end) };
            }
            case 'last_week': {
                const today = new Date();
                const day = today.getDay();
                const diff = today.getDate() - day - 7;
                const start = new Date(today.setDate(diff));
                start.setHours(0, 0, 0, 0);
                const end = new Date(start);
                end.setDate(end.getDate() + 6);
                return { from: getDateString(start), to: getDateString(end) };
            }
            case 'this_month': {
                const start = new Date(today.getFullYear(), today.getMonth(), 1);
                const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                return { from: getDateString(start), to: getDateString(end) };
            }
            case 'last_month': {
                const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const end = new Date(today.getFullYear(), today.getMonth(), 0);
                return { from: getDateString(start), to: getDateString(end) };
            }
            case 'all_time': {
                return { from: '2000-01-01', to: getDateString(today) };
            }
            default: {
                return { from: getDateString(today), to: getDateString(today) };
            }
        }
    };

    const syncDateFieldState = () => {
        const isCustom = rangeSelect.value === 'custom';

        if (!isCustom) {
            const range = calculateDateRange(rangeSelect.value);
            fromInput.value = range.from;
            toInput.value = range.to;
        }

        fromInput.classList.toggle('opacity-60', !isCustom);
        toInput.classList.toggle('opacity-60', !isCustom);
        fromInput.disabled = !isCustom;
        toInput.disabled = !isCustom;
    };

    syncDateFieldState();
    rangeSelect.addEventListener('change', () => {
        syncDateFieldState();
        if (rangeSelect.value !== 'custom') {
            form.submit();
        }
    });
    [fromInput, toInput].forEach((input) => {
        input.addEventListener('change', () => {
            rangeSelect.value = 'custom';
            syncDateFieldState();
        });
    });
});

document.querySelectorAll('[data-bank-transfer-form]').forEach((form) => {
    const typeSelect = form.querySelector('[data-transfer-type]');
    const targetSelect = form.querySelector('[name="to_bank_account_id"]');
    const targetWrap = form.querySelector('[data-transfer-target-wrap]');
    const sourceBalance = form.querySelector('[data-transfer-source-balance]');
    const cashBalance = Number(form.dataset.cashBalance || 0);
    const defaultBankBalance = Number(form.dataset.defaultBankBalance || 0);
    const formatBalance = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const updateSourceBalance = () => {
        const isBankToBank = typeSelect?.value === 'bank_to_bank';
        const isBankToCash = typeSelect?.value === 'bank_to_cash';

        if (sourceBalance) {
            sourceBalance.textContent = `Available: ${formatBalance(isBankToBank || isBankToCash ? defaultBankBalance : cashBalance)}`;
        }

        targetWrap?.classList.toggle('hidden', isBankToCash);
        if (targetSelect) {
            targetSelect.disabled = isBankToCash;
            targetSelect.required = !isBankToCash;
        }

        targetSelect?.querySelectorAll('option').forEach((option) => {
            option.disabled = !isBankToCash && isBankToBank && option.dataset.default === '1';
        });

        if (!isBankToCash && targetSelect?.selectedOptions[0]?.disabled) {
            const nextOption = [...targetSelect.options].find((option) => !option.disabled);
            if (nextOption) {
                targetSelect.value = nextOption.value;
            }
        }
    };

    typeSelect?.addEventListener('change', updateSourceBalance);
    updateSourceBalance();
});

document.querySelectorAll('[data-bank-account-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: new FormData(form),
            });
            const data = await response.json();

            if (!response.ok) {
                alert(data.message || 'Error saving bank account');
                return;
            }

            const account = data.account;
            document.querySelectorAll('[name="to_bank_account_id"]').forEach((select) => {
                const option = document.createElement('option');
                option.value = String(account.id);
                option.dataset.default = account.is_default ? '1' : '0';
                option.dataset.balance = String(account.balance || 0);
                option.textContent = `${account.name}${account.account_no ? ` - ${account.account_no}` : ''}${account.is_default ? ' (Default)' : ''}`;
                select.appendChild(option);
                select.value = option.value;
                select.dispatchEvent(new Event('change'));
            });

            form.reset();
            alert(data.message || 'Bank account saved.');
        } catch (error) {
            console.error(error);
            alert('Error saving bank account. Please try again.');
        }
    });
});

document.querySelectorAll('.permission-category').forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
        checkbox.closest('.rounded-lg').querySelectorAll('.permission-item').forEach((item) => {
            item.checked = checkbox.checked;
        });
    });
});

document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => document.getElementById(button.dataset.modalOpen)?.classList.add('open'));
});

document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('.modal')?.classList.remove('open'));
});

const pos = document.querySelector('#pos-root');

if (pos) {
    const currency = pos.dataset.currency || 'Rs.';
    const serviceChargeEnabled = pos.dataset.serviceChargeEnabled === '1';
    const serviceChargeRate = Number(pos.dataset.serviceChargeRate || 0);
    const currentPosPath = window.location.pathname.replace(/\/$/, '');
    const registerCloseSummaryUrl = `${currentPosPath}/register/close-summary`;
    const expenseDeleteBaseUrl = `${currentPosPath}/expense`;
    const cart = [];
    let selectedTable = null;
    let selectedWaiter = null;
    let currentHoldId = null;
    let currentHoldStatus = null;
    let currentToken = null;
    let paymentMethod = 'cash';
    const customerInput = document.querySelector('[data-customer-search-input]');
    const customerIdInput = document.querySelector('[data-customer-id]');
    const waiterInput = document.querySelector('[data-waiter-search-input]');
    const waiterIdInput = document.querySelector('[data-waiter-id]');
    const customerSuggestions = document.querySelector('[data-customer-suggestions]');
    const waiterSuggestions = document.querySelector('[data-waiter-suggestions]');
    const customerOptionsWrap = document.querySelector('[data-customer-options]');
    const waiterOptionsWrap = document.querySelector('[data-waiter-options]');
    let customerOptions = [...document.querySelectorAll('[data-customer-options] [data-id]')];
    let waiterOptions = [...document.querySelectorAll('[data-waiter-options] [data-id]')];
    const posToast = document.querySelector('[data-pos-toast]');
    const posToastTitle = document.querySelector('[data-pos-toast-title]');
    const posToastMessage = document.querySelector('[data-pos-toast-message]');
    let posToastTimer = null;

    const money = (value) => `${currency} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const formatToken = (value) => String(Number(value || 0)).padStart(2, '0');
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));

    const hidePosToast = () => {
        window.clearTimeout(posToastTimer);
        posToast?.classList.remove('open');
    };

    const showPosToast = (message, title = 'Action needed', type = 'error') => {
        if (!posToast || !posToastTitle || !posToastMessage) {
            console.error(message);
            return;
        }

        const toastType = title === 'Success' ? 'success' : type;
        posToastTitle.textContent = title;
        posToastMessage.textContent = message;
        posToast.classList.toggle('is-success', toastType === 'success');
        posToast.classList.add('open');
        window.clearTimeout(posToastTimer);
        posToastTimer = window.setTimeout(hidePosToast, 4000);
    };

    document.querySelector('[data-pos-toast-close]')?.addEventListener('click', hidePosToast);

    const updateOrderHeader = () => {
        const tableLabel = document.querySelector('[data-selected-table]');
        const orderMode = document.querySelector('[data-order-mode]');
        const currentTokenWrap = document.querySelector('[data-current-token-wrap]');
        const currentTokenElement = document.querySelector('[data-current-token]');

        currentTokenWrap?.classList.toggle('hidden', !currentToken);
        if (currentTokenElement) {
            currentTokenElement.textContent = currentToken ? formatToken(currentToken) : '';
        }

        if (selectedTable) {
            if (tableLabel) {
                tableLabel.textContent = selectedTable.name;
            }
            if (orderMode) {
                orderMode.textContent = 'Table order';
            }
            return;
        }

        if (tableLabel) {
            tableLabel.textContent = 'Takeaway Order';
        }
        if (orderMode) {
            orderMode.textContent = currentHoldId ? 'Held takeaway' : 'No table';
        }
    };

    const syncCancelHoldAction = () => {
        const cancelHoldWrap = document.querySelector('[data-cancel-hold-wrap]');
        const canCancelHold = Boolean(currentHoldId && ['hold', 'payment_pending'].includes(currentHoldStatus));
        cancelHoldWrap?.classList.toggle('hidden', !canCancelHold);
        
        document.querySelector('[data-cancel-confirm-popup]')?.classList.add('hidden');

        const actionWrap = document.querySelector('[data-payment-action-wrap]');
        actionWrap?.classList.toggle('sm:grid-cols-2', canCancelHold);
    };

    const syncPaymentModalMode = () => {
        const isTakeaway = !selectedTable;
        document.querySelector('[data-payment-print-action]')?.classList.toggle('hidden', isTakeaway);

        const paymentHelp = document.querySelector('[data-payment-help]');
        if (paymentHelp) {
            paymentHelp.textContent = isTakeaway
                ? 'Take payment now. The paid bill prints after payment.'
                : 'Print the bill first, then take payment when the waiter returns.';
        }
    };

    const activateTakeawayMode = () => {
        selectedTable = null;
        currentHoldId = null;
        currentHoldStatus = null;
        currentToken = null;
        document.querySelectorAll('.table-card').forEach((card) => card.classList.remove('active'));
        document.querySelectorAll('[data-takeaway-hold]').forEach((card) => card.classList.remove('active'));
        document.querySelector('[data-takeaway-start]')?.classList.add('active');
        updateOrderHeader();
        syncCancelHoldAction();
    };

    const totals = () => {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity) - Number(item.discount_amount || 0), 0);

        const discountInput = document.querySelector('[data-bill-discount]');
        const discountTypeInput = document.querySelector('[data-bill-discount-type]');

        const discountValue = Number(discountInput?.value || 0);
        const discountType = discountTypeInput?.value || 'fixed';

        let discount = discountType === 'percent'
            ? subtotal * discountValue / 100
            : discountValue;

        discount = Math.min(discount, subtotal);

        const service = serviceChargeEnabled ? subtotal * (serviceChargeRate / 100) : 0;

        return {
            subtotal,
            discount,
            service,
            total: Math.max(0, subtotal + service - discount)
        };
    };

    const render = () => {
        updateOrderHeader();
        const body = document.querySelector('[data-cart-items]');
        body.innerHTML = cart.map((item, index) => `
            <tr>
                <td class="py-3 font-semibold">
                    <button data-line-discount="${index}" class="text-left hover:text-blue-700">${escapeHtml(normalizedItemName(item))}</button>
                    ${Number(item.discount_amount || 0) > 0 ? `<div class="text-xs font-bold text-amber-600">Line discount: -${money(item.discount_amount)}</div>` : ''}
                </td>
                <td><div class="inline-flex items-center rounded-lg border border-slate-200"><button class="px-2" data-qty="${index}" data-step="-1">-</button><span class="px-2">${item.quantity}</span><button class="px-2" data-qty="${index}" data-step="1">+</button></div></td>
                <td>${money(item.price)}</td>
                <td>${money((item.price * item.quantity) - Number(item.discount_amount || 0))}</td>
                <td><button data-remove="${index}" class="text-red-600">Delete</button></td>
            </tr>
        `).join('');
        const summary = totals();
        document.querySelector('[data-subtotal]').textContent = money(summary.subtotal);
        document.querySelector('[data-service]').textContent = money(summary.service);
        document.querySelector('[data-discount]').textContent = `- ${money(summary.discount)}`;
        document.querySelector('[data-total]').textContent = money(summary.total);
        updatePaymentBalance();

        syncCancelHoldAction();
    };

    const selectedCustomerOption = () => optionById(customerOptions, customerIdInput?.value || '');
    const selectedCustomerCanTakeDue = () => {
        const option = selectedCustomerOption();

        return Boolean(option && option.dataset.walkIn !== '1');
    };

    const paymentBalance = () => {
        const total = totals().total;
        const received = paymentMethod === 'due' ? 0 : Number(document.querySelector('[data-received]')?.value || 0);

        return {
            due: Math.max(0, total - received),
            change: Math.max(0, received - total),
        };
    };

    const updatePaymentBalance = () => {
        const balance = paymentBalance();
        const isDue = paymentMethod === 'due' || balance.due > 0;
        const label = document.querySelector('[data-balance-label]');
        const amount = document.querySelector('[data-change]');

        if (label) {
            label.textContent = isDue ? 'Due:' : 'Change:';
        }
        if (amount) {
            amount.textContent = money(isDue ? balance.due : balance.change);
        }
    };

    const renderPrintBill = (options = {}) => {
        const summary = totals();
        const receivedAmount = Number(options.receivedAmount ?? document.querySelector('[data-received]')?.value ?? summary.total);
        const paidAmount = Number(options.paidAmount ?? (options.paymentMethod === 'due' ? 0 : summary.total));
        const changeAmount = Number(options.changeAmount ?? Math.max(0, receivedAmount - paidAmount));
        const dueAmount = Number(options.dueAmount ?? Math.max(0, summary.total - paidAmount));
        const setPrintText = (selector, value) => {
            const element = document.querySelector(selector);
            if (element) {
                element.textContent = value;
            }
        };
        updateOrderHeader();
        setPrintText('[data-print-bill-title]', options.title || 'Pre-payment bill');
        setPrintText('[data-print-token]', options.formattedToken || (currentToken ? formatToken(currentToken) : '-'));
        setPrintText('[data-print-table]', selectedTable?.name || 'Takeaway');
        setPrintText('[data-print-invoice]', options.invoice || '-');
        setPrintText('[data-print-payment]', options.paymentMethod ? options.paymentMethod.toUpperCase() : '-');
        setPrintText('[data-print-waiter]', waiterInput?.value || 'No waiter');
        setPrintText('[data-print-customer]', customerInput?.value || 'Walk-in Customer');
        setPrintText('[data-print-date]', options.orderDate || '-');
        document.querySelector('[data-print-items]').innerHTML = cart.map((item) => `
            <tr>
                <td>
                    ${escapeHtml(normalizedItemName(item))}
                    <br><span style="font-size: 10px;">${money(item.price)}</span>
                    ${Number(item.discount_amount || 0) > 0 ? `<br><span style="font-size: 10px; color: #b45309;">Discount: -${money(item.discount_amount)}</span>` : ''}
                </td>
                <td class="text-right">${Number(item.quantity).toLocaleString()}</td>
                <td class="text-right">${money((item.price * item.quantity) - Number(item.discount_amount || 0))}</td>
            </tr>
        `).join('');
        setPrintText('[data-print-subtotal]', money(summary.subtotal));
        setPrintText('[data-print-service]', money(summary.service));
        setPrintText('[data-print-discount]', `- ${money(summary.discount)}`);
        setPrintText('[data-print-total]', money(summary.total));

        const paymentSummary = document.querySelector('[data-print-payment-summary]');
        if (paymentSummary) {
            paymentSummary.hidden = !options.paymentMethod;
        }
        setPrintText('[data-print-paid]', money(paidAmount));
        setPrintText('[data-print-received]', money(receivedAmount));
        setPrintText('[data-print-balance-label]', dueAmount > 0 ? 'Due' : 'Change');
        setPrintText('[data-print-change]', money(dueAmount > 0 ? dueAmount : changeAmount));

        if (options.footer) {
            setPrintText('[data-print-footer]', options.footer);
        }
    };

    const payload = () => {
        const summary = totals();

        return {
            table_id: selectedTable?.id,
            hold_id: currentHoldId,
            customer_id: customerIdInput?.value || null,
            waiter_id: waiterIdInput?.value || null,
            note: document.querySelector('[data-note]')?.value || null,
            discount_amount: summary.discount,
            items: cart.map(normalizedCartItem),
        };
    };

    const optionByValue = (options, value) => options.find((option) => option.value.trim().toLowerCase() === value.trim().toLowerCase());
    const optionById = (options, id) => options.find((option) => String(option.dataset.id) === String(id));

    const optionLabel = (option) => option.value || option.textContent || '';
    const optionByQuery = (options, value) => options.find((option) => optionLabel(option).trim().toLowerCase() === value.trim().toLowerCase());

    const refreshOptions = () => {
        customerOptions = [...document.querySelectorAll('[data-customer-options] [data-id]')];
        waiterOptions = [...document.querySelectorAll('[data-waiter-options] [data-id]')];
    };

    const hideSuggestions = (container) => {
        if (!container) return;
        container.classList.add('hidden');
        container.innerHTML = '';
    };

    const renderSuggestions = (container, options, query, onPick) => {
        if (!container) return;
        const normalized = (query || '').trim().toLowerCase();
        if (normalized.length === 0) {
            hideSuggestions(container);
            return;
        }

        const matches = options.filter((option) => optionLabel(option).toLowerCase().includes(normalized)).slice(0, 8);
        if (matches.length === 0) {
            hideSuggestions(container);
            return;
        }

        container.innerHTML = matches.map((option) => `<button type="button" class="pos-suggestion-item" data-id="${option.dataset.id}">${optionLabel(option)}</button>`).join('');
        container.classList.remove('hidden');

        container.querySelectorAll('.pos-suggestion-item').forEach((button) => {
            button.addEventListener('pointerdown', (event) => {
                event.preventDefault();
                onPick(button.dataset.id);
            });
        });
    };

    const updateWaiterSelection = () => {
        const option = optionByQuery(waiterOptions, waiterInput?.value || '');

        if (!option) {
            if (waiterIdInput) {
                waiterIdInput.value = '';
            }
            selectedWaiter = null;
            document.querySelector('[data-selected-waiter]').textContent = 'No waiter';
            return;
        }

        if (waiterIdInput) {
            waiterIdInput.value = option.dataset.id || '';
        }
        selectedWaiter = { id: option.dataset.id, name: optionLabel(option) };
        document.querySelector('[data-selected-waiter]').textContent = optionLabel(option);
    };

    const updateCustomerSelection = () => {
        const option = optionByQuery(customerOptions, customerInput?.value || '');
        if (customerIdInput) {
            customerIdInput.value = option?.dataset.id || '';
        }
    };

    const defaultCustomer = customerOptions.find((option) => option.dataset.walkIn === '1') || customerOptions[0];
    const defaultCustomerId = defaultCustomer?.dataset.id || '';
    const defaultCustomerName = defaultCustomer ? optionLabel(defaultCustomer) : '';
    if (defaultCustomer && customerInput && customerIdInput) {
        customerInput.value = defaultCustomerName;
        customerIdInput.value = defaultCustomerId;
    }

    const postJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(json.message || 'Action failed.');
        return json;
    };

    const deleteJson = async (url) => {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(json.message || 'Action failed.');
        return json;
    };

    const updateNextToken = (nextToken) => {
        const nextTokenElement = document.querySelector('[data-next-token]');
        if (nextTokenElement && nextToken?.display) {
            nextTokenElement.textContent = nextToken.display;
        }
    };

    const refreshNextToken = async () => {
        if (!pos.dataset.nextTokenUrl) {
            return;
        }

        try {
            const response = await fetch(pos.dataset.nextTokenUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (response.ok) {
                updateNextToken(await response.json());
            }
        } catch (error) {
            console.debug('Unable to refresh the advisory next token display.', error);
        }
    };

    window.setInterval(refreshNextToken, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshNextToken();
        }
    });

    // Line item modal elements
    const lineModal = document.getElementById('line-item-modal');
    const lineNameInput = lineModal?.querySelector('[data-line-name]');
    const linePriceInput = lineModal?.querySelector('[data-line-price]');
    const lineDiscountType = lineModal?.querySelector('[data-line-discount-type]');
    const lineDiscountValue = lineModal?.querySelector('[data-line-discount-value]');
    let currentLineIndex = null;

    const productFromCard = (card) => JSON.parse(card.dataset.product || '{}');

    const productForCartItem = (item) => {
        const card = [...document.querySelectorAll('[data-add-product-card]')]
            .find((productCard) => Number(productFromCard(productCard).id) === Number(item.id));

        return card ? productFromCard(card) : item;
    };

    const normalizedItemName = (item) => {
        const fallbackProduct = productForCartItem(item);

        return (item?.name || fallbackProduct?.name || 'Item').trim();
    };

    const normalizedCartItem = (item) => {
        const fallbackProduct = productForCartItem(item);

        return {
            ...item,
            name: normalizedItemName(item),
            quantity: Number(item.quantity || 0),
            price: Number(item.price ?? fallbackProduct?.selling_price ?? 0),
            discount_amount: Number(item.discount_amount || 0),
            discount_type: item.discount_type || null,
            maintain_stock: item.maintain_stock ?? fallbackProduct?.maintain_stock,
            stock_quantity: Number(item.stock_quantity ?? fallbackProduct?.stock_quantity ?? 0),
        };
    };

    const canSetCartQuantity = (product, quantity) => {
        if (!product.maintain_stock) {
            return true;
        }

        const availableStock = Number(product.stock_quantity || 0);

        if (availableStock <= 0) {
            showPosToast('This item is out of stock.');
            return false;
        }

        if (quantity > availableStock) {
            showPosToast(`Only ${availableStock.toLocaleString()} in stock.`);
            return false;
        }

        return true;
    };

    const addProductToCart = (card) => {
        const product = productFromCard(card);
        const existing = cart.find((item) => item.id === product.id);
        const nextQuantity = (existing?.quantity || 0) + 1;

        if (!canSetCartQuantity(product, nextQuantity)) {
            return;
        }

        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                id: product.id,
                name: product.name,
                quantity: 1,
                price: Number(product.selling_price),
                discount_amount: 0,
                maintain_stock: product.maintain_stock,
                stock_quantity: Number(product.stock_quantity || 0),
            });
        }

        render();
    };

    const applyHeldOrder = (data) => {
        currentHoldId = data.hold?.id || null;
        currentHoldStatus = data.hold?.status || null;
        currentToken = data.hold?.token_number || null;
        cart.splice(0, cart.length, ...data.items.map((item) => normalizedCartItem({
            id: item.product_id,
            name: item.product_name,
            quantity: Number(item.quantity),
            price: Number(item.unit_price),
            discount_amount: Number(item.discount_amount || 0),
            discount_type: item.discount_type || null,
        })));

        if (data.hold?.customer_id) {
            const customerOption = optionById(customerOptions, data.hold.customer_id);
            if (customerOption && customerInput && customerIdInput) {
                customerInput.value = optionLabel(customerOption);
                customerIdInput.value = customerOption.dataset.id || '';
            }
        }

        const billDiscountInput = document.querySelector('[data-bill-discount]');
        if (billDiscountInput) {
            billDiscountInput.value = Number(data.hold?.discount_amount || 0);
        }

        const noteInput = document.querySelector('[data-note]');
        if (noteInput) {
            noteInput.value = data.hold?.note || '';
        }

        selectedWaiter = null;
        document.querySelector('[data-selected-waiter]').textContent = 'No waiter';
        if (waiterInput) {
            waiterInput.value = '';
        }
        if (waiterIdInput) {
            waiterIdInput.value = '';
        }

        if (data.hold?.waiter_id) {
            const waiterOption = optionById(waiterOptions, data.hold.waiter_id);
            if (waiterOption && waiterInput && waiterIdInput) {
                waiterInput.value = optionLabel(waiterOption);
                waiterIdInput.value = waiterOption.dataset.id || '';
                selectedWaiter = {
                    id: waiterOption.dataset.id,
                    name: optionLabel(waiterOption),
                };
                document.querySelector('[data-selected-waiter]').textContent = selectedWaiter.name;
            }
        }

        updateOrderHeader();
        syncCancelHoldAction();
        render();
    };

    document.querySelectorAll('[data-add-product-card]').forEach((card) => {
        card.addEventListener('click', () => addProductToCart(card));
        card.addEventListener('keydown', (event) => {
            if (!['Enter', ' '].includes(event.key)) {
                return;
            }

            event.preventDefault();
            addProductToCart(card);
        });
    });

    document.addEventListener('click', async (event) => {
        const remove = event.target.closest('[data-remove]');
        const qty = event.target.closest('[data-qty]');
        const lineDiscount = event.target.closest('[data-line-discount]');

        if (remove) {
            cart.splice(Number(remove.dataset.remove), 1);
            render();
        }

        if (qty) {
            const item = cart[Number(qty.dataset.qty)];
            const step = Number(qty.dataset.step);
            const nextQuantity = Math.max(1, item.quantity + step);

            if (step > 0 && !canSetCartQuantity(productForCartItem(item), nextQuantity)) {
                return;
            }

            item.quantity = nextQuantity;
            render();
        }

        if (lineDiscount) {
            // open inline modal to edit unit price and discount
            currentLineIndex = Number(lineDiscount.dataset.lineDiscount);
            const item = normalizedCartItem(cart[currentLineIndex]);
            if (!item) return;
            cart[currentLineIndex] = item;

            if (lineNameInput) lineNameInput.value = normalizedItemName(item);
            if (linePriceInput) linePriceInput.value = Number(item.price).toFixed(2);

            // infer discount type/value (prefer percent if it looks like a percent)
            const totalLine = Number(item.price) * Number(item.quantity) || 0;
            const inferredPercent = totalLine > 0 ? (Number(item.discount_amount || 0) / totalLine) * 100 : 0;
            if (lineDiscountType && lineDiscountValue) {
                if (inferredPercent > 0 && inferredPercent <= 100) {
                    lineDiscountType.value = 'percent';
                    lineDiscountValue.value = Number(inferredPercent.toFixed(2));
                } else {
                    lineDiscountType.value = 'fixed';
                    lineDiscountValue.value = Number(item.discount_amount || 0).toFixed(2);
                }
            }

            lineModal?.classList.add('open');
        }
    });

    // Save / Cancel handlers for line modal
    document.querySelector('[data-line-save]')?.addEventListener('click', () => {
        if (currentLineIndex === null) return;
        const item = cart[currentLineIndex];
        if (!item) return;

        const newName = lineNameInput?.value?.trim() || item.name;
        const newPrice = Number(linePriceInput?.value || 0);
        const type = lineDiscountType?.value || 'fixed';
        const val = Number(lineDiscountValue?.value || 0);

        item.name = newName;
        item.price = newPrice;
        item.discount_type = type === 'percent' ? 'percentage' : 'fixed';

        let discountAmount = 0;
        if (type === 'percent') {
            discountAmount = (item.price * item.quantity) * (val / 100);
        } else {
            discountAmount = val;
        }

        const maxDiscount = item.price * item.quantity;
        discountAmount = Math.min(discountAmount, maxDiscount);
        item.discount_amount = Number(discountAmount.toFixed(2));

        render();
        lineModal?.classList.remove('open');
        currentLineIndex = null;
    });

    document.querySelector('[data-line-cancel]')?.addEventListener('click', () => {
        currentLineIndex = null;
        lineModal?.classList.remove('open');
    });

    document.querySelectorAll('.table-card').forEach((button) => {
        button.addEventListener('click', async () => {
            selectedTable = { id: button.dataset.tableId, name: button.dataset.tableName };
            document.querySelectorAll('.table-card').forEach((card) => card.classList.remove('active'));
            document.querySelectorAll('[data-takeaway-hold]').forEach((card) => card.classList.remove('active'));
            document.querySelector('[data-takeaway-start]')?.classList.remove('active');
            button.classList.add('active');
            currentHoldId = null;
            currentHoldStatus = null;
            currentToken = null;
            updateOrderHeader();
            syncCancelHoldAction();

            if (button.dataset.held === '1') {
                const data = await fetch(button.dataset.resumeUrl, { headers: { Accept: 'application/json' } }).then((response) => response.json());
                applyHeldOrder(data);
            }
        });
    });

    document.querySelectorAll('[data-takeaway-hold]').forEach((button) => {
        button.addEventListener('click', async () => {
            selectedTable = null;
            document.querySelectorAll('.table-card').forEach((card) => card.classList.remove('active'));
            document.querySelectorAll('[data-takeaway-hold]').forEach((card) => card.classList.remove('active'));
            document.querySelector('[data-takeaway-start]')?.classList.remove('active');
            button.classList.add('active');

            const data = await fetch(button.dataset.resumeUrl, { headers: { Accept: 'application/json' } }).then((response) => response.json());
            applyHeldOrder(data);
        });
    });

    document.querySelector('[data-takeaway-start]')?.addEventListener('click', activateTakeawayMode);

    waiterInput?.addEventListener('input', () => {
        renderSuggestions(waiterSuggestions, waiterOptions, waiterInput.value, (id) => {
            const option = optionById(waiterOptions, id);
            if (!option || !waiterInput) return;
            waiterInput.value = optionLabel(option);
            updateWaiterSelection();
            hideSuggestions(waiterSuggestions);
        });
    });
    waiterInput?.addEventListener('focus', () => renderSuggestions(waiterSuggestions, waiterOptions, waiterInput.value, (id) => {
        const option = optionById(waiterOptions, id);
        if (!option || !waiterInput) return;
        waiterInput.value = optionLabel(option);
        updateWaiterSelection();
        hideSuggestions(waiterSuggestions);
    }));
    waiterInput?.addEventListener('change', () => {
        updateWaiterSelection();
        hideSuggestions(waiterSuggestions);
    });

    customerInput?.addEventListener('input', () => {
        renderSuggestions(customerSuggestions, customerOptions, customerInput.value, (id) => {
            const option = optionById(customerOptions, id);
            if (!option || !customerInput) return;
            customerInput.value = optionLabel(option);
            updateCustomerSelection();
            hideSuggestions(customerSuggestions);
        });
    });
    customerInput?.addEventListener('focus', () => renderSuggestions(customerSuggestions, customerOptions, customerInput.value, (id) => {
        const option = optionById(customerOptions, id);
        if (!option || !customerInput) return;
        customerInput.value = optionLabel(option);
        updateCustomerSelection();
        hideSuggestions(customerSuggestions);
    }));
    customerInput?.addEventListener('click', () => {
        if (customerInput.value === defaultCustomerName || customerIdInput?.value === defaultCustomerId) {
            customerInput.value = '';
            if (customerIdInput) {
                customerIdInput.value = '';
            }
            renderSuggestions(customerSuggestions, customerOptions, '', () => { });
        }
    });
    customerInput?.addEventListener('change', () => {
        updateCustomerSelection();
        hideSuggestions(customerSuggestions);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-searchable-wrap="waiter"]')) {
            hideSuggestions(waiterSuggestions);
        }
        if (!event.target.closest('[data-searchable-wrap="customer"]')) {
            hideSuggestions(customerSuggestions);
        }
    });

    document.querySelector('[data-quick-create-waiter]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const payloadData = {
            name: form.querySelector('[name="name"]').value,
            phone: form.querySelector('[name="phone"]').value,
        };

        try {
            const response = await postJson(pos.dataset.waiterCreateUrl, payloadData);
            const option = document.createElement('span');
            option.dataset.id = String(response.waiter.id);
            option.textContent = response.waiter.name;
            waiterOptionsWrap?.append(option);
            refreshOptions();

            if (waiterInput && waiterIdInput) {
                waiterInput.value = response.waiter.name;
                waiterIdInput.value = String(response.waiter.id);
                selectedWaiter = { id: String(response.waiter.id), name: response.waiter.name };
                document.querySelector('[data-selected-waiter]').textContent = response.waiter.name;
            }

            form.reset();
            form.closest('.modal')?.classList.remove('open');
        } catch (error) {
            showPosToast(error.message || 'Unable to add waiter.');
        }
    });

    document.querySelector('[data-quick-create-customer]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const payloadData = {
            name: form.querySelector('[name="name"]').value,
            phone: form.querySelector('[name="phone"]').value,
        };

        try {
            const response = await postJson(pos.dataset.customerCreateUrl, payloadData);
            const option = document.createElement('span');
            option.dataset.id = String(response.customer.id);
            option.dataset.walkIn = '0';
            option.textContent = response.customer.name;
            customerOptionsWrap?.append(option);
            refreshOptions();

            if (customerInput && customerIdInput) {
                customerInput.value = response.customer.name;
                customerIdInput.value = String(response.customer.id);
            }

            form.reset();
            form.closest('.modal')?.classList.remove('open');
        } catch (error) {
            showPosToast(error.message || 'Unable to add customer.');
        }
    });

    document.querySelector('[data-hold]')?.addEventListener('click', async () => {
        if (cart.length === 0) {
            showPosToast('Add items first.');
            return;
        }

        try {
            const response = await postJson(pos.dataset.holdUrl, payload());
            updateNextToken(response.next_token);
            window.location.reload();
        } catch (error) {
            showPosToast(error.message || 'Unable to hold this order right now.');
        }
    });

    document.querySelector('[data-print]')?.addEventListener('click', async () => {
        if (cart.length === 0) {
            showPosToast('Add items first.');
            return;
        }

        try {
            const response = await postJson(pos.dataset.printUrl, payload());
            if (response.hold_id) {
                currentHoldId = response.hold_id;
                currentHoldStatus = 'payment_pending';
            }
            currentToken = response.token_number || currentToken;
            updateNextToken(response.next_token);
            renderPrintBill({
                invoice: response.invoice,
                paymentMethod: null,
                formattedToken: response.formatted_token,
                orderDate: response.order_date,
            });
            window.addEventListener('afterprint', () => window.location.reload(), { once: true });
            window.print();
        } catch (error) {
            showPosToast(error.message || 'Unable to print this bill right now.');
        }
    });

    document.querySelector('[data-submit-transfer]')?.addEventListener('click', async () => {
        if (!selectedTable) {
            showPosToast('Select a table first.');
            return;
        }

        try {
            await postJson(pos.dataset.transferUrl, { from_table_id: selectedTable.id, to_table_id: document.querySelector('[data-transfer-table]').value });
            window.location.reload();
        } catch (error) {
            showPosToast(error.message || 'Unable to transfer this table right now.');
        }
    });

    const setPaymentMethod = (method) => {
        const button = document.querySelector(`[data-payment-method="${method}"]`) || document.querySelector('[data-payment-method]');
        if (!button) return;

        paymentMethod = button.dataset.paymentMethod;
        document.querySelectorAll('[data-payment-method]').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        document.querySelector('[data-qr-payment-action]')?.classList.toggle('hidden', paymentMethod !== 'qr');
        updatePaymentBalance();
    };

    document.querySelector('[data-modal-open="payment-modal"]')?.addEventListener('click', () => {
        syncPaymentModalMode();
        setPaymentMethod('cash');
        const receivedInput = document.querySelector('[data-received]');
        if (receivedInput) {
            receivedInput.value = totals().total.toFixed(2);
        }
        render();
    });

    document.querySelectorAll('[data-payment-method]').forEach((button) => {
        button.addEventListener('click', () => setPaymentMethod(button.dataset.paymentMethod));
    });

    document.querySelector('[data-submit-payment]')?.addEventListener('click', async () => {
        if (cart.length === 0) {
            showPosToast('Add items first.');
            return;
        }

        try {
            const receivedAmount = Number(document.querySelector('[data-received]')?.value || totals().total);
            const dueAmount = paymentMethod === 'due' ? totals().total : Math.max(0, totals().total - receivedAmount);

            if (dueAmount > 0 && !selectedCustomerCanTakeDue()) {
                showPosToast('Select a registered customer before leaving a due balance.');
                return;
            }

            const response = await postJson(pos.dataset.payUrl, { ...payload(), payment_method: paymentMethod, received_amount: receivedAmount });
            currentToken = response.token_number || currentToken;
            updateNextToken(response.next_token);
            renderPrintBill({
                title: response.status === 'due' ? 'Due bill' : 'Paid bill',
                invoice: response.invoice,
                paymentMethod,
                formattedToken: response.formatted_token,
                orderDate: response.order_date,
                paidAmount: response.paid_amount,
                receivedAmount: response.received_amount ?? receivedAmount,
                changeAmount: response.change_amount,
                dueAmount: response.due_amount,
                footer: response.status === 'due' ? 'Thank you. Balance marked as due.' : 'Thank you. Payment received.',
            });
            document.querySelector('#payment-modal')?.classList.remove('open');
            window.addEventListener('afterprint', () => window.location.reload(), { once: true });
            window.print();
        } catch (error) {
            showPosToast(error.message || 'Unable to complete payment right now.');
        }
    });

    const cancelHoldPopup = document.querySelector('[data-cancel-confirm-popup]');
    document.querySelector('[data-cancel-hold]')?.addEventListener('click', () => {
        if (!currentHoldId || !['hold', 'payment_pending'].includes(currentHoldStatus)) {
            showPosToast('Select a hold table or waiting payment table first.');
            return;
        }
        cancelHoldPopup?.classList.remove('hidden');
    });

    document.querySelector('[data-cancel-no]')?.addEventListener('click', () => {
        cancelHoldPopup?.classList.add('hidden');
    });

    document.querySelector('[data-cancel-yes]')?.addEventListener('click', async () => {
        cancelHoldPopup?.classList.add('hidden');
        try {
            await deleteJson(`${pos.dataset.cancelHoldUrl}/${currentHoldId}`);
            window.location.reload();
        } catch (error) {
            showPosToast(error.message || 'Unable to cancel this held order right now.');
        }
    });

    document.querySelector('[data-clear]')?.addEventListener('click', () => {
        cart.splice(0, cart.length);
        if (!selectedTable) {
            currentHoldId = null;
            currentHoldStatus = null;
            document.querySelectorAll('[data-takeaway-hold]').forEach((card) => card.classList.remove('active'));
            document.querySelector('[data-takeaway-start]')?.classList.add('active');
        }
        render();
    });

    const focusMenuSearch = () => {
        const menuSearch = document.querySelector('[data-menu-search]');
        if (!menuSearch) {
            return;
        }

        menuSearch.focus();
        menuSearch.select();
    };

    const openPaymentModal = () => {
        syncPaymentModalMode();
        setPaymentMethod('cash');

        const receivedInput = document.querySelector('[data-received]');
        if (receivedInput) {
            receivedInput.value = totals().total.toFixed(2);
        }

        document.querySelector('[data-modal-open="payment-modal"]')?.click();
        document.querySelector('#payment-modal')?.classList.add('open');
        render();
    };

    const closeOpenModal = () => {
        document.querySelectorAll('.modal.open').forEach((modal) => modal.classList.remove('open'));
    };

    document.addEventListener('keydown', (event) => {
        const activeElement = document.activeElement;
        const isTypingField = activeElement && (
            activeElement.isContentEditable
            || ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeElement.tagName)
        );
        const isModifierPressed = event.metaKey || event.ctrlKey;
        const isEnterShortcut = isModifierPressed && event.key === 'Enter';
        const isHoldShortcut = isModifierPressed && event.key.toLowerCase() === 'h';
        const isSearchShortcut = !isModifierPressed && !event.altKey && event.key === '/';

        if (event.key === 'Escape') {
            if (document.querySelector('.modal.open')) {
                closeOpenModal();
                event.preventDefault();
                return;
            }

            hidePosToast();
            return;
        }

        if (isTypingField && !isEnterShortcut) {
            return;
        }

        if (isSearchShortcut) {
            event.preventDefault();
            focusMenuSearch();
            return;
        }

        if (isHoldShortcut) {
            event.preventDefault();
            document.querySelector('[data-hold]')?.click();
            return;
        }

        if (isEnterShortcut) {
            const paymentModal = document.querySelector('#payment-modal');

            event.preventDefault();
            if (paymentModal?.classList.contains('open')) {
                document.querySelector('[data-submit-payment]')?.click();
                return;
            }

            openPaymentModal();
        }
    });

    document.querySelector('[data-bill-discount]')?.addEventListener('input', render);
    document.querySelector('[data-bill-discount-type]')?.addEventListener('change', render);
    document.querySelector('[data-received]')?.addEventListener('input', render);

    document.querySelector('[data-menu-search]')?.addEventListener('input', (event) => {
        const query = event.target.value.toLowerCase();
        document.querySelectorAll('.menu-card').forEach((card) => card.classList.toggle('hidden', !card.dataset.name.includes(query)));
    });

    document.querySelector('[data-pos-search]')?.addEventListener('input', (event) => {
        const query = event.target.value.toLowerCase();
        document.querySelectorAll('.table-card').forEach((card) => card.classList.toggle('hidden', !card.dataset.tableName.toLowerCase().includes(query)));
    });

    document.querySelector('[data-category-filter]')?.addEventListener('change', (event) => {
        document.querySelectorAll('.menu-card').forEach((card) => card.classList.toggle('hidden', event.target.value !== 'all' && card.dataset.category !== event.target.value));
    });

    const closeRegisterModal = document.querySelector('[data-close-register-modal]');
    const modalActualCashInput = document.querySelector('[data-modal-actual-cash]');
    const modalActualBankInput = document.querySelector('[data-modal-actual-bank]');
    const closeNoteWrap = document.querySelector('[data-close-note-wrap]');
    const closeNoteInput = document.querySelector('[data-close-note]');
    const closeNoteHiddenInput = document.querySelector('[data-close-note-hidden]');
    const hiddenActualCashInput = document.querySelector('[data-actual-cash]');
    const closeRegisterForm = document.querySelector('.pos-close-register-form');
    const differenceLabel = document.querySelector('[data-close-difference-label]');
    const differenceAmount = document.querySelector('[data-close-difference-amount]');
    const bankDifferenceLabel = document.querySelector('[data-bank-difference-label]');
    const bankDifferenceAmount = document.querySelector('[data-bank-difference-amount]');
    let expectedCashBalance = 0;
    let expectedBankBalance = 0;
    let expenses = [];

    const formatCurrency = (value) => `Rs. ${Number(value || 0).toFixed(2)}`;
    const parseJsonPayload = (selector) => {
        const node = document.querySelector(selector);
        if (!node) {
            return [];
        }

        try {
            return JSON.parse(node.textContent || '[]');
        } catch (error) {
            console.error(error);
            return [];
        }
    };

    const dueCustomers = parseJsonPayload('[data-due-customers-json]');
    const dueSuppliers = parseJsonPayload('[data-due-suppliers-json]');

    const setupDuePaymentModal = (modalRoot) => {
        if (!modalRoot) {
            return;
        }

        const type = modalRoot.dataset.duePaymentModal;
        const records = type === 'supplier' ? dueSuppliers : dueCustomers;
        const endpoint = type === 'supplier' ? pos.dataset.supplierPaymentUrl : pos.dataset.customerDuePaymentUrl;
        const entityKey = type === 'supplier' ? 'supplier_id' : 'customer_id';
        const searchInput = modalRoot.querySelector('[data-due-search]');
        const resultsWrap = modalRoot.querySelector('[data-due-results]');
        const selectedIdInput = modalRoot.querySelector('[data-due-selected-id]');
        const totalLabel = modalRoot.querySelector('[data-due-total]');
        const remainingLabel = modalRoot.querySelector('[data-due-remaining]');
        const amountInput = modalRoot.querySelector('[data-due-amount]');
        const methodSelect = modalRoot.querySelector('[data-due-payment-method]');
        const bankWrap = modalRoot.querySelector('[data-due-bank-wrap]');
        const bankSelect = modalRoot.querySelector('[data-due-bank-account]');
        let selectedRecord = null;

        const updateBankVisibility = () => {
            const showBank = methodSelect?.value !== 'cash';
            bankWrap?.classList.toggle('hidden', !showBank);
            bankWrap?.classList.toggle('grid', showBank);
        };

        const updateRemaining = () => {
            const totalDue = Number(selectedRecord?.due_amount || 0);
            const paidAmount = Math.max(0, Number(amountInput?.value || 0));
            const remainingDue = Math.max(0, totalDue - paidAmount);
            if (totalLabel) {
                totalLabel.textContent = money(totalDue);
            }
            if (remainingLabel) {
                remainingLabel.textContent = money(remainingDue);
            }
        };

        const selectRecord = (record) => {
            selectedRecord = record;
            if (selectedIdInput) {
                selectedIdInput.value = String(record.id);
            }
            if (searchInput) {
                searchInput.value = `${record.name}${record.phone ? ` - ${record.phone}` : ''}`;
            }
            if (amountInput) {
                amountInput.value = '';
                amountInput.max = String(record.due_amount || 0);
            }
            updateRemaining();
        };

        const renderResults = () => {
            if (!resultsWrap) {
                return;
            }

            const query = (searchInput?.value || '').trim().toLowerCase();
            const sortedRecords = [...records].sort((first, second) => Number(second.due_amount || 0) - Number(first.due_amount || 0));
            const matches = (query
                ? sortedRecords.filter((record) => `${record.name} ${record.phone || ''}`.toLowerCase().includes(query)).slice(0, 10)
                : sortedRecords.slice(0, 3));

            if (matches.length === 0) {
                resultsWrap.innerHTML = '<p class="px-2 py-3 text-sm font-bold text-slate-500">No due records found.</p>';
                return;
            }

            resultsWrap.innerHTML = matches.map((record) => `
                <button class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm font-bold hover:bg-white" type="button" data-due-record="${record.id}">
                    <span class="min-w-0">
                        <span class="block truncate text-slate-900">${escapeHtml(record.name)}</span>
                        <span class="block truncate text-xs text-slate-500">${escapeHtml(record.phone || 'No phone')}</span>
                    </span>
                    <strong class="shrink-0 text-blue-700">${money(record.due_amount)}</strong>
                </button>
            `).join('');

            resultsWrap.querySelectorAll('[data-due-record]').forEach((button) => {
                button.addEventListener('click', () => {
                    const record = records.find((item) => Number(item.id) === Number(button.dataset.dueRecord));
                    if (record) {
                        selectRecord(record);
                    }
                });
            });
        };

        const resetModal = () => {
            selectedRecord = null;
            if (searchInput) {
                searchInput.value = '';
            }
            if (selectedIdInput) {
                selectedIdInput.value = '';
            }
            if (amountInput) {
                amountInput.value = '';
                amountInput.removeAttribute('max');
            }
            if (methodSelect) {
                methodSelect.value = 'cash';
            }
            updateBankVisibility();
            updateRemaining();
            renderResults();
        };

        searchInput?.addEventListener('input', renderResults);
        amountInput?.addEventListener('input', updateRemaining);
        methodSelect?.addEventListener('change', updateBankVisibility);
        modalRoot.querySelector('[data-due-cancel]')?.addEventListener('click', () => modalRoot.closest('.modal')?.classList.remove('open'));

        modalRoot.querySelector('[data-due-save]')?.addEventListener('click', async () => {
            const amount = Number(amountInput?.value || 0);

            if (!selectedRecord) {
                showPosToast(type === 'supplier' ? 'Please select a supplier.' : 'Please select a customer.');
                return;
            }

            if (amount <= 0) {
                showPosToast('Please enter a valid amount.');
                return;
            }

            if (amount > Number(selectedRecord.due_amount || 0)) {
                showPosToast('Amount cannot be greater than total due.');
                return;
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                    body: JSON.stringify({
                        [entityKey]: selectedRecord.id,
                        amount,
                        payment_method: methodSelect?.value || 'cash',
                        bank_account_id: methodSelect?.value === 'cash' ? null : (bankSelect?.value || null),
                    }),
                });
                const data = await response.json();

                if (!response.ok) {
                    showPosToast(data.message || 'Unable to save payment.');
                    return;
                }

                selectedRecord.due_amount = Number(data.remaining_due || 0);
                if (selectedRecord.due_amount <= 0) {
                    const index = records.findIndex((record) => Number(record.id) === Number(selectedRecord.id));
                    if (index >= 0) {
                        records.splice(index, 1);
                    }
                }

                modalRoot.closest('.modal')?.classList.remove('open');
                resetModal();
                if (!closeRegisterModal?.classList.contains('hidden')) {
                    await loadRegisterCloseSummary(false);
                }
                showPosToast(data.message || 'Payment saved.', 'Success');
            } catch (error) {
                console.error(error);
                showPosToast('Unable to save payment. Please try again.');
            }
        });

        resetModal();
    };

    document.querySelectorAll('[data-due-payment-modal]').forEach(setupDuePaymentModal);

    // Expense modal handlers
    const expenseModal = document.getElementById('expense-modal');
    const expenseAmountInput = expenseModal?.querySelector('[data-expense-amount]');
    const expenseSourceSelect = expenseModal?.querySelector('[data-expense-source]');
    const expenseBankWrap = expenseModal?.querySelector('[data-expense-bank-wrap]');
    const expenseBankAccountSelect = expenseModal?.querySelector('[data-expense-bank-account]');
    const expenseDescriptionInput = expenseModal?.querySelector('[data-expense-description]');
    const expenseCategorySelect = expenseModal?.querySelector('[data-expense-category]');
    const expenseCategoryPopup = expenseModal?.querySelector('[data-expense-category-popup]');
    const expenseCategoryNameInput = expenseModal?.querySelector('[data-expense-category-name]');
    const expenseCategoryError = expenseModal?.querySelector('[data-expense-category-error]');
    const expenseCategorySaveButton = expenseModal?.querySelector('[data-expense-category-save]');
    const expenseStoreUrl = pos.dataset.expenseStoreUrl || '/pos/expense';
    const expenseCategoryStoreUrl = pos.dataset.expenseCategoryStoreUrl || '/pos/expense-categories';

    const closeExpenseCategoryPopup = () => {
        expenseCategoryPopup?.classList.add('hidden');
        expenseCategoryPopup?.classList.remove('grid');
        if (expenseCategoryNameInput) {
            expenseCategoryNameInput.value = '';
        }
        if (expenseCategoryError) {
            expenseCategoryError.textContent = '';
            expenseCategoryError.classList.add('hidden');
        }
    };

    const showExpenseCategoryError = (message) => {
        if (!expenseCategoryError) {
            showPosToast(message);
            return;
        }

        expenseCategoryError.textContent = message;
        expenseCategoryError.classList.remove('hidden');
    };

    document.querySelector('[data-add-category]')?.addEventListener('click', async () => {
        expenseCategoryPopup?.classList.remove('hidden');
        expenseCategoryPopup?.classList.add('grid');
        expenseCategoryNameInput?.focus();
    });

    expenseModal?.querySelectorAll('[data-expense-category-cancel]').forEach((button) => {
        button.addEventListener('click', closeExpenseCategoryPopup);
    });

    expenseCategorySaveButton?.addEventListener('click', async () => {
        const categoryName = expenseCategoryNameInput?.value?.trim() || '';
        if (categoryName.length === 0) {
            showExpenseCategoryError('Please enter an expense category name.');
            return;
        }

        if (expenseCategoryError) {
            expenseCategoryError.textContent = '';
            expenseCategoryError.classList.add('hidden');
        }

        expenseCategorySaveButton.disabled = true;

        try {
            const response = await fetch(expenseCategoryStoreUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({ name: categoryName }),
            });
            const data = await response.json();

            if (!response.ok) {
                showExpenseCategoryError(data.message || 'Error saving expense category.');
                return;
            }

            const existingOption = expenseCategorySelect?.querySelector(`option[value="${data.category.id}"]`);
            const option = existingOption || document.createElement('option');
            option.value = String(data.category.id);
            option.dataset.name = data.category.name;
            option.textContent = data.category.name;
            if (!existingOption) {
                expenseCategorySelect?.appendChild(option);
            }
            if (expenseCategorySelect) {
                expenseCategorySelect.value = String(data.category.id);
            }
            closeExpenseCategoryPopup();
        } catch (error) {
            console.error(error);
            showExpenseCategoryError('Error saving expense category. Please try again.');
        } finally {
            expenseCategorySaveButton.disabled = false;
        }
    });

    expenseCategoryNameInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            expenseCategorySaveButton?.click();
        }
    });

    const updateExpenseBankVisibility = () => {
        expenseBankWrap?.classList.toggle('hidden', expenseSourceSelect?.value !== 'bank');
        expenseBankWrap?.classList.toggle('grid', expenseSourceSelect?.value === 'bank');
    };

    expenseSourceSelect?.addEventListener('change', updateExpenseBankVisibility);
    updateExpenseBankVisibility();

    const renderExpenses = () => {
        const expensesWrap = document.querySelector('[data-close-expenses-wrap]');
        const expensesList = document.querySelector('[data-close-expenses-list]');

        if (!expensesList) return;

        if (expenses.length === 0) {
            expensesWrap?.classList.add('hidden');
            return;
        }

        expensesWrap?.classList.remove('hidden');
        expensesList.innerHTML = expenses.map((exp, idx) => `
            <div class="grid min-h-24 gap-1 rounded-md bg-white px-3 py-2">
                <div class="min-w-0">
                    <p class="truncate text-xs font-bold text-amber-900">${escapeHtml(exp.category || 'Expense')}</p>
                    <p class="line-clamp-2 text-[11px] leading-snug text-amber-700">${escapeHtml(exp.description || 'Expense')} • ${exp.source === 'drawer' ? 'Drawer Cash' : 'Bank Account'}</p>
                </div>
                <div class="flex items-end justify-between gap-2 self-end">
                    <span class="text-sm font-black text-amber-800">-${money(exp.amount)}</span>
                    ${exp.can_remove ? `<button type="button" data-remove-expense="${idx}" class="text-xs font-bold text-red-600 hover:text-red-700">Remove</button>` : ''}
                </div>
            </div>
        `).join('');

        expensesList.querySelectorAll('[data-remove-expense]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const idx = Number(btn.dataset.removeExpense);
                const expense = expenses[idx];

                if (!expense?.id) return;

                try {
                    const response = await fetch(`${expenseDeleteBaseUrl}/${expense.id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        showPosToast(data.message || 'Error removing expense');
                        return;
                    }

                    await loadRegisterCloseSummary(false);
                    showPosToast('Expense removed successfully', 'Success');
                } catch (error) {
                    console.error(error);
                    showPosToast('Error removing expense. Please try again.');
                }
            });
        });
    };

    document.querySelector('[data-expense-save]')?.addEventListener('click', async () => {
        const amount = Number(expenseAmountInput?.value || 0);
        const source = expenseSourceSelect?.value || 'drawer';
        const bankAccountId = expenseBankAccountSelect?.value || '';
        const categoryValue = expenseCategorySelect?.value || '';
        const description = expenseDescriptionInput?.value?.trim() || '';

        if (!categoryValue) {
            showPosToast('Please select an expense category');
            return;
        }

        if (amount <= 0) {
            showPosToast('Please enter a valid expense amount');
            return;
        }

        // Get the category name from the selected option's data attribute or text
        const selectedOption = expenseCategorySelect?.querySelector(`option[value="${categoryValue}"]`);
        const categoryName = selectedOption?.dataset.name || selectedOption?.textContent || categoryValue;

        try {
            const response = await fetch(expenseStoreUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({
                    amount,
                    category: categoryName,
                    source,
                    bank_account_id: source === 'bank' ? bankAccountId : null,
                    description,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                showPosToast(data.message || 'Error adding expense');
                return;
            }

            if (expenseAmountInput) expenseAmountInput.value = '';
            if (expenseSourceSelect) expenseSourceSelect.value = 'drawer';
            updateExpenseBankVisibility();
            if (expenseCategorySelect) expenseCategorySelect.value = '';
            if (expenseDescriptionInput) expenseDescriptionInput.value = '';

            expenseModal?.classList.remove('open');
            if (!closeRegisterModal?.classList.contains('hidden')) {
                await loadRegisterCloseSummary(false);
            }
            showPosToast('Expense added successfully', 'Success');
        } catch (error) {
            console.error(error);
            showPosToast('Error adding expense. Please try again.');
        }
    });

    document.querySelector('[data-expense-cancel]')?.addEventListener('click', () => {
        expenseModal?.classList.remove('open');
    });

    const updateNoteVisibility = () => {
        const actualCash = Number.parseFloat(modalActualCashInput?.value || '0');
        const actualBank = Number.parseFloat(modalActualBankInput?.value || '0');
        const cashDifference = actualCash - expectedCashBalance;
        const bankDifference = actualBank - expectedBankBalance;
        const shouldShowNote = Math.abs(cashDifference) > 0.00001 || Math.abs(bankDifference) > 0.00001;

        if (shouldShowNote) {
            closeNoteWrap?.classList.remove('hidden');
            return;
        }

        closeNoteWrap?.classList.add('hidden');
    };

    const updateDifferenceResult = () => {
        if (!modalActualCashInput || !differenceLabel || !differenceAmount) {
            return;
        }

        const actualCash = Number.parseFloat(modalActualCashInput.value || '0');
        const difference = actualCash - expectedCashBalance;

        if (difference < 0) {
            differenceLabel.textContent = 'Expense (Shortage)';
            differenceAmount.textContent = formatCurrency(Math.abs(difference));
            differenceAmount.classList.remove('text-emerald-600', 'text-slate-700');
            differenceAmount.classList.add('text-red-600');
            updateNoteVisibility();
            return;
        }

        if (difference > 0) {
            differenceLabel.textContent = 'Profit (Overage)';
            differenceAmount.textContent = formatCurrency(difference);
            differenceAmount.classList.remove('text-red-600', 'text-slate-700');
            differenceAmount.classList.add('text-emerald-600');
            updateNoteVisibility();
            return;
        }

        differenceLabel.textContent = 'Balanced';
        differenceAmount.textContent = formatCurrency(0);
        differenceAmount.classList.remove('text-red-600', 'text-emerald-600');
        differenceAmount.classList.add('text-slate-700');
        updateNoteVisibility();
    };

    const updateBankDifferenceResult = () => {
        if (!modalActualBankInput || !bankDifferenceLabel || !bankDifferenceAmount) {
            return;
        }

        const actualBank = Number.parseFloat(modalActualBankInput.value || '0');
        const difference = actualBank - expectedBankBalance;

        if (difference < 0) {
            bankDifferenceLabel.textContent = 'Bank Expense (Shortage)';
            bankDifferenceAmount.textContent = formatCurrency(Math.abs(difference));
            bankDifferenceAmount.classList.remove('text-emerald-600', 'text-slate-700');
            bankDifferenceAmount.classList.add('text-red-600');
            updateNoteVisibility();
            return;
        }

        if (difference > 0) {
            bankDifferenceLabel.textContent = 'Bank Profit (Overage)';
            bankDifferenceAmount.textContent = formatCurrency(difference);
            bankDifferenceAmount.classList.remove('text-red-600', 'text-slate-700');
            bankDifferenceAmount.classList.add('text-emerald-600');
            updateNoteVisibility();
            return;
        }

        bankDifferenceLabel.textContent = 'Balanced';
        bankDifferenceAmount.textContent = formatCurrency(0);
        bankDifferenceAmount.classList.remove('text-red-600', 'text-emerald-600');
        bankDifferenceAmount.classList.add('text-slate-700');
        updateNoteVisibility();
    };

    const loadRegisterCloseSummary = async (showModal = true) => {
        try {
            const response = await fetch(registerCloseSummaryUrl, {
                headers: {
                    Accept: 'application/json',
                },
            });
            const data = await response.json();

            if (!response.ok) {
                showPosToast(data.message || 'Error loading register summary');
                return;
            }

            const overallCashBreakdown = data.overall_cash_breakdown || {};
            const overallCashBalance = Number(overallCashBreakdown.now ?? data.overall_cash_balance ?? data.cash_balance_now ?? data.cash_in_cashier ?? 0);
            expectedCashBalance = overallCashBalance;
            expectedBankBalance = Number(data.bank_amount || 0);
            expenses = Array.isArray(data.expenses) ? data.expenses : [];

            document.querySelector('[data-cash-in-cashier]').textContent = formatCurrency(overallCashBalance);
            const totalCashSalesNode = document.querySelector('[data-total-cash-sales]');
            if (totalCashSalesNode) totalCashSalesNode.textContent = formatCurrency(data.total_sale_cash);
            document.querySelector('[data-bank-amount]').textContent = formatCurrency(data.bank_amount);
            document.querySelector('[data-total-orders]').textContent = data.total_orders;
            document.querySelector('[data-tables-served]').textContent = data.total_tables_served;
            document.querySelector('[data-takeaway-orders]').textContent = data.total_takeaway_orders;

            const overallCashBeforeNode = document.querySelector('[data-overall-cash-before]');
            if (overallCashBeforeNode) overallCashBeforeNode.textContent = formatCurrency(overallCashBreakdown.before ?? data.cash_balance_before);

            const overallCashOpeningNode = document.querySelector('[data-overall-cash-opening]');
            if (overallCashOpeningNode) overallCashOpeningNode.textContent = formatCurrency(overallCashBreakdown.drawer_open_balance ?? data.cash_drawer_open_balance);

            const overallCashInNode = document.querySelector('[data-overall-cash-in]');
            if (overallCashInNode) overallCashInNode.textContent = formatCurrency(overallCashBreakdown.cash_in ?? 0);

            const overallCashSalesNode = document.querySelector('[data-overall-cash-sales]');
            if (overallCashSalesNode) overallCashSalesNode.textContent = formatCurrency(overallCashBreakdown.total_sale_cash ?? data.total_sale_cash);

            const overallCashOutNode = document.querySelector('[data-overall-cash-out]');
            if (overallCashOutNode) overallCashOutNode.textContent = formatCurrency(overallCashBreakdown.cash_out ?? 0);

            const overallCashExpensesNode = document.querySelector('[data-overall-cash-expenses]');
            if (overallCashExpensesNode) overallCashExpensesNode.textContent = formatCurrency(overallCashBreakdown.total_expense_amount ?? data.total_expense_amount);

            const overallCashNowNode = document.querySelector('[data-overall-cash-now]');
            if (overallCashNowNode) overallCashNowNode.textContent = formatCurrency(overallCashBalance);

            if (modalActualCashInput) {
                modalActualCashInput.value = expectedCashBalance.toFixed(2);
                updateDifferenceResult();
            }

            if (modalActualBankInput) {
                modalActualBankInput.value = expectedBankBalance.toFixed(2);
                updateBankDifferenceResult();
            }

            if (closeNoteInput) {
                closeNoteInput.value = '';
            }

            if (closeNoteHiddenInput) {
                closeNoteHiddenInput.value = '';
            }

            renderExpenses();
            updateNoteVisibility();

            if (showModal) {
                closeRegisterModal?.classList.remove('hidden');
                closeRegisterModal?.classList.add('flex');
                modalActualCashInput?.focus();
            }
        } catch (error) {
            console.error(error);
            showPosToast('Error loading register summary');
        }
    };

    document.querySelector('[data-close-register]')?.addEventListener('click', async (event) => {
        event.preventDefault();
        await loadRegisterCloseSummary();
    });

    modalActualCashInput?.addEventListener('input', updateDifferenceResult);
    modalActualBankInput?.addEventListener('input', updateBankDifferenceResult);

    document.querySelector('[data-close-register-modal-cancel]')?.addEventListener('click', () => {
        closeRegisterModal?.classList.add('hidden');
        closeRegisterModal?.classList.remove('flex');
    });

    document.querySelector('[data-close-register-modal-submit]')?.addEventListener('click', () => {
        const actualCash = Number.parseFloat(modalActualCashInput?.value || '');
        const actualBank = Number.parseFloat(modalActualBankInput?.value || '');

        if (!Number.isFinite(actualCash) || actualCash < 0) {
            showPosToast('Please enter a valid actual cash amount');
            return;
        }

        if (!Number.isFinite(actualBank) || actualBank < 0) {
            showPosToast('Please enter a valid actual bank amount');
            return;
        }

        if (hiddenActualCashInput) {
            hiddenActualCashInput.value = actualCash.toFixed(2);
        }

        if (closeNoteHiddenInput) {
            closeNoteHiddenInput.value = (closeNoteInput?.value || '').trim();
        }

        closeRegisterForm?.submit();
    });

    setPaymentMethod('cash');
    render();
}
