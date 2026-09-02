@php
$receiptPaperSize = $settings['invoice_paper_size'] ?? '80mm';
$receiptPrintableWidth = match (strtolower((string) $receiptPaperSize)) {
'58mm' => '48mm',
default => '72mm',
};

$currency = $settings['currency_symbol'] ?? 'Rs.';
$money = fn ($amount) => $currency.' '.number_format((float) $amount, 2);
$businessPhones = collect(preg_split('/[\r\n,|\/]+/', (string) ($settings['business_phone'] ?? '')))
->map(fn ($phone) => trim($phone))
->filter()
->implode(' / ');
$paymentLabel = $payments
->pluck('payment_method')
->filter()
->unique()
->map(fn ($method) => str((string) $method)->headline()->toString())
->implode(', ');
$receivedAmount = $payments->sum(fn ($payment) => (float) ($payment->received_amount ?? $payment->amount ?? 0));
$changeAmount = $payments->sum(fn ($payment) => (float) ($payment->change_amount ?? 0));
@endphp

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->invoice_no }} Receipt</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: Arial, sans-serif;
        }

        .toolbar {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 16px;
        }

        .toolbar button,
        .toolbar a {
            border: 1px solid #dbe4f0;
            border-radius: 10px;
            background: #ffffff;
            color: #1e293b;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            padding: 10px 16px;
            text-decoration: none;
        }

        .toolbar .primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #ffffff;
        }

        .receipt {
            width: var(--receipt-print-width, 72mm);
            max-width: var(--receipt-print-width, 72mm);
            margin: 0 auto;
            background: #ffffff;
            padding: 2mm;
            color: #000000;
            box-shadow: none;
            overflow: hidden;
        }

        .receipt h1 {
            margin: 0;
            font-size: 19px;
            font-weight: 900;
            text-align: center;
        }

        .business-line {
            margin: 2px 0 0;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            text-align: center;
        }

        .bill-title {
            display: inline-block;
            margin: 8px 0 11px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 10px;
            font-weight: 900;
            padding: 3px 10px;
        }

        .token-number {
            margin: 7px 0 0;
            color: #000000;
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-align: center;
        }

        .receipt-line {
            display: flex;
            justify-content: space-between;
            gap: 5px;
            font-size: 11px;
            line-height: 1.35;
        }

        .receipt-line span {
            flex: 0 0 auto;
        }

        .receipt-line strong {
            flex: 1;
            text-align: right;
            word-break: break-word;
            font-weight: 900;
        }

        .section-title {
            margin: 12px 0 7px;
            border-bottom: 1px dashed #cbd5e1;
            border-top: 1px dashed #cbd5e1;
            padding: 5px 0;
            color: #0f172a;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-align: center;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
        }

        th,
        td {
            border-bottom: 1px dashed #e2e8f0;
            padding: 2px 0;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: 11px;
        }

        th {
            font-weight: 900;
            text-align: left;
        }

        th:nth-child(1),
        td:nth-child(1) {
            width: 55%;
        }

        th:nth-child(2),
        td:nth-child(2) {
            width: 15%;
            text-align: right;
        }

        th:nth-child(3),
        td:nth-child(3) {
            width: 30%;
            text-align: right;
        }

        .text-right {
            text-align: right;
        }

        .muted {
            color: #475569;
            font-size: 10px;
        }

        .totals {
            display: grid;
            gap: 4px;
            margin-top: 10px;
        }

        .grand-total {
            margin-top: 6px;
            border-top: 1px solid #111827;
            padding-top: 7px;
            font-size: 14px;
        }

        .payment-summary {
            display: grid;
            gap: 4px;
            margin-top: 10px;
            border-radius: 10px;
            background: #f8fafc;
            padding: 8px;
        }

        .footer {
            margin: 14px 0 0;
            font-size: 11px;
            font-weight: 800;
            text-align: center;
        }

        .terms {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 9px;
            text-align: center;
        }

        @media print {
            @page {
                size: {
                        {
                        strtolower((string) $receiptPaperSize)==='58mm' ? '58mm 297mm': '80mm 297mm'
                    }
                }

                ;
                margin: 0;
            }

            html,
            body {
                width: {
                        {
                        strtolower((string) $receiptPaperSize)==='58mm' ? '58mm': '80mm'
                    }
                }

                ;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            .toolbar {
                display: none !important;
            }

            .receipt {
                width: var(--receipt-print-width, 72mm) !important;
                max-width: var(--receipt-print-width, 72mm) !important;
                margin-left: 2mm !important;
                margin-right: 0 !important;
                padding: 2mm !important;
                box-shadow: none !important;
                page-break-after: avoid;
                page-break-before: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button class="primary" type="button" onclick="window.print()">Print</button>
        <a href="{{ route('backoffice.modules.index', 'sales') }}">Back to Sales</a>
    </div>

    <section class="receipt" data-paper-size="{{ strtolower((string) $receiptPaperSize) }}" style="--receipt-print-width: {{ $receiptPrintableWidth }};">
        <div style="text-align: center;">
            @if(($settings['invoice_show_logo'] ?? true) && ! empty($settings['business_logo']))
            <img src="{{ asset('storage/'.$settings['business_logo']) }}" alt="{{ $settings['business_name'] }}" style="display: block; width: 18mm; height: 18mm; object-fit: contain; margin: 0 auto 4px;">
            @endif

            <h1>{{ $settings['business_name'] }}</h1>
            @if(! empty($settings['business_tagline']))
            <p class="business-line">{{ $settings['business_tagline'] }}</p>
            @endif
            <p class="token-number">TOKEN NO: {{ $sale->token_number ? str_pad((string) $sale->token_number, 2, '0', STR_PAD_LEFT) : '-' }}</p>
            @if(! empty($settings['business_address']))
            <p class="business-line">{{ $settings['business_address'] }}</p>
            @endif
            @if($businessPhones !== '')
            <p class="business-line">Phone: {{ $businessPhones }}</p>
            @endif
            <p class="bill-title">{{ strtolower((string) $sale->status) === 'paid' ? 'Paid bill' : 'Due bill' }}</p>
        </div>

        <div style="display: grid; gap: 3px;">
            @if($settings['invoice_show_table'] ?? true)
            <div class="receipt-line">
                <span>Table</span>
                <strong>{{ $sale->table_number ? 'Table '.$sale->table_number : '-' }}</strong>
            </div>
            @endif
            <div class="receipt-line">
                <span>Invoice</span>
                <strong>{{ $sale->invoice_no }}</strong>
            </div>
            <div class="receipt-line">
                <span>Payment</span>
                <strong>{{ $paymentLabel !== '' ? $paymentLabel : '-' }}</strong>
            </div>
            @if($settings['invoice_show_waiter'] ?? true)
            <div class="receipt-line">
                <span>Waiter</span>
                <strong>{{ $sale->waiter_name ?: 'No waiter' }}</strong>
            </div>
            @endif
            @if($settings['invoice_show_customer'] ?? true)
            <div class="receipt-line">
                <span>Customer</span>
                <strong>{{ $sale->customer_name ?: 'Walk-in Customer' }}</strong>
            </div>
            @endif
            <div class="receipt-line">
                <span>Date</span>
                <strong>{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d/m/Y, H:i:s') }}</strong>
            </div>
        </div>

        <p class="section-title">Order Items</p>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->product_name }}</strong>
                        <div class="muted">{{ $money($item->unit_price) }}</div>
                        @if((float) ($item->discount_amount ?? 0) > 0)
                        <div class="muted">Discount: {{ $money($item->discount_amount) }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                    <td class="text-right">{{ $money($item->line_total) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="receipt-line">
                <span>Subtotal</span>
                <strong>{{ $money($sale->subtotal) }}</strong>
            </div>
            <div class="receipt-line">
                <span>Service Charge</span>
                <strong>{{ $money($sale->service_charge) }}</strong>
            </div>
            <div class="receipt-line">
                <span>Discount</span>
                <strong>- {{ $money($sale->discount_amount) }}</strong>
            </div>
            <div class="receipt-line grand-total">
                <span>Total</span>
                <strong>{{ $money($sale->total) }}</strong>
            </div>
        </div>

        <div class="payment-summary">
            <div class="receipt-line">
                <span>Paid</span>
                <strong>{{ $money($sale->paid_amount) }}</strong>
            </div>
            <div class="receipt-line">
                <span>Received</span>
                <strong>{{ $money($receivedAmount) }}</strong>
            </div>
            <div class="receipt-line">
                <span>{{ (float) $sale->due_amount > 0 ? 'Due' : 'Change' }}</span>
                <strong>{{ $money((float) $sale->due_amount > 0 ? $sale->due_amount : $changeAmount) }}</strong>
            </div>
        </div>

        <p class="footer">{{ $settings['invoice_footer_text'] ?: 'Thank you. Payment received.' }}</p>
        @if(! empty($settings['invoice_terms']))
        <p class="terms">{{ $settings['invoice_terms'] }}</p>
        @endif
    </section>


</body>

</html>
