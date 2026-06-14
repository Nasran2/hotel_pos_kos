<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Order Invoice</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-100 p-6 text-slate-900">
    <div class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-2xl font-black">Online Order Invoice</h1>
                <p class="text-sm font-semibold text-slate-500">Reference: {{ $order->order_reference }}</p>
                <p class="text-sm font-semibold text-slate-500">Platform: {{ $order->source_name }}</p>
            </div>
            <div class="text-right text-sm font-semibold text-slate-600">
                <p>Date: {{ \Illuminate\Support\Carbon::parse($order->created_at)->format('Y-m-d H:i') }}</p>
                <p>Order status: {{ str($order->order_status)->replace('_', ' ')->headline() }}</p>
                <p>Payment status: {{ str($order->payment_status)->replace('_', ' ')->headline() }}</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <p class="text-xs font-black uppercase tracking-wide text-slate-500">Customer</p>
                <p class="mt-1 font-bold">{{ $order->customer_name }}</p>
                <p class="text-sm text-slate-500">{{ $order->customer_phone ?: '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-wide text-slate-500">Delivery Address</p>
                <p class="mt-1 text-sm font-semibold">{{ $order->delivery_address ?: '-' }}</p>
            </div>
        </div>

        <div class="mt-5 data-table-wrap">
            <table class="data-table min-w-full">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ number_format((float) $item->qty, 3) }}</td>
                            <td>{{ $currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>{{ $currency }} {{ number_format((float) $item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="space-y-1 text-sm font-semibold text-slate-600">
                <p>Subtotal: <span class="font-black text-slate-900">{{ $currency }} {{ number_format((float) $order->subtotal, 2) }}</span></p>
                <p>Discount: <span class="font-black text-slate-900">{{ $currency }} {{ number_format((float) $order->discount_amount, 2) }}</span></p>
                <p>Delivery: <span class="font-black text-slate-900">{{ $currency }} {{ number_format((float) $order->delivery_charge, 2) }}</span></p>
                @can('online_orders.view_commission_expense')
                    <p>Commission: <span class="font-black text-slate-900">{{ $currency }} {{ number_format((float) $order->commission_amount, 2) }}</span></p>
                @endcan
                <p>Total: <span class="font-black text-slate-900">{{ $currency }} {{ number_format((float) $order->total, 2) }}</span></p>
                <p>Paid: <span class="font-black text-slate-900">{{ $currency }} {{ number_format((float) $order->paid_amount, 2) }}</span></p>
                <p>Balance: <span class="font-black text-slate-900">{{ $currency }} {{ number_format((float) $order->balance_amount, 2) }}</span></p>
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-wide text-slate-500">Payments</p>
                <div class="mt-2 space-y-2">
                    @forelse($payments as $payment)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            <p class="font-black">{{ $currency }} {{ number_format((float) $payment->amount, 2) }} - {{ str($payment->payment_method)->replace('_', ' ')->headline() }}</p>
                            <p class="text-xs font-semibold text-slate-500">{{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('Y-m-d H:i') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No payments yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        window.print();
    </script>
</body>
</html>
