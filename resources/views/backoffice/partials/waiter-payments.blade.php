<div id="waiter-payments-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/50 opacity-0 backdrop-blur-sm transition" data-modal-overlay></div>

    <div class="modal-panel relative w-full max-w-xl translate-y-6 scale-95 rounded-lg bg-white opacity-0 shadow-2xl ring-1 ring-slate-950/10 transition">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-4">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Waiter Incentive</p>
                <h3 class="mt-1 text-xl font-black text-slate-900">{{ $waiter->name }}</h3>
            </div>
            <button type="button" class="grid size-9 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-100" data-modal-close aria-label="Close waiter payment popup">
                <x-lucide name="x" class="size-4" />
            </button>
        </div>

        <div class="space-y-5 p-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Total Earned</p>
                    <p class="mt-2 text-xl font-black text-slate-900">Rs. {{ number_format($earned, 2) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Total Paid</p>
                    <p class="mt-2 text-xl font-black text-slate-900">Rs. {{ number_format($paid, 2) }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs font-black uppercase tracking-wide text-emerald-700">Balance Due</p>
                    <p class="mt-2 text-xl font-black text-emerald-700">Rs. {{ number_format($payable, 2) }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('backoffice.modules.payments.store', [$module, $waiter->id]) }}" class="grid gap-4 sm:grid-cols-2" data-waiter-payment-form>
                @csrf
                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Payment Amount
                    <input class="form-control" type="number" step="0.01" min="0.01" max="{{ $payable }}" name="amount" value="{{ number_format($payable, 2, '.', '') }}" required>
                </label>

                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Payment Method
                    <select class="form-control" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                        <option value="card">Card</option>
                    </select>
                </label>

                <label class="grid gap-2 text-sm font-bold text-slate-800">
                    Paid Date
                    <input class="form-control" type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                </label>

                <label class="grid gap-2 text-sm font-bold text-slate-800 sm:col-span-2">
                    Note
                    <textarea class="form-control min-h-20" name="note" placeholder="Optional note">Waiter incentive payment for {{ $waiter->name }}</textarea>
                </label>

                <p class="hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 sm:col-span-2" data-waiter-payment-error></p>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-4 sm:col-span-2">
                    <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                    <button type="submit" class="btn-primary" @disabled($payable <= 0)>Save Payment</button>
                </div>
            </form>

            @if($payments->isNotEmpty())
                <div class="rounded-lg border border-slate-200">
                    <div class="border-b border-slate-100 px-4 py-3 text-sm font-black text-slate-700">Previous Payments</div>
                    <div class="max-h-40 divide-y divide-slate-100 overflow-y-auto">
                        @foreach($payments as $payment)
                            <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                <span class="font-semibold text-slate-600">{{ $payment->expense_date }}</span>
                                <span class="font-black text-slate-900">Rs. {{ number_format((float) $payment->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
