const kiosk = document.querySelector('[data-kod]');

if (kiosk) {
    const gate = kiosk.querySelector('[data-kod-gate]');
    const board = kiosk.querySelector('[data-kod-board]');
    const pinForm = kiosk.querySelector('[data-kod-pin-form]');
    const pinInput = pinForm.elements.pin;
    const fullscreenButton = kiosk.querySelector('[data-kod-fullscreen]');
    const errorNode = kiosk.querySelector('[data-kod-error]');
    const title = kiosk.querySelector('[data-kod-title]');
    const description = kiosk.querySelector('[data-kod-description]');
    let unlocked = kiosk.dataset.kodUnlocked === '1';
    let submitting = false;
    const fullscreen = () => document.fullscreenElement === document.documentElement;
    const showError = (message) => {
        errorNode.textContent = message;
        errorNode.hidden = false;
    };
    const synchronize = () => {
        const ready = unlocked && fullscreen();
        kiosk.dataset.kodReady = ready ? '1' : '0';
        kiosk.classList.toggle('kod-active', ready);
        gate.hidden = ready;
        board.hidden = !ready;
        board.inert = !ready;
        pinForm.hidden = unlocked;
        fullscreenButton.hidden = !unlocked;
        gate.querySelector('[data-kod-lock]').hidden = !unlocked;
        title.textContent = unlocked ? 'Ready when you are.' : 'Kitchen display';
        description.textContent = unlocked ? 'Enter fullscreen to continue working with your live kitchen orders.' : 'Enter your four-digit PIN to open the live kitchen board in fullscreen.';
        document.dispatchEvent(new CustomEvent('kod:access', { detail: { ready } }));
    };
    const enterFullscreen = async () => {
        if (fullscreen()) return;
        if (!document.fullscreenEnabled || !document.documentElement.requestFullscreen) {
            throw new Error('Fullscreen is unavailable in this browser. Open this address in Chrome, Edge, Firefox, or a browser that supports fullscreen.');
        }
        try {
            await document.documentElement.requestFullscreen({ navigationUI: 'hide' });
        } catch {
            throw new Error('Fullscreen could not start. Tap Resume fullscreen to try again.');
        }
    };
    const lockLocally = () => {
        unlocked = false;
        kiosk.dataset.kodUnlocked = '0';
        pinInput.value = '';
        errorNode.hidden = true;
        synchronize();
    };
    const request = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST', cache: 'no-store', headers: {
                'Content-Type': 'application/json', Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }, body: JSON.stringify(body), signal: AbortSignal.timeout(10000),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(response.status === 429 ? 'Too many attempts. Wait one minute and try again.' : response.status === 419 ? 'This session expired. Reload the page and enter the PIN again.' : data.errors?.pin?.[0] || data.message || 'Connection interrupted. Please try again.');
        }
        if (data.csrf_token) {
            document.querySelector('meta[name="csrf-token"]').content = data.csrf_token;
            kiosk.querySelectorAll('input[name="_token"]').forEach((input) => { input.value = data.csrf_token; });
        }
        return data;
    };
    pinInput.addEventListener('input', () => { pinInput.value = pinInput.value.replace(/[^0-9]/g, '').slice(0, 4); });
    kiosk.querySelectorAll('[data-kod-digit]').forEach((button) => button.addEventListener('click', () => {
        if (pinInput.value.length < 4) pinInput.value += button.dataset.kodDigit;
    }));
    kiosk.querySelector('[data-kod-clear]').addEventListener('click', () => { pinInput.value = ''; });
    kiosk.querySelector('[data-kod-delete]').addEventListener('click', () => { pinInput.value = pinInput.value.slice(0, -1); });
    pinForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submitting) return;
        submitting = true;
        const button = kiosk.querySelector('[data-kod-unlock]');
        button.disabled = true;
        errorNode.hidden = true;
        // Start fullscreen while this submit still has browser user activation.
        const fullscreenResult = enterFullscreen().then(() => null, (error) => error);
        try {
            await request(pinForm.action, { pin: pinInput.value });
            unlocked = true;
            kiosk.dataset.kodUnlocked = '1';
            pinInput.value = '';
            const fullscreenError = await fullscreenResult;
            synchronize();
            if (fullscreenError) showError(fullscreenError.message);
        } catch (error) {
            await fullscreenResult;
            pinInput.value = '';
            showError(error.name === 'TimeoutError' ? 'Kitchen connection timed out. Try again.' : error.message);
        } finally {
            submitting = false;
            button.disabled = false;
        }
    });
    fullscreenButton.addEventListener('click', async () => {
        errorNode.hidden = true;
        try { await enterFullscreen(); synchronize(); } catch (error) { showError(error.message); }
    });
    const lockDisplay = async (event) => {
        event.preventDefault();
        const lockUrl = kiosk.querySelector('[data-kod-lock-form]').action;
        lockLocally();
        try {
            await request(lockUrl, {});
            if (fullscreen()) await document.exitFullscreen();
        } catch (error) {
            showError(`Display hidden. Lock could not be confirmed: ${error.message}`);
        }
    };
    kiosk.querySelector('[data-kod-lock-form]').addEventListener('submit', lockDisplay);
    board.querySelector('[data-kod-lock]').addEventListener('click', lockDisplay);
    document.addEventListener('fullscreenchange', synchronize);
    document.addEventListener('kod:locked', () => { lockLocally(); showError('Kitchen display locked. Enter the PIN again.'); });
    const updateClock = () => { kiosk.querySelector('[data-kod-clock]').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); };
    updateClock();
    setInterval(updateClock, 1000);
    synchronize();
}
