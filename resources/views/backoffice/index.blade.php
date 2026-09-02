<x-layouts.app :heading="$config['label']" :title="$config['label']" :show-date-filter="$module !== 'waiters'">
    <div class="space-y-5">
        <div class="pos-card">
            <form method="GET" class="flex flex-1 flex-wrap gap-3">
                <input class="form-control max-w-md" name="search" placeholder="Search {{ strtolower($config['label']) }}" value="{{ request('search') }}">
                @foreach(($config['filters'] ?? []) as $column => $source)
                    <select class="form-control max-w-52" name="{{ $column }}">
                        <option value="all">{{ str($column)->headline() }}</option>
                        @if(is_array($source))
                            @foreach($source as $option)
                                <option @selected(request($column) === $option)>{{ $option }}</option>
                            @endforeach
                        @else
                            @foreach($lookups[$source] ?? [] as $option)
                                <option value="{{ $option->id }}" @selected(request($column) == $option->id)>{{ $option->name }}</option>
                            @endforeach
                        @endif
                    </select>
                @endforeach
                <button class="btn-secondary">Filter</button>
            </form>
            @can($config['permission_prefix'].'.create')
                <div class="mt-3 flex justify-end">
                    <a class="btn-primary" href="{{ route('backoffice.modules.create', $module) }}">Add {{ str($config['label'])->singular() }}</a>
                </div>
            @endcan
        </div>

        <div class="pos-card p-0">
            <div class="data-table-wrap">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            @foreach($config['fields'] as $name => $field)
                                @php
                                    // For waiters listing hide internal availability fields, we'll render a dedicated toggle column
                                    $skipForWaiters = ($module === 'waiters' && in_array($name, ['is_available', 'is_active'], true));
                                @endphp
                                @if(!$skipForWaiters && !($field['virtual'] ?? false) && (($field['list'] ?? true) !== false))
                                    @can($config['permission_prefix'].'.field.'.$name)
                                        <th class="px-4 py-3">{{ $field['label'] }}</th>
                                    @endcan
                                @endif
                            @endforeach
                            @if($module === 'sales')
                                <th class="px-4 py-3">Token No</th>
                            @endif
                            @if($module === 'customers')
                                <th class="px-4 py-3">Due Amount</th>
                            @endif
                            @if($module === 'waiters')
                                <th class="px-4 py-3">Active</th>
                                <th class="px-4 py-3">Total Earned</th>
                                <th class="px-4 py-3">Total Paid</th>
                                <th class="px-4 py-3">Balance Due</th>
                            @endif
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                @foreach($config['fields'] as $name => $field)
                                    @php
                                        $skipForWaiters = ($module === 'waiters' && in_array($name, ['is_available', 'is_active'], true));
                                    @endphp
                                    @if(!$skipForWaiters && !($field['virtual'] ?? false) && (($field['list'] ?? true) !== false))
                                        @can($config['permission_prefix'].'.field.'.$name)
                                            @php
                                                $value = $record->{$name} ?? null;

                                                if (($field['type'] ?? null) === 'select' && isset($field['source'])) {
                                                    $sourceRows = $lookups[$field['source']] ?? collect();
                                                    $match = $sourceRows->firstWhere('id', $value);
                                                    $value = $match->name ?? $match->number ?? $value;
                                                }

                                                if (($field['type'] ?? null) === 'select_static' && isset($field['options'])) {
                                                    $value = $field['options'][$value] ?? $value;
                                                }

                                                $display = filled($value) ? $value : '-';
                                            @endphp
                                            <td class="max-w-56 truncate px-4 py-3">
                                                @if(($field['type'] ?? null) === 'boolean')
                                                    @php($enabled = (bool) $value)
                                                    @if($name === 'is_active' && auth()->user()->can($config['permission_prefix'].'.edit'))
                                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full px-2 py-1 text-xs font-black {{ $enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}" aria-label="Toggle active status">
                                                            <input type="checkbox" class="peer sr-only generic-active-checkbox" data-id="{{ $record->id }}" data-module="{{ $module }}" @checked($enabled)>
                                                            <span class="relative h-5 w-9 rounded-full bg-red-500 shadow-inner ring-1 ring-red-600/20 transition duration-200 after:absolute after:left-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-white after:shadow after:ring-1 after:ring-slate-200 after:transition after:duration-200 peer-checked:bg-emerald-500 peer-checked:ring-emerald-600/20 peer-checked:after:translate-x-4"></span>
                                                            <span data-active-label>{{ $enabled ? 'Active' : 'Inactive' }}</span>
                                                        </label>
                                                    @else
                                                        <span class="inline-flex items-center gap-2 rounded-full px-2 py-1 text-xs font-black {{ $enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                                            <span class="relative h-5 w-9 rounded-full transition {{ $enabled ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                                                <span class="absolute left-0.5 top-0.5 size-4 rounded-full bg-white shadow transition {{ $enabled ? 'translate-x-4' : '' }}"></span>
                                                            </span>
                                                            {{ $enabled ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    @endif
                                                @else
                                                    {{ str($display)->limit(45) }}
                                                @endif
                                            </td>
                                        @endcan
                                    @endif
                                @endforeach
                                @if($module === 'sales')
                                    <td class="px-4 py-3 font-black text-blue-700">
                                        {{ $record->token_number ? str_pad((string) $record->token_number, 2, '0', STR_PAD_LEFT) : '-' }}
                                    </td>
                                @endif
                                @if($module === 'customers')
                                    <td class="px-4 py-3 font-black {{ ((float) ($record->due_balance ?? 0)) > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                        Rs. {{ number_format((float) ($record->due_balance ?? 0), 2) }}
                                    </td>
                                @endif
                                @if($module === 'waiters')
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center">
                                            <label class="inline-flex cursor-pointer items-center" aria-label="Toggle waiter active status">
                                                <input type="checkbox" class="peer sr-only waiter-active-checkbox" data-id="{{ $record->id }}" {{ ($record->is_active ?? true) ? 'checked' : '' }}>
                                                <span class="relative h-6 w-11 rounded-full bg-red-500 shadow-inner ring-1 ring-red-600/20 transition duration-200 after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:ring-1 after:ring-slate-200 after:transition after:duration-200 peer-checked:bg-emerald-500 peer-checked:ring-emerald-600/20 peer-checked:after:translate-x-5"></span>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-bold">Rs. {{ number_format((float) ($record->earned_incentive ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-slate-600">Rs. {{ number_format((float) ($record->paid_incentive ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 font-bold text-emerald-700">Rs. {{ number_format((float) ($record->payable_incentive ?? 0), 2) }}</td>
                                @endif
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        @can($config['permission_prefix'].'.view')
                                            <a class="btn-mini" href="{{ route('backoffice.modules.show', [$module, $record->id]) }}">View</a>
                                        @endcan
                                        @if($module === 'sales' && auth()->user()->can('sales.print'))
                                            <a class="btn-mini" href="{{ route('backoffice.modules.print', [$module, $record->id]) }}" target="_blank" rel="noopener">Print</a>
                                        @endif
                                        @can($config['permission_prefix'].'.edit')
                                            <a class="btn-mini" href="{{ route('backoffice.modules.edit', [$module, $record->id]) }}">Edit</a>
                                        @endcan
                                        @if($module === 'sales' && auth()->user()->can('sales.edit') && ($record->due_amount ?? 0) > 0)
                                            <button class="btn-mini" type="button" data-payments-button data-id="{{ $record->id }}">Payments</button>
                                        @endif
                                        @if($module === 'waiters' && auth()->user()->can('waiters.edit'))
                                            <button class="btn-mini" type="button" data-waiter-payments-button data-id="{{ $record->id }}" @disabled(((float) ($record->payable_incentive ?? 0)) <= 0)>Payment</button>
                                        @endif
                                        @can($config['permission_prefix'].'.delete')
                                            <form method="POST" action="{{ route('backoffice.modules.destroy', [$module, $record->id]) }}" data-confirm="Delete this record?">
                                                @csrf @method('DELETE')
                                                <button class="btn-mini-danger">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="20" class="px-4 py-10 text-center text-slate-500">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3">{{ $records->links() }}</div>
        </div>
    </div>

    @push('scripts')
        <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-payments-button]');
            if (!btn) return;
            const id = btn.dataset.id;
            const module = '{{ $module }}';

            btn.disabled = true;
            btn.textContent = 'Loading...';

            fetch(`{{ url('/manage') }}/${module}/${id}/payments`)
                .then(r => {
                    if (!r.ok) {
                        throw new Error(`HTTP error! status: ${r.status} ${r.statusText}`);
                    }

                    return r.text();
                })
                .then(html => {
                    try {
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = html;
                        document.body.appendChild(wrapper);

                        const modal = wrapper.querySelector('#sale-payments-modal');
                        if (!modal) {
                            wrapper.remove();
                            btn.disabled = false;
                            btn.textContent = 'Payments';
                            alert('Payment popup could not be loaded.');

                            return;
                        }

                        const overlay = modal.querySelector('[data-modal-overlay]');
                        const panel = modal.querySelector('.modal-panel');

                        if (!overlay || !panel) {
                            wrapper.remove();
                            btn.disabled = false;
                            btn.textContent = 'Payments';
                            alert('Payment popup could not be opened.');

                            return;
                        }

                        requestAnimationFrame(() => {
                            overlay.classList.remove('opacity-0');
                            overlay.classList.add('opacity-100');
                            panel.classList.remove('opacity-0', 'translate-y-6', 'scale-95');
                            panel.classList.add('opacity-100', 'translate-y-0', 'scale-100');
                        });

                        const closeModal = () => {
                            overlay.classList.remove('opacity-100');
                            overlay.classList.add('opacity-0');
                            panel.classList.remove('opacity-100', 'translate-y-0', 'scale-100');
                            panel.classList.add('opacity-0', 'translate-y-6', 'scale-95');
                            setTimeout(() => wrapper.remove(), 250);
                            document.removeEventListener('keydown', escapeHandler);
                            btn.disabled = false;
                            btn.textContent = 'Payments';
                        };

                        const escapeHandler = (ev) => {
                            if (ev.key === 'Escape') closeModal();
                        };

                        modal.querySelectorAll('[data-modal-close]').forEach((el) => {
                            el.addEventListener('click', closeModal);
                        });
                        overlay?.addEventListener('click', closeModal);
                        document.addEventListener('keydown', escapeHandler);

                        const form = modal.querySelector('form[data-sale-payments-form]');
                        if (form) {
                            const paymentAmount = form.querySelector('[data-payment-amount]');
                            const deductionAmount = form.querySelector('[data-deduction-amount]');
                            const livePaid = modal.querySelector('[data-live-paid]');
                            const liveDue = modal.querySelector('[data-live-due]');
                            const basePaid = Number(livePaid?.dataset.basePaid || 0);
                            const baseDue = Number(liveDue?.dataset.baseDue || 0);
                            const money = new Intl.NumberFormat('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            });
                            const amount = (input) => Math.max(0, Number(input?.value || 0) || 0);
                            const updateLiveTotals = () => {
                                const payment = amount(paymentAmount);
                                const deduction = amount(deductionAmount);

                                if (livePaid) {
                                    livePaid.textContent = money.format(basePaid + payment);
                                }

                                if (liveDue) {
                                    liveDue.textContent = money.format(Math.max(0, baseDue - payment - deduction));
                                }
                            };

                            paymentAmount?.addEventListener('input', updateLiveTotals);
                            deductionAmount?.addEventListener('input', updateLiveTotals);
                            updateLiveTotals();

                            form.addEventListener('submit', (ev) => {
                                ev.preventDefault();
                                if (amount(paymentAmount) <= 0 && amount(deductionAmount) <= 0) {
                                    alert('Enter a payment amount or deduction amount.');

                                    return;
                                }

                                const fd = new FormData(form);

                                fetch(form.action, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: fd,
                                }).then((res) => {
                                    if (res.ok) {
                                        location.reload();
                                    } else {
                                        res.text().then(t => {
                                            console.error('[Payments] Form error response:', t);
                                            alert('Error: Failed to record payment. Check browser console.');
                                        });
                                    }
                                }).catch((err) => {
                                    console.error('[Payments] Form request failed:', err);
                                    alert('Request failed: ' + err.message);
                                });
                            });
                        }
                        btn.disabled = false;
                        btn.textContent = 'Payments';
                    } catch (err) {
                        console.error('[Payments] Modal initialization error:', err);
                        btn.disabled = false;
                        btn.textContent = 'Payments';
                        alert('Failed to open payments modal: ' + err.message);
                    }
                })
                .catch(err => {
                    console.error('[Payments] Fetch error:', err);
                    btn.disabled = false;
                    btn.textContent = 'Payments';
                    alert('Failed to fetch payments modal: ' + err.message);
                });
        });

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-waiter-payments-button]');
            if (!btn) return;
            const id = btn.dataset.id;
            const module = '{{ $module }}';
            const originalText = btn.textContent;

            btn.disabled = true;
            btn.textContent = 'Loading...';

            fetch(`{{ url('/manage') }}/${module}/${id}/payments`)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    return response.text();
                })
                .then((html) => {
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    document.body.appendChild(wrapper);

                    const modal = wrapper.querySelector('#waiter-payments-modal');
                    const overlay = modal?.querySelector('[data-modal-overlay]');
                    const panel = modal?.querySelector('.modal-panel');

                    if (!modal || !overlay || !panel) {
                        wrapper.remove();
                        throw new Error('Payment popup could not be opened.');
                    }

                    requestAnimationFrame(() => {
                        overlay.classList.remove('opacity-0');
                        overlay.classList.add('opacity-100');
                        panel.classList.remove('opacity-0', 'translate-y-6', 'scale-95');
                        panel.classList.add('opacity-100', 'translate-y-0', 'scale-100');
                    });

                    const closeModal = () => {
                        overlay.classList.remove('opacity-100');
                        overlay.classList.add('opacity-0');
                        panel.classList.remove('opacity-100', 'translate-y-0', 'scale-100');
                        panel.classList.add('opacity-0', 'translate-y-6', 'scale-95');
                        setTimeout(() => wrapper.remove(), 250);
                        document.removeEventListener('keydown', escapeHandler);
                        btn.disabled = false;
                        btn.textContent = originalText;
                    };

                    const escapeHandler = (event) => {
                        if (event.key === 'Escape') closeModal();
                    };

                    modal.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', closeModal));
                    overlay.addEventListener('click', closeModal);
                    document.addEventListener('keydown', escapeHandler);

                    const form = modal.querySelector('[data-waiter-payment-form]');
                    const errorBox = modal.querySelector('[data-waiter-payment-error]');

                    form?.addEventListener('submit', async (event) => {
                        event.preventDefault();
                        errorBox?.classList.add('hidden');

                        const submitButton = form.querySelector('button[type="submit"]');
                        submitButton.disabled = true;
                        submitButton.textContent = 'Saving...';

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: new FormData(form),
                            });

                            if (!response.ok) {
                                const payload = await response.json().catch(() => ({}));
                                const errors = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'Unable to save waiter payment.'];
                                throw new Error(errors[0]);
                            }

                            location.reload();
                        } catch (error) {
                            if (errorBox) {
                                errorBox.textContent = error.message || 'Unable to save waiter payment.';
                                errorBox.classList.remove('hidden');
                            }
                            submitButton.disabled = false;
                            submitButton.textContent = 'Save Payment';
                        }
                    });
                })
                .catch((error) => {
                    console.error('[Waiter Payments] Fetch error:', error);
                    btn.disabled = false;
                    btn.textContent = originalText;
                    alert(error.message || 'Failed to open waiter payment popup.');
                });
        });
        </script>
    @endpush

    @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.generic-active-checkbox').forEach((checkbox) => {
                checkbox.addEventListener('change', async function () {
                    const id = this.dataset.id;
                    const module = this.dataset.module;
                    const active = this.checked ? 1 : 0;
                    const wrapper = this.closest('label');
                    const label = wrapper?.querySelector('[data-active-label]');

                    this.disabled = true;

                    try {
                        const res = await fetch(`{{ url('/manage') }}/${module}/${id}/toggle-active`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ is_active: active })
                        });

                        if (!res.ok) {
                            throw new Error('Failed to update');
                        }

                        wrapper?.classList.toggle('bg-emerald-50', active === 1);
                        wrapper?.classList.toggle('text-emerald-700', active === 1);
                        wrapper?.classList.toggle('bg-red-50', active === 0);
                        wrapper?.classList.toggle('text-red-700', active === 0);

                        if (label) {
                            label.textContent = active === 1 ? 'Active' : 'Inactive';
                        }
                    } catch (err) {
                        console.error('[Active Toggle] Toggle failed', err);
                        alert('Unable to update active status.');
                        this.checked = !this.checked;
                    } finally {
                        this.disabled = false;
                    }
                });
            });

            document.querySelectorAll('.waiter-active-checkbox').forEach((checkbox) => {
                checkbox.addEventListener('change', async function () {
                    const id = this.dataset.id;
                    const active = this.checked ? 1 : 0;
                    const module = '{{ $module }}';

                    try {
                        const res = await fetch(`{{ url('/manage') }}/${module}/${id}/toggle-active`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ is_active: active })
                        });

                        if (!res.ok) throw new Error('Failed to update');

                        const json = await res.json();

                        // Update POS waiter options if present
                        const waiterOptions = document.querySelector('[data-waiter-options]');
                        if (waiterOptions) {
                            if (active === 1) {
                                // add or update
                                let span = waiterOptions.querySelector(`span[data-id="${id}"]`);
                                if (!span) {
                                    span = document.createElement('span');
                                    span.dataset.id = id;
                                    span.textContent = json.waiter.name;
                                    waiterOptions.appendChild(span);
                                } else {
                                    span.textContent = json.waiter.name;
                                }
                            } else {
                                const span = waiterOptions.querySelector(`span[data-id="${id}"]`);
                                if (span) span.remove();
                            }
                        }

                    } catch (err) {
                        console.error('[Waiters] Toggle failed', err);
                        alert('Unable to update waiter active status.');
                        // revert
                        this.checked = !this.checked;
                    }
                });
            });
        });
        </script>
    @endpush
</x-layouts.app>
