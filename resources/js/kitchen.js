const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));

for (const display of document.querySelectorAll('[data-kitchen-display]')) {
    let orders = [];
    let fingerprint = null;
    let refreshPromise = null;
    let activeController = null;
    let mutationController = null;
    let busy = false;
    let dialogState = null;
    const seenRequests = new Set();
    const kiosk = display.dataset.kiosk === '1';
    const canAccess = () => !kiosk || (document.fullscreenElement === document.documentElement && document.querySelector('[data-kod]')?.dataset.kodReady === '1');
    const board = display.dataset.mode === 'board';
    const canUpdate = board && display.dataset.canUpdate === '1';
    const errorNode = display.querySelector('[data-kitchen-error]');
    const syncNode = display.querySelector('[data-kitchen-sync]');
    const announcement = display.querySelector('[data-kitchen-announcement]');
    const dialog = display.querySelector('[data-kitchen-dialog]');
    const dialogContent = display.querySelector('[data-kitchen-dialog-content]');
    const filter = display.querySelector('[data-kitchen-filter]');
    const statuses = ['queued', 'preparing', 'ready', 'stop_requested', 'stopped'];
    const labels = { queued: 'In queue', preparing: 'Preparing', ready: 'Ready to serve', stop_requested: 'Stop requested', stopped: 'Preparation stopped' };
    const showError = (message) => {
        errorNode.textContent = message;
        errorNode.classList.remove('hidden');
    };
    const ageLabel = (date) => {
        const minutes = Math.max(0, Math.floor((Date.now() - new Date(date.replace(' ', 'T')).getTime()) / 60000));
        return Number.isFinite(minutes) ? (minutes === 0 ? 'Just now' : `${minutes} min elapsed`) : '';
    };
    const action = (order, status, label, kind = 'primary') => `<button type="button" class="btn-${kind}" data-kitchen-action="${status}" data-order-id="${order.id}" ${busy ? 'disabled' : ''}>${label}</button>`;
    const itemsMarkup = (order) => `<ul class="kitchen-items">${order.items.map((item) => `<li><b>${escapeHtml(item.quantity)}×</b><span>${escapeHtml(item.name)}${item.note ? `<small>${escapeHtml(item.note)}</small>` : ''}</span></li>`).join('')}</ul>`;
    const locationLabel = (order) => order.platform ? `${order.platform} · ${order.order_reference}` : order.table ? `Table ${order.table}` : 'Takeaway';
    const requestKey = (order) => `${order.id}:${order.revision}:${order.stop_requested_at}`;
    const applyFilter = () => {
        const selected = filter?.value ?? 'all';
        const activeLanes = display.querySelector('[data-kitchen-active-lanes]');
        activeLanes.hidden = selected === 'stopped';
        activeLanes.classList.toggle('kitchen-lanes-filtered', selected !== 'all');
        for (const status of ['queued', 'preparing', 'ready']) {
            display.querySelector(`.kitchen-lane-${status}`).hidden = selected !== 'all' && selected !== status;
        }
        display.querySelector('[data-kitchen-stopped]').hidden = !orders.some((order) => order.status === 'stopped') || !['all', 'stopped'].includes(selected);
    };
    const render = () => {
        for (const status of statuses) {
            const laneOrders = orders.filter((order) => order.status === status);
            display.querySelector(`[data-kitchen-count="${status}"]`).textContent = laneOrders.length;
            display.querySelector(`[data-kitchen-list="${status}"]`).innerHTML = laneOrders.length ? laneOrders.map((order) => `
                <article class="kitchen-ticket kitchen-ticket-${status}" data-ticket="${order.id}">
                    <div class="kitchen-ticket-top"><strong>#${escapeHtml(order.token)}</strong><span>${escapeHtml(locationLabel(order))}</span><small data-kitchen-age="${escapeHtml(order.sent_at)}">${escapeHtml(ageLabel(order.sent_at))}</small></div>
                    ${board ? `<div class="kitchen-ticket-meta">${escapeHtml(order.token_date)}${order.waiter ? ` · ${escapeHtml(order.waiter)}` : ''}</div>
                        ${order.previous_items ? `<details class="kitchen-revision"><summary>Updated order · review changes (v${order.revision})</summary><p>Previous order:</p>${order.previous_items.map((item) => `<p>${escapeHtml(item.quantity)} × ${escapeHtml(item.name)}</p>`).join('')}<p class="mt-2">Current order below replaces the previous ticket. Prepare only the changes.</p></details>` : ''}
                        ${itemsMarkup(order)}
                        ${order.note ? `<p class="kitchen-note">Note: ${escapeHtml(order.note)}</p>` : ''}` : `<p class="kitchen-strip-items">${order.items.map((item) => `${escapeHtml(item.quantity)}× ${escapeHtml(item.name)}`).join(' · ')}</p>`}
                    ${['stop_requested', 'stopped'].includes(status) ? `<p class="kitchen-stop-message"><strong>${labels[status]}</strong>${order.stop_requester ? `<span>From ${escapeHtml(order.stop_requester)}</span>` : ''}${order.stop_reason ? `<span>${escapeHtml(order.stop_reason)}</span>` : ''}${status === 'stop_requested' ? '<span>Stop cooking this ticket. Kitchen confirmation needed.</span>' : ''}</p>` : ''}
                    <div class="kitchen-ticket-actions">
                        ${canUpdate && status === 'queued' ? action(order, 'preparing', 'Start preparing') : ''}
                        ${canUpdate && ['queued', 'preparing'].includes(status) ? action(order, 'ready', 'Mark ready', status === 'queued' ? 'secondary' : 'primary') : ''}
                        ${canUpdate && ['queued', 'preparing'].includes(status) ? action(order, 'stopped', 'Stop preparing', 'danger') : ''}
                        ${canUpdate && status === 'stop_requested' ? action(order, 'confirm-stop', 'Confirm stopped', 'danger') : ''}
                        ${canUpdate && status === 'stopped' ? action(order, 'delete', 'Delete ticket', 'danger') : ''}
                        ${!board && display.dataset.canRequestStop === '1' && ['queued', 'preparing'].includes(status) ? action(order, 'request-stop', 'Request stop', 'danger') : ''}
                        ${status === 'ready' && display.dataset.canServe === '1' ? action(order, 'served', 'Mark served') : ''}
                        ${!board && order.resume_url && display.dataset.canResume === '1' ? `<button type="button" class="btn-secondary" data-kitchen-resume="${order.id}" ${busy ? 'disabled' : ''}>Open order</button>` : ''}
                        ${board && status === 'ready' ? '<span class="kitchen-ready-label">Ready · awaiting collection</span>' : ''}
                    </div>
                </article>`).join('') : `<p class="kitchen-empty">${status === 'queued' ? 'No orders waiting' : status === 'preparing' ? 'No orders being prepared' : status === 'ready' ? 'No orders ready yet' : 'No stopped orders'}</p>`;
        }
        const pending = orders.some((order) => order.status === 'stop_requested');
        display.querySelector('[data-kitchen-attention]').hidden = !pending;
        display.querySelector('[data-kitchen-stop-requests]').hidden = !pending;
        applyFilter();
    };
    const closeDialog = () => {
        dialogState = null;
        if (dialog.open) dialog.close();
        dialogContent.replaceChildren();
    };
    const openDialog = (order, type) => {
        if (!canAccess() || busy) return;
        const options = {
            'request-stop': ['Ask kitchen to stop?', 'The kitchen will receive a live stop alert. Wait for their confirmation before deleting the ticket.', 'Send stop request'],
            stopped: ['Stop preparing this order?', 'Confirm that preparation has stopped. The ticket will move to Stopped orders.', 'Confirm stopped'],
            'confirm-stop': ['Cashier requests: stop preparing', 'Stop cooking this ticket now, then confirm below. The cashier will see your confirmation.', 'Confirm stopped'],
            delete: ['Delete stopped ticket?', 'Remove this ticket from the kitchen display. The held bill stays available in POS.', 'Delete ticket'],
        };
        const [title, description, confirmLabel] = options[type];
        dialogState = { id: order.id, revision: order.revision, type, status: order.status };
        dialogContent.innerHTML = `<form data-kitchen-confirm-form>
            <p class="kitchen-dialog-eyebrow">${type === 'confirm-stop' ? 'LIVE STOP REQUEST' : 'KITCHEN ORDER'}</p>
            <h2 id="kitchen-dialog-title">${title}</h2>
            <p class="kitchen-dialog-description">${description}</p>
            <div class="kitchen-dialog-ticket"><strong>#${escapeHtml(order.token)}</strong><span>${escapeHtml(locationLabel(order))}</span></div>
            ${type === 'confirm-stop' ? `${itemsMarkup(order)}<p class="kitchen-stop-message">${order.stop_requester ? `<strong>From ${escapeHtml(order.stop_requester)}</strong>` : ''}<span>${escapeHtml(order.stop_reason)}</span></p>` : ''}
            ${type === 'request-stop' ? '<label class="kitchen-reason-label" for="kitchen-stop-reason">Reason (optional)</label><textarea id="kitchen-stop-reason" name="reason" maxlength="500" rows="3" placeholder="For example: customer changed their order"></textarea>' : ''}
            <p class="kitchen-dialog-error" data-kitchen-dialog-error role="alert" hidden></p>
            <div class="kitchen-dialog-actions"><button type="button" class="btn-secondary" data-kitchen-dialog-cancel>${type === 'confirm-stop' ? 'Review on board' : 'Go back'}</button><button type="submit" class="btn-danger">${confirmLabel}</button></div>
        </form>`;
        if (!dialog.open) dialog.showModal();
    };
    const reconcileDialog = () => {
        if (busy || !canAccess()) return;
        if (dialogState) {
            const order = orders.find((order) => order.id === dialogState.id);
            if (!order || order.revision !== dialogState.revision || order.status !== dialogState.status) closeDialog();
        }
        if (!dialogState && canUpdate) {
            const pending = orders.find((order) => order.status === 'stop_requested' && !seenRequests.has(requestKey(order)));
            if (pending) {
                seenRequests.add(requestKey(pending));
                announcement.textContent = `Stop request for order ${pending.token}. Stop preparing this order.`;
                openDialog(pending, 'confirm-stop');
            }
        }
    };
    const loadOrders = async () => {
        const controller = new AbortController();
        activeController = controller;
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(display.dataset.feedUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
            if (kiosk && [401, 419].includes(response.status)) document.dispatchEvent(new CustomEvent('kod:locked'));
            if (!response.ok) throw new Error('Kitchen connection interrupted. Refresh to try again.');
            const data = await response.json();
            if (!canAccess()) return;
            const nextFingerprint = JSON.stringify(data.orders);
            orders = data.orders;
            if (fingerprint !== nextFingerprint) {
                fingerprint = nextFingerprint;
                render();
            }
            display.querySelectorAll('[data-kitchen-age]').forEach((element) => { element.textContent = ageLabel(element.dataset.kitchenAge); });
            errorNode.classList.add('hidden');
            syncNode.textContent = `Live · Updated ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
            reconcileDialog();
        } catch (error) {
            if (!canAccess()) return;
            showError(error.name === 'AbortError' ? 'Kitchen connection timed out. Retrying automatically…' : 'Kitchen connection interrupted. Retrying automatically…');
            syncNode.textContent = 'Disconnected · orders may be out of date';
        } finally {
            clearTimeout(timeout);
            if (activeController === controller) activeController = null;
        }
    };
    const refresh = async () => {
        if (!canAccess()) return;
        if (refreshPromise) return refreshPromise;
        refreshPromise = loadOrders();
        try { await refreshPromise; } finally { refreshPromise = null; }
    };
    const mutate = async (order, type, reason = null) => {
        if (busy || !canAccess()) return;
        busy = true;
        display.querySelectorAll('.kitchen-ticket button, .kitchen-dialog button').forEach((button) => { button.disabled = true; });
        const controller = new AbortController();
        mutationController = controller;
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const url = type === 'delete' ? order.delete_url : type === 'request-stop' ? order.stop_url : type === 'served' ? order.served_url : order.status_url;
            const response = await fetch(url, {
                method: type === 'delete' ? 'DELETE' : 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ status: type === 'confirm-stop' ? 'stopped' : type, revision: order.revision, reason }),
                signal: controller.signal,
            });
            if (kiosk && [401, 419].includes(response.status)) document.dispatchEvent(new CustomEvent('kod:locked'));
            const data = await response.json().catch(() => { throw new Error('Unable to read the response. Refresh before trying again.'); });
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] || data.message || 'Unable to update this order.');
            if (canAccess()) {
                closeDialog();
                announcement.textContent = data.message;
            }
        } catch (error) {
            if (canAccess()) {
                const message = error.name === 'AbortError' ? 'Request timed out. Check the latest ticket before retrying.' : error.message;
                if (dialogState) {
                    const node = dialog.querySelector('[data-kitchen-dialog-error]');
                    node.textContent = message;
                    node.hidden = false;
                }
                showError(message);
            }
        } finally {
            clearTimeout(timeout);
            mutationController = null;
            if (refreshPromise) await refreshPromise;
            await refresh();
            busy = false;
            render();
            dialog.querySelectorAll('button').forEach((button) => { button.disabled = false; });
            reconcileDialog();
        }
    };
    display.addEventListener('click', (event) => {
        if (!canAccess() || busy) return;
        const resume = event.target.closest('[data-kitchen-resume]');
        if (resume) {
            const order = orders.find((order) => String(order.id) === resume.dataset.kitchenResume);
            if (order) document.dispatchEvent(new CustomEvent('kitchen:resume', { detail: order }));
        }
        const button = event.target.closest('[data-kitchen-action]');
        if (!button || button.disabled) return;
        const order = orders.find((order) => String(order.id) === button.dataset.orderId);
        if (!order) return;
        const type = button.dataset.kitchenAction;
        if (['request-stop', 'stopped', 'confirm-stop', 'delete'].includes(type)) openDialog(order, type);
        else mutate(order, type);
    });
    dialog.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!dialogState || busy) return;
        const state = dialogState;
        const order = orders.find((order) => order.id === state.id && order.revision === state.revision && order.status === state.status);
        if (!order) { closeDialog(); refresh(); return; }
        mutate(order, state.type, dialog.querySelector('[name="reason"]')?.value.trim() || null);
    });
    dialog.addEventListener('click', (event) => { if (event.target.closest('[data-kitchen-dialog-cancel]') && !busy) { closeDialog(); reconcileDialog(); } });
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); if (!busy) { closeDialog(); reconcileDialog(); } });
    filter?.addEventListener('change', applyFilter);
    display.querySelector('[data-kitchen-refresh]').addEventListener('click', refresh);
    document.addEventListener('kitchen:refresh', refresh);
    if (kiosk) {
        document.addEventListener('kod:access', (event) => {
            if (event.detail.ready) refresh();
            else {
                activeController?.abort();
                mutationController?.abort();
                closeDialog();
                orders = [];
                fingerprint = null;
                seenRequests.clear();
                render();
                syncNode.textContent = 'Paused · enter fullscreen to continue';
            }
        });
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    setInterval(() => { if (!document.hidden && canAccess()) refresh(); }, 2000);
    refresh();
}
