@php
    $total = (float) $sale->total;
    $paid = (float) $sale->paid_amount;
    $due = (float) $sale->due_amount;
@endphp

<div id="sale-payments-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/55 opacity-0 backdrop-blur-md transition-opacity" data-modal-overlay></div>

    <div class="modal-panel relative flex max-h-[90vh] w-full max-w-3xl translate-y-6 scale-95 transform flex-col overflow-hidden rounded-lg bg-white opacity-0 shadow-2xl ring-1 ring-slate-950/10 transition-all">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 bg-slate-50 px-5 py-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/20">
                        <x-lucide name="wallet-cards" class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Sale Payment</p>
                        <h3 class="truncate text-lg font-black text-slate-900">Invoice {{ $sale->invoice_no }}</h3>
                    </div>
                </div>
            </div>
            <button class="grid size-9 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900" type="button" data-modal-close aria-label="Close payments popup">
                <x-lucide name="x" class="size-4" />
            </button>
        </div>

        <div class="overflow-y-auto px-5 py-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">Total</p>
                    <p class="mt-1 text-xl font-black text-slate-900">{{ number_format($total, 2) }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[11px] font-black uppercase tracking-wide text-emerald-600">Paid</p>
                    <p class="mt-1 text-xl font-black text-emerald-700" data-live-paid data-base-paid="{{ $paid }}">{{ number_format($paid, 2) }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                    <p class="text-[11px] font-black uppercase tracking-wide text-rose-600">Due</p>
                    <p class="mt-1 text-xl font-black text-rose-700" data-live-due data-base-due="{{ $due }}">{{ number_format($due, 2) }}</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_1fr]">
                <section class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-3 py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="grid size-7 place-items-center rounded-lg bg-blue-50 text-blue-600">
                                <x-lucide name="credit-card" class="size-4" />
                            </span>
                            <h4 class="text-sm font-black text-slate-900">Payments</h4>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500">{{ $payments->count() }}</span>
                    </div>
                    <div class="max-h-32 overflow-y-auto p-3">
                        <div class="space-y-2">
                            @forelse($payments as $payment)
                                <div class="flex items-start justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-black text-slate-800">{{ str($payment->payment_method)->headline() }}</p>
                                        <p class="mt-0.5 truncate text-xs font-semibold text-slate-500">{{ $payment->paid_at }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-black text-slate-900">{{ number_format((float) $payment->amount, 2) }}</p>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center">
                                    <p class="text-sm font-bold text-slate-500">No payments recorded.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-3 py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="grid size-7 place-items-center rounded-lg bg-amber-50 text-amber-600">
                                <x-lucide name="receipt" class="size-4" />
                            </span>
                            <h4 class="text-sm font-black text-slate-900">Deductions</h4>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500">{{ $deductions->count() }}</span>
                    </div>
                    <div class="max-h-32 overflow-y-auto p-3">
                        <div class="space-y-2">
                            @forelse($deductions as $deduction)
                                <div class="flex items-start justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-800">{{ $deduction->note ?: 'Sale deduction' }}</p>
                                        <p class="mt-0.5 truncate text-xs font-semibold text-slate-500">{{ $deduction->deduction_date ?? $deduction->created_at }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-black text-slate-900">{{ number_format((float) $deduction->amount, 2) }}</p>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center">
                                    <p class="text-sm font-bold text-slate-500">No deductions recorded.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>

            <form method="POST" action="{{ route('backoffice.modules.payments.store', [$module, $sale->id]) }}" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3" data-sale-payments-form>
                @csrf
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Payment Amount</span>
                        <input name="payment_amount" type="number" step="0.01" min="0.01" class="form-control min-h-11 bg-white text-sm" placeholder="0.00" data-payment-amount>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Deduction Amount</span>
                        <input name="deduction_amount" type="number" step="0.01" min="0.01" class="form-control min-h-11 bg-white text-sm" placeholder="0.00" data-deduction-amount>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Payment Method</span>
                        <select name="payment_method" class="form-control min-h-11 bg-white text-sm">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank">Bank</option>
                            <option value="qr">QR</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Date & Time</span>
                        <input name="paid_at" type="datetime-local" class="form-control min-h-11 bg-white text-sm" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </label>
                    <label class="grid gap-1.5 md:col-span-2">
                        <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Description</span>
                        <textarea name="note" class="form-control min-h-20 bg-white text-sm" placeholder="Optional description for payment or deduction"></textarea>
                    </label>
                </div>

                <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary min-h-10 justify-center" data-modal-close>Close</button>
                    <button type="submit" class="btn-primary min-h-10 justify-center">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
