<x-layouts.app heading="Sales / POS" title="POS" :pos-fullscreen="true" :show-date-filter="false">
    @php
    $receiptPaperSize = $settings['invoice_paper_size'] ?? '80mm';
    $receiptWidth = match ($receiptPaperSize) {
    'A4' => '190mm',
    'A5' => '136mm',
    default => '80mm',
    };
    @endphp

    <style>
        :root {
            --pos-primary: #2563eb;
            --pos-primary-dark: #1d4ed8;
            --pos-success: #16a34a;
            --pos-danger: #ef4444;
            --pos-warning: #f59e0b;
            --pos-bg: #f6f8fc;
            --pos-card: #ffffff;
            --pos-border: #e5eaf3;
            --pos-text: #0f172a;
            --pos-muted: #64748b;
            --pos-shadow: 0 18px 45px rgba(15, 23, 42, .08);
        }

        .pos-neo {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, .08), transparent 35%),
                linear-gradient(180deg, #f8fbff, #f4f7fb);
            padding: 18px;
            border-radius: 28px;
        }

        .pos-card {
            background: rgba(255, 255, 255, .92);
            border: 1px solid var(--pos-border);
            border-radius: 24px;
            box-shadow: var(--pos-shadow);
            padding: 18px;
            backdrop-filter: blur(12px);
        }

        .pos-card h2 {
            color: var(--pos-text);
            letter-spacing: -.02em;
        }

        .form-control {
            width: 100%;
            border: 1px solid #dbe3ef;
            background: #fff;
            border-radius: 16px;
            padding: 13px 15px;
            font-weight: 700;
            color: #111827;
            outline: none;
            transition: .2s ease;
        }

        .form-control:focus {
            border-color: var(--pos-primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        }

        .btn-primary,
        .btn-secondary,
        .btn-success {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 16px;
            padding: 12px 18px;
            font-weight: 900;
            transition: .2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 12px 24px rgba(37, 99, 235, .22);
        }

        .btn-secondary {
            background: #f8fbff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .btn-success {
            background: linear-gradient(135deg, #16a34a, #0f9f46);
            color: white;
            box-shadow: 0 12px 24px rgba(22, 163, 74, .20);
        }

        .btn-primary:hover,
        .btn-success:hover,
        .btn-secondary:hover {
            transform: translateY(-1px);
        }

        .table-card {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            min-height: 86px;
            padding: 14px;
            text-align: left;
            background: #fff;
            border: 1px solid #e5eaf3;
            border-radius: 20px;
            transition: .22s ease;
        }

        .table-card:hover {
            transform: translateY(-2px);
            border-color: #bfdbfe;
            box-shadow: 0 14px 30px rgba(37, 99, 235, .10);
        }

        .table-card strong {
            display: block;
            font-size: 14px;
            font-weight: 900;
            color: #111827;
        }

        .table-card small {
            display: block;
            margin-top: 3px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
        }

        .table-avatar {
            color: #2563eb;
            background: #eff6ff !important;
        }

        .status-pill {
            margin-left: auto;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            font-style: normal;
            font-weight: 900;
        }

        .status-pill.active,
        .status-pill.available {
            color: #15803d;
            background: #dcfce7;
        }

        .status-pill.hold {
            color: #b45309;
            background: #fef3c7;
        }

        .status-pill.payment_pending {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .status-pill.busy {
            color: #dc2626;
            background: #fee2e2;
        }

        .takeaway-hold-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            width: 100%;
            border: 1px solid #dbeafe;
            border-radius: 14px;
            background: #f8fbff;
            padding: 10px 12px;
            text-align: left;
            transition: .2s ease;
        }

        .takeaway-hold-card:hover,
        .takeaway-hold-card.active {
            border-color: #93c5fd;
            background: #eff6ff;
            transform: translateY(-1px);
        }

        .takeaway-hold-card strong {
            display: block;
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
        }

        .takeaway-hold-card small {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
        }

        .pos-print-bill {
            display: none;
        }

        .receipt-line {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 11px;
            line-height: 1.35;
        }

        .receipt-line strong {
            max-width: 48mm;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .receipt-section-title {
            margin: 10px 0 4px;
            padding: 4px 0;
            border-top: 1px dashed #cbd5e1;
            border-bottom: 1px dashed #cbd5e1;
            text-align: center;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .menu-card {
            position: relative;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e5eaf3;
            border-radius: 22px;
            padding: 12px;
            transition: .22s ease;
        }

        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 35px rgba(15, 23, 42, .10);
            border-color: #bfdbfe;
        }

        .menu-card.stock-low {
            border-color: #facc15;
            box-shadow: 0 0 0 1px rgba(250, 204, 21, .55), 0 0 24px rgba(250, 204, 21, .42);
        }

        .menu-card.stock-low:hover {
            border-color: #eab308;
            box-shadow: 0 0 0 1px rgba(234, 179, 8, .70), 0 0 32px rgba(234, 179, 8, .50);
        }

        .menu-card.stock-out {
            border-color: #ef4444;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, .55), 0 0 24px rgba(239, 68, 68, .42);
        }

        .menu-card.stock-out:hover {
            border-color: #dc2626;
            box-shadow: 0 0 0 1px rgba(220, 38, 38, .70), 0 0 32px rgba(220, 38, 38, .50);
        }

        .menu-thumb {
            background:
                radial-gradient(circle at 30% 20%, #fef3c7, transparent 35%),
                linear-gradient(135deg, #dbeafe, #eff6ff);
            color: #1d4ed8;
            font-weight: 900;
        }

        .add-item-btn {
            position: absolute;
            right: 12px;
            bottom: 12px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            font-size: 22px;
            font-weight: 900;
            display: grid;
            place-items: center;
            box-shadow: 0 10px 20px rgba(37, 99, 235, .24);
            transition: .2s ease;
        }

        .add-item-btn:hover {
            transform: scale(1.08);
        }

        .pos-assignment-card {
            padding: 10px;
        }

        .pos-assignment-card h2 {
            font-size: 15px;
        }

        .pos-assignment-card p {
            margin-top: 2px;
            font-size: 11px;
        }

        .pos-assignment-card .btn-primary {
            min-height: 38px;
            border-radius: 14px;
            padding: 8px 14px;
            font-size: 13px;
        }

        .pos-assignment-card .form-control {
            margin-top: 10px;
            min-height: 40px;
            border-radius: 14px;
            padding: 9px 12px;
            font-size: 13px;
        }

        .pos-assignment-card .table-card {
            min-height: 58px;
            border-radius: 14px;
            gap: 9px;
            padding: 8px 10px;
        }

        .pos-assignment-card .table-avatar {
            width: 38px;
            height: 38px;
            font-size: 12px;
        }

        .pos-assignment-card .table-card strong {
            font-size: 13px;
        }

        .pos-assignment-card .table-card small {
            margin-top: 1px;
            font-size: 11px;
        }

        .pos-assignment-card .status-pill {
            padding: 4px 8px;
            font-size: 10px;
        }

        .menu-card[data-add-product-card] {
            cursor: pointer;
        }

        .menu-card[data-add-product-card]:focus-visible {
            border-color: var(--pos-primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
            outline: none;
        }

        .pos-menu-grid {
            gap: 10px;
        }

        .pos-neo .pos-menu-card {
            padding: 14px;
        }

        .pos-neo .pos-menu-card .menu-card {
            border-radius: 18px;
            padding: 10px;
        }

        .pos-neo .pos-menu-card .menu-card.stock-low {
            border-color: #facc15;
            box-shadow: 0 0 0 1px rgba(250, 204, 21, .55), 0 0 24px rgba(250, 204, 21, .42);
        }

        .pos-neo .pos-menu-card .menu-card.stock-low:hover {
            border-color: #eab308;
            box-shadow: 0 0 0 1px rgba(234, 179, 8, .70), 0 0 32px rgba(234, 179, 8, .50);
        }

        .pos-neo .pos-menu-card .menu-card.stock-out {
            border-color: #ef4444;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, .55), 0 0 24px rgba(239, 68, 68, .42);
        }

        .pos-neo .pos-menu-card .menu-card.stock-out:hover {
            border-color: #dc2626;
            box-shadow: 0 0 0 1px rgba(220, 38, 38, .70), 0 0 32px rgba(220, 38, 38, .50);
        }

        .pos-neo .pos-menu-card .menu-thumb {
            aspect-ratio: 16 / 9;
            border-radius: 16px;
            font-size: 2rem;
        }

        .pos-neo .pos-menu-card .menu-card h3 {
            margin-top: 9px;
            font-size: 13px;
            line-height: 1.15;
        }

        .pos-neo .pos-menu-card .menu-card p {
            margin-top: 2px;
            font-size: 13px;
            line-height: 1.15;
        }

        .pos-neo .pos-menu-card .add-item-btn {
            right: 10px;
            bottom: 10px;
            width: 32px;
            height: 32px;
            font-size: 20px;
        }

        .pos-toast {
            position: fixed;
            top: 88px;
            right: 18px;
            z-index: 90;
            display: none;
            width: min(360px, calc(100vw - 36px));
            align-items: flex-start;
            gap: 12px;
            border: 1px solid #f87171;
            border-radius: 14px;
            background: #ef4444;
            color: #fff;
            padding: 12px 14px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, .18);
        }

        .pos-toast.open {
            display: flex;
        }

        .pos-toast strong {
            display: block;
            font-size: 13px;
            font-weight: 900;
            color: #fff;
        }

        .pos-toast p {
            margin-top: 2px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
            color: #fff;
        }

        .pos-toast button {
            margin-left: auto;
            display: grid;
            width: 28px;
            height: 28px;
            flex: 0 0 28px;
            place-items: center;
            border-radius: 10px;
            background: rgba(255, 255, 255, .2);
            color: #fff;
            font-size: 18px;
            font-weight: 900;
            line-height: 1;
        }

        .pos-toast.is-success {
            border-color: #34d399;
            background: #10b981;
        }

        .pos-checkout-panel {
            border: 1px solid #dbeafe;
        }

        .cart-action {
            border: 1px solid #e5eaf3;
            background: #f8fbff;
            color: #334155;
            border-radius: 12px;
            padding: 6px 4px;
            font-weight: 700;
            font-size: 0.65rem;
            transition: .2s ease;
        }

        .cart-action:hover {
            background: #eff6ff;
            color: #2563eb;
            transform: translateY(-1px);
        }

        .pos-field-add-circle {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            font-size: 20px;
            font-weight: 900;
            box-shadow: 0 8px 16px rgba(37, 99, 235, .18);
        }

        .pos-suggestions {
            position: absolute;
            z-index: 20;
            left: 0;
            right: 0;
            top: calc(100% + 8px);
            background: white;
            border: 1px solid #e5eaf3;
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .14);
            overflow: hidden;
        }

        .modal {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: none;
            place-items: center;
            padding: 18px;
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(8px);
        }

        .modal.open,
        .modal.active,
        .modal.show {
            display: grid;
        }

        .modal-panel {
            position: relative;
            width: 100%;
            max-width: 420px;
            border-radius: 18px;
            background: white;
            padding: 16px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, .2);
            animation: posModal .22s ease;
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #334155;
            font-size: 18px;
            font-weight: 900;
            transition: .2s ease;
        }

        .modal-close:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .payment-method {
            min-height: 56px;
            border-radius: 16px;
            border: 1px solid #e5eaf3;
            background: #f8fbff;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.4rem;
            transition: .2s ease;
        }

        .payment-method:hover,
        .payment-method.active {
            background: #eff6ff;
            border-color: #2563eb;
            color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, .1);
        }

        .pos-register-panel {
            border-radius: 30px !important;
            border: 1px solid rgba(255, 255, 255, .5);
            box-shadow: 0 35px 90px rgba(15, 23, 42, .28);
        }

        .pos-close-register {
            border-radius: 18px !important;
            background: linear-gradient(135deg, #0f172a, #334155) !important;
        }

        [data-bill-discount-type] {
            cursor: pointer;
            appearance: none;
            background-image:
                linear-gradient(45deg, transparent 50%, #64748b 50%),
                linear-gradient(135deg, #64748b 50%, transparent 50%);
            background-position:
                calc(100% - 20px) calc(50% - 3px),
                calc(100% - 14px) calc(50% - 3px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            padding-right: 42px;
        }



        @keyframes posModal {
            from {
                opacity: 0;
                transform: translateY(16px) scale(.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .animate-pop {
            animation: posModal .25s ease;
        }

        @media (max-width: 1280px) {
            .pos-checkout-panel {
                position: static !important;
            }
        }

        @media (max-width: 768px) {
            .pos-neo {
                display: flex !important;
                flex-direction: column;
                gap: 10px;
                padding: 8px;
                padding-bottom: 82px;
                border-radius: 16px;
            }

            .pos-neo>section {
                display: contents;
            }

            .pos-card {
                padding: 10px;
                border-radius: 16px;
            }

            .pos-assignment-card {
                order: 1;
            }

            .pos-checkout-panel {
                order: 2;
                position: static !important;
            }

            .pos-menu-card {
                order: 3;
            }

            .pos-assignment-card .btn-primary {
                min-height: 36px;
            }

            .pos-table-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .table-card {
                position: relative;
                display: block;
                min-height: 64px;
                padding: 10px;
            }

            .pos-assignment-card .table-avatar {
                display: none;
            }

            .table-card .min-w-0 {
                min-width: 0;
            }

            .table-card strong,
            .table-card small {
                display: block;
                overflow: visible;
                text-overflow: clip;
                white-space: normal;
            }

            .table-card .status-pill {
                position: absolute;
                bottom: 10px;
                right: 8px;
            }

            .pos-table-grid .status-pill.available {
                display: none;
            }

            .table-card small {
                padding-right: 0;
            }

            .pos-menu-card .flex {
                align-items: stretch;
            }

            .pos-menu-card select.form-control {
                max-width: none;
            }

            .pos-menu-grid {
                gap: 8px;
            }

            .menu-card {
                border-radius: 16px;
                padding: 8px;
                padding-bottom: 40px;
            }

            .menu-thumb {
                aspect-ratio: 3 / 2;
                border-radius: 14px;
                font-size: 2rem;
            }

            .menu-card h3 {
                margin-top: 8px;
                min-height: 32px;
                padding-right: 0;
                line-height: 1.15;
            }

            .menu-card p {
                padding-right: 36px;
                line-height: 1.15;
            }

            .pos-checkout-panel table {
                min-width: 0;
                table-layout: fixed;
            }

            .pos-checkout-panel table th,
            .pos-checkout-panel table td {
                padding: 9px 6px;
                font-size: 11px;
            }

            .pos-checkout-panel table th:nth-child(1),
            .pos-checkout-panel table td:nth-child(1) {
                width: 38%;
            }

            .pos-checkout-panel table th:nth-child(2),
            .pos-checkout-panel table td:nth-child(2) {
                width: 24%;
            }

            .pos-checkout-panel table th:nth-child(3),
            .pos-checkout-panel table td:nth-child(3) {
                display: none;
            }

            .pos-checkout-panel table th:nth-child(4),
            .pos-checkout-panel table td:nth-child(4) {
                width: 26%;
            }

            .pos-checkout-panel table th:nth-child(5),
            .pos-checkout-panel table td:nth-child(5) {
                width: 12%;
            }

            .pos-checkout-panel textarea {
                min-height: 70px;
            }

            .pos-field-add-circle {
                width: 38px;
                height: 38px;
                flex-basis: 38px;
            }

            .pos-close-register-form {
                position: static !important;
                width: calc(100% - 28px);
                margin: 10px 14px 18px;
            }

            .pos-close-register {
                width: 100%;
                padding: 9px 12px !important;
                font-size: 12px !important;
            }

            .modal-panel {
                max-width: 100%;
                border-radius: 16px;
                padding: 14px;
            }

            .payment-method {
                min-height: 50px;
            }
        }

        @media (max-width: 480px) {

            .btn-primary,
            .btn-secondary,
            .btn-success {
                width: 100%;
                justify-content: center;
            }

            .cart-action {
                padding: 11px 6px;
                font-size: 12px;
            }
        }

        @media print {
            body * {
                visibility: hidden !important;
            }

            .pos-print-bill,
            .pos-print-bill * {
                visibility: visible !important;
            }

            .pos-print-bill {
                display: block !important;
                position: absolute;
                inset: 0 auto auto 0;
                width: var(--receipt-width, 80mm);
                min-height: 100vh;
                padding: 6mm 5mm;
                color: #111827;
                background: #fff;
                font-family: 'Plus Jakarta Sans', Arial, sans-serif;
                font-size: 11px;
            }

            .pos-print-bill h1 {
                margin: 0;
                font-size: 17px;
                font-weight: 900;
                text-align: center;
            }

            .pos-print-bill table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10.5px;
            }

            .pos-print-bill th,
            .pos-print-bill td {
                padding: 5px 0;
                border-bottom: 1px dashed #cbd5e1;
                vertical-align: top;
            }

            .pos-print-bill .text-right {
                text-align: right;
            }

            .pos-print-bill[data-paper-size="A4"],
            .pos-print-bill[data-paper-size="A5"] {
                padding: 12mm;
                font-size: 12px;
            }

            .pos-print-bill[data-paper-size="A4"] h1,
            .pos-print-bill[data-paper-size="A5"] h1 {
                font-size: 22px;
            }
        }
    </style>

    @unless($register)
    <div class="pos-register-overlay fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4 backdrop-blur-md">
        <form method="POST" action="{{ route('pos.register.open') }}"
            class="pos-register-panel w-full max-w-md animate-pop bg-white p-6">
            @csrf

            <div class="mb-5 flex items-center gap-4">
                <div
                    class="grid size-14 place-items-center rounded-2xl bg-blue-600 text-2xl font-black text-white shadow-lg shadow-blue-600/20">
                    Rs
                </div>
                <div>
                    <h2 class="text-2xl font-black text-slate-950">Open Register</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Enter opening drawer balance before taking
                        orders.</p>
                </div>
            </div>

            <label class="mt-5 grid gap-2 text-sm font-black text-slate-700">
                Opening Cash
                <input class="form-control" type="number" step="0.01" name="opening_cash" required autofocus
                    placeholder="0.00" value="0">
            </label>

            <label class="mt-4 grid gap-2 text-sm font-black text-slate-700">
                Note
                <textarea class="form-control min-h-24" name="opening_note"
                    placeholder="Opening note optional..."></textarea>
            </label>

            @can('pos.open_register')
            <button class="btn-primary mt-5 w-full justify-center">Open Register</button>
            @endcan

            @can('dashboard.view')
            <a href="{{ route('dashboard') }}" class="btn-secondary mt-3 w-full justify-center">Go to Dashboard</a>
            @endcan
        </form>
    </div>
    @endunless

    <div class="pos-toast" data-pos-toast role="alert" aria-live="assertive">
        <div class="min-w-0">
            <strong data-pos-toast-title>Action needed</strong>
            <p data-pos-toast-message>Select a table before adding items to the cart.</p>
        </div>
        <button type="button" data-pos-toast-close aria-label="Close notification">&times;</button>
    </div>

    <div id="pos-root" class="pos-neo grid gap-4 xl:grid-cols-[1fr_27rem]"
        data-currency="{{ $settings['currency_symbol'] }}" data-pay-url="{{ route('pos.pay') }}"
        data-hold-url="{{ route('pos.hold') }}" data-print-url="{{ route('pos.print') }}"
        data-cancel-hold-url="{{ url('/pos/hold') }}"
        data-register-close-summary-url="{{ route('pos.register.close-summary') }}"
        data-service-charge-enabled="{{ $settings['pos_enable_service_charge'] ? '1' : '0' }}"
        data-service-charge-rate="{{ $settings['pos_service_charge_percentage'] }}"
        data-qr-code-url="{{ $settings['pos_qr_code_image'] ? asset('storage/'.$settings['pos_qr_code_image']) : '' }}"
        data-transfer-url="{{ route('pos.transfer') }}" data-customer-create-url="{{ route('pos.customers.store') }}"
        data-waiter-create-url="{{ route('pos.waiters.store') }}"
        data-expense-store-url="{{ route('pos.expense.store') }}"
        data-expense-delete-base-url="{{ url('/pos/expense') }}"
        data-expense-category-store-url="{{ route('pos.expense-categories.store') }}"
        data-customer-due-payment-url="{{ route('pos.customer-due-payment.store') }}"
        data-supplier-payment-url="{{ route('pos.supplier-payment.store') }}">

        <script type="application/json" data-due-customers-json>
            @json($dueCustomers)
        </script>
        <script type="application/json" data-due-suppliers-json>
            @json($dueSuppliers)
        </script>

        <section class="space-y-4">
            <div class="pos-card pos-assignment-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-black sm:text-lg">Table & Waiter Assignment</h2>
                        <p class="mt-1 text-xs font-bold text-slate-500">Select table, resume hold orders, and manage
                            dining flow.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @can('pos.payment')
                        <button class="btn-secondary" data-modal-open="customer-due-payment-modal" type="button">Receive Due Payment</button>
                        @endcan

                        @can('purchases.edit')
                        <button class="btn-secondary" data-modal-open="supplier-payment-modal" type="button">Pay Supplier</button>
                        @endcan

                        @can('pos.close_register')
                        <button class="btn-secondary" data-modal-open="expense-modal" type="button">+ Add Expense</button>
                        @endcan

                        @if($register)
                        <a href="{{ route('online-orders.index') }}" class="btn-secondary">Online Orders</a>
                        @else
                        <button type="button" class="btn-secondary" disabled title="Open register first">Online Orders</button>
                        @endif

                        @can('tables.create')
                        <a href="{{ route('backoffice.modules.create', 'tables') }}" class="btn-primary">+ New Table</a>
                        @endcan

                        <button class="btn-secondary px-4 py-2 text-[11px]" data-modal-open="shortcut-modal" type="button">
                            Keyboard Shortcuts
                        </button>
                    </div>
                </div>

                <input class="form-control" data-pos-search placeholder="Search table">

                <button class="takeaway-hold-card active mt-3" type="button" data-takeaway-start>
                    <span class="min-w-0">
                        <strong>Takeaway Order</strong>
                        <small>No table needed. Add items and hold or pay directly.</small>
                    </span>

                    <em class="status-pill active">Ready</em>
                </button>

                <div class="pos-table-grid mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-5">
                    @foreach($tables as $table)
                    @php
                    $activeOrder = $holds[$table->id] ?? null;
                    $status = $activeOrder?->status ?? $table->status;
                    $statusLabel = $status === 'payment_pending' ? 'Waiting Payment' : str($status)->replace('_', ' ')->headline();
                    @endphp

                    <button class="table-card" data-table-id="{{ $table->id }}"
                        data-table-name="Table {{ $table->number }}" data-held="{{ $activeOrder ? '1' : '0' }}"
                        data-resume-url="{{ route('pos.resume', $table->id) }}">
                        <span
                            class="table-avatar grid size-12 place-items-center rounded-full bg-slate-100 text-sm font-black">TB</span>

                        <span class="min-w-0">
                            <strong>Table {{ $table->number }}</strong>
                            <small>
                                {{ $activeOrder
                        ? $statusLabel . ' - ' . $settings['currency_symbol'] . ' ' . number_format((float) $activeOrder->total, 2)
                        : $statusLabel }}
                            </small>
                        </span>

                        <em class="status-pill {{ $status }}">{{ $statusLabel }}</em>
                    </button>
                    @endforeach
                </div>

                @if($takeawayHolds->isNotEmpty())
                <div class="mt-3 rounded-2xl border border-blue-100 bg-blue-50/60 p-3">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-xs font-black uppercase tracking-wide text-blue-700">Takeaway Holds</span>
                        <span class="rounded-full bg-white px-2 py-1 text-[11px] font-black text-slate-500">{{ $takeawayHolds->count() }}</span>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($takeawayHolds as $hold)
                        @php
                        $statusLabel = $hold->status === 'payment_pending' ? 'Waiting Payment' : str($hold->status)->headline();
                        @endphp

                        <button class="takeaway-hold-card" type="button" data-takeaway-hold
                            data-hold-id="{{ $hold->id }}" data-resume-url="{{ route('pos.resume-held-order', $hold->id) }}">
                            <span class="min-w-0">
                                <strong>Takeaway #{{ $hold->id }}</strong>
                                <small>{{ $hold->customer_name ?? 'Walk-in Customer' }} - {{ $settings['currency_symbol'] }} {{ number_format((float) $hold->total, 2) }}</small>
                            </span>

                            <em class="status-pill {{ $hold->status }}">{{ $statusLabel }}</em>
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <div class="pos-card pos-menu-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-black sm:text-lg">Menu</h2>
                        <p class="mt-1 text-xs font-bold text-slate-500">Search and add hotel items quickly.</p>
                    </div>

                    <select class="form-control max-w-56" data-category-filter>
                        <option value="all">All Categories</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <input class="form-control mt-4" data-menu-search
                    placeholder="Search items (e.g. Kothu, Rice, Curry...)">

                <div class="pos-menu-grid mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-5">
                    @foreach($products as $product)
                    @php
                    $isStockTracked = (bool) $product->maintain_stock;
                    $isOutOfStock = $isStockTracked && (float) $product->stock_quantity <= 0;
                        $isLowStock=$isStockTracked && ! $isOutOfStock && (float) $product->alert_quantity > (float) $product->stock_quantity;
                        @endphp
                        <article class="menu-card {{ $isOutOfStock ? 'stock-out' : ($isLowStock ? 'stock-low' : '') }}" data-category="{{ $product->category_id }}"
                            data-name="{{ str($product->name)->lower() }}" data-product='@json($product)'
                            @can('pos.add_item') data-add-product-card role="button" tabindex="0" @endcan>
                            <div class="menu-thumb grid aspect-[4/3] place-items-center rounded-2xl text-4xl">
                                {{ mb_substr($product->name, 0, 1) }}
                            </div>

                            <h3 class="mt-3 pr-9 text-sm font-black text-slate-950">{{ $product->name }}</h3>
                            <p class="mt-1 text-sm font-black text-slate-500">
                                {{ $settings['currency_symbol'] }} {{ number_format((float) $product->selling_price, 2) }}
                            </p>

                            @can('pos.add_item')
                            <button class="add-item-btn" data-add-product type="button">+</button>
                            @endcan
                        </article>
                        @endforeach
                </div>
            </div>
        </section>

        <aside class="pos-card pos-checkout-panel sticky top-18 h-fit">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950" data-selected-table>Takeaway Order</h2>
                    <span class="status-pill active mt-2 inline-block" data-order-mode>No table</span>
                </div>

                <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-black text-slate-500"
                    data-selected-waiter>No waiter</span>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-3">
                @can('pos.hold_order')
                <button class="cart-action" data-hold type="button">Hold</button>
                @endcan

                @can('pos.transfer_table')
                <button class="cart-action" data-modal-open="transfer-modal" type="button">Transfer</button>
                @endcan

                <button class="cart-action" data-clear type="button">Clear</button>
            </div>

            <div class="mt-5 grid gap-2 text-sm font-black text-slate-700">
                <span>Waiter</span>
                <div class="flex items-start gap-3">
                    <div class="relative min-w-0 flex-1" data-searchable-wrap="waiter">
                        <input class="form-control w-full" type="text" data-waiter-search-input
                            placeholder="Search waiter..." autocomplete="off" spellcheck="false">
                        <input type="hidden" data-waiter-id>
                        <div class="pos-suggestions hidden" data-waiter-suggestions></div>

                        <div class="hidden" data-waiter-options>
                            @foreach($waiters as $waiter)
                            <span data-id="{{ $waiter->id }}">{{ $waiter->name }}</span>
                            @endforeach
                        </div>
                    </div>

                    @can('waiters.create')
                    <button type="button" class="pos-field-add pos-field-add-circle"
                        data-modal-open="waiter-create-modal" aria-label="Add waiter">+</button>
                    @endcan
                </div>
            </div>

            <div class="mt-4 grid gap-2 text-sm font-black text-slate-700">
                <span>Customer</span>
                <div class="flex items-start gap-3">
                    <div class="relative min-w-0 flex-1" data-searchable-wrap="customer">
                        <input class="form-control w-full" type="text" data-customer-search-input
                            placeholder="Search customer..." autocomplete="off" spellcheck="false">
                        <input type="hidden" data-customer-id>
                        <div class="pos-suggestions hidden" data-customer-suggestions></div>

                        <div class="hidden" data-customer-options>
                            @foreach($customers as $customer)
                            <span data-id="{{ $customer->id }}"
                                data-walk-in="{{ $customer->is_walk_in ? 1 : 0 }}">{{ $customer->name }}</span>
                            @endforeach
                        </div>
                    </div>

                    @can('customers.create')
                    <button type="button" class="pos-field-add pos-field-add-circle"
                        data-modal-open="customer-create-modal" aria-label="Add customer">+</button>
                    @endcan
                </div>
            </div>

            <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-100 bg-white">
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3">Order Items</th>
                            <th class="px-3 py-3">Qty</th>
                            <th class="px-3 py-3">Price</th>
                            <th class="px-3 py-3">Total</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>

                    <tbody data-cart-items class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <label class="mt-4 grid gap-2 text-sm font-black text-slate-700">
                Add Note
                <textarea class="form-control min-h-24" data-note placeholder="Enter note here..."></textarea>
            </label>

            @can('pos.bill_discount')
            <div class="mt-4 grid gap-2 text-sm font-black text-slate-700">
                <span>Bill Discount</span>

                <div class="grid grid-cols-[150px_1fr] gap-3 max-sm:grid-cols-1">
                    <select class="form-control" data-bill-discount-type>
                        <option value="fixed" selected>Fix Amount</option>
                        <option value="percent">Percentage</option>
                    </select>

                    <input class="form-control" type="number" step="0.01" min="0" data-bill-discount value="0"
                        placeholder="Discount value">
                </div>
            </div>
            @endcan

            <div class="mt-5 space-y-2 rounded-2xl bg-slate-50 p-4 text-sm">
                <div class="flex justify-between">
                    <span class="font-bold text-slate-500">Subtotal</span>
                    <strong data-subtotal>Rs. 0.00</strong>
                </div>

                <div class="flex justify-between">
                    <span class="font-bold text-slate-500">Service Charge</span>
                    <strong data-service>Rs. 0.00</strong>
                </div>

                <div class="flex justify-between">
                    <span class="font-bold text-slate-500">Discount</span>
                    <strong data-discount>Rs. 0.00</strong>
                </div>

                <div class="mt-3 flex justify-between border-t border-slate-200 pt-3 text-xl font-black">
                    <span>Total</span>
                    <strong class="text-blue-600" data-total>Rs. 0.00</strong>
                </div>
            </div>

            <div class="mt-4">
                <div class="grid gap-2" data-payment-action-wrap>
                    @can('pos.hold_order')
                    <div class="relative hidden w-full" data-cancel-hold-wrap>
                        <button class="btn-secondary w-full justify-center border-red-200 bg-red-50 text-red-600 hover:border-red-300 hover:bg-red-100" data-cancel-hold type="button">Cancel Hold</button>
                        
                        <div class="absolute bottom-full left-0 mb-2 hidden w-full animate-pop rounded-xl border border-red-100 bg-white p-3 shadow-xl" data-cancel-confirm-popup>
                            <p class="text-center text-sm font-black text-slate-700">Cancel this held order?</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="btn-secondary flex-1 px-2 py-1.5 text-xs" data-cancel-no>No</button>
                                <button type="button" class="btn-primary flex-1 bg-red-600 px-2 py-1.5 text-xs hover:bg-red-700" data-cancel-yes>Yes</button>
                            </div>
                        </div>
                    </div>
                    @endcan

                    @can('pos.payment')
                    <button class="btn-success w-full justify-center" data-modal-open="payment-modal" type="button">Proceed to
                        Payment</button>
                    @endcan
                </div>
            </div>
        </aside>
    </div>

    <section class="pos-print-bill" data-print-bill data-paper-size="{{ $receiptPaperSize }}"
        style="--receipt-width: {{ $receiptWidth }};">
        <div style="text-align: center;">
            @if(($settings['invoice_show_logo'] ?? true) && ! empty($settings['business_logo']))
            <img src="{{ asset('storage/'.$settings['business_logo']) }}" alt="{{ $settings['business_name'] }}"
                style="display: block; width: 18mm; height: 18mm; object-fit: contain; margin: 0 auto 4px;">
            @endif

            <h1>{{ $settings['business_name'] }}</h1>
            @if(! empty($settings['business_tagline']))
            <p style="margin: 2px 0 0; font-size: 10px; font-weight: 700; color: #475569;">{{ $settings['business_tagline'] }}</p>
            @endif
            @if(! empty($settings['business_address']))
            <p style="margin: 2px 0 0; font-size: 9.5px; color: #64748b;">{{ $settings['business_address'] }}</p>
            @endif
            @if(! empty($settings['business_phone']))
            @php
            $businessPhones = collect(preg_split('/[\r\n,|\/]+/', $settings['business_phone']))
            ->map(fn ($phone) => trim($phone))
            ->filter()
            ->implode(' / ');
            @endphp
            @if($businessPhones !== '')
            <p style="margin: 2px 0 0; font-size: 9.5px; font-weight: 700; color: #64748b;">Phone: {{ $businessPhones }}</p>
            @endif
            @endif
            <p style="display: inline-block; margin: 7px 0 10px; border-radius: 999px; background: #eff6ff; padding: 3px 10px; color: #1d4ed8; font-size: 10px; font-weight: 900;"
                data-print-bill-title>Pre-payment bill</p>
        </div>

        <div style="display: grid; gap: 3px;">
            @if($settings['invoice_show_table'] ?? true)
            <div class="receipt-line">
                <span>Table</span>
                <strong data-print-table>-</strong>
            </div>
            @endif
            <div class="receipt-line">
                <span>Invoice</span>
                <strong data-print-invoice>-</strong>
            </div>
            <div class="receipt-line">
                <span>Payment</span>
                <strong data-print-payment>-</strong>
            </div>
            @if($settings['invoice_show_waiter'] ?? true)
            <div class="receipt-line">
                <span>Waiter</span>
                <strong data-print-waiter>-</strong>
            </div>
            @endif
            @if($settings['invoice_show_customer'] ?? true)
            <div class="receipt-line">
                <span>Customer</span>
                <strong data-print-customer>-</strong>
            </div>
            @endif
            <div class="receipt-line">
                <span>Date</span>
                <strong data-print-date>-</strong>
            </div>
        </div>

        <p class="receipt-section-title">Order Items</p>

        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody data-print-items></tbody>
        </table>

        <div style="margin-top: 10px; display: grid; gap: 4px;">
            <div class="receipt-line">
                <span>Subtotal</span>
                <strong data-print-subtotal>Rs. 0.00</strong>
            </div>
            <div class="receipt-line">
                <span>Service Charge</span>
                <strong data-print-service>Rs. 0.00</strong>
            </div>
            <div class="receipt-line">
                <span>Discount</span>
                <strong data-print-discount>Rs. 0.00</strong>
            </div>
            <div class="receipt-line" style="margin-top: 6px; border-top: 1px solid #111827; padding-top: 7px; font-size: 13px;">
                <span>Total</span>
                <strong data-print-total>Rs. 0.00</strong>
            </div>
        </div>

        <div style="margin-top: 10px; display: grid; gap: 4px; border-radius: 10px; background: #f8fafc; padding: 8px;"
            data-print-payment-summary hidden>
            <div class="receipt-line">
                <span>Paid</span>
                <strong data-print-paid>Rs. 0.00</strong>
            </div>
            <div class="receipt-line">
                <span>Received</span>
                <strong data-print-received>Rs. 0.00</strong>
            </div>
            <div class="receipt-line">
                <span data-print-balance-label>Change</span>
                <strong data-print-change>Rs. 0.00</strong>
            </div>
        </div>

        <p style="margin: 14px 0 0; text-align: center; font-size: 11px; font-weight: 800;" data-print-footer>
            {{ $settings['invoice_footer_text'] ?: 'Thank you. Please keep this bill for payment.' }}
        </p>
        @if(! empty($settings['invoice_terms']))
        <p style="margin: 6px 0 0; text-align: center; font-size: 9px; color: #64748b;">{{ $settings['invoice_terms'] }}</p>
        @endif
    </section>

    <div id="payment-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <div class="mb-4 flex items-center gap-4">
                <div class="grid size-13 place-items-center rounded-2xl bg-green-100 text-xl font-black text-green-700">
                    ₹</div>
                <div>
                    <h2 class="text-2xl font-black text-slate-950">Payment</h2>
                    <p class="text-sm font-semibold text-slate-500" data-payment-help>Print the bill first, then take payment when the
                        waiter returns.</p>
                </div>
            </div>

            <div data-payment-print-action>
                @can('pos.print_bill')
                <button class="btn-secondary w-full justify-center" data-print type="button">Print Bill</button>
                @endcan
            </div>

            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-black text-slate-700">Get Payment</span>
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500">Default: Cash</span>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3">
                @foreach(['cash' => 'Cash', 'card' => 'Card', 'qr' => 'QR', 'due' => 'Due'] as $method => $label)
                @can("pos.$method" . '_payment')
                <button class="payment-method {{ $method === 'cash' ? 'active' : '' }}"
                    data-payment-method="{{ $method }}" type="button">{{ $label }}</button>
                @elsecan('pos.payment')
                @if($method === 'due')
                <button class="payment-method" data-payment-method="{{ $method }}" type="button">Due</button>
                @endif
                @endcan
                @endforeach
            </div>

            <div class="mt-3 hidden rounded-2xl border border-blue-100 bg-blue-50 p-3" data-qr-payment-action>
                @if($settings['pos_qr_code_image'])
                <button class="btn-secondary w-full justify-center border-blue-200 bg-white text-blue-700 hover:bg-blue-100" data-modal-open="qr-code-modal" type="button">
                    Show QR Code
                </button>
                @else
                <p class="text-center text-xs font-black text-blue-700">Upload a QR code image in POS Settings first.</p>
                @endif
            </div>

            <label class="mt-4 grid gap-2 text-sm font-black text-slate-700">
                Received Cash
                <input class="form-control" type="number" step="0.01" data-received placeholder="0.00">
            </label>

            <p class="mt-3 rounded-2xl bg-blue-50 px-4 py-3 text-sm font-black text-slate-600">
                <span data-balance-label>Change:</span>
                <span class="text-blue-700" data-change>{{ $settings['currency_symbol'] }} 0.00</span>
            </p>

            <button class="btn-success mt-5 w-full justify-center" data-submit-payment type="button">Get Payment</button>
        </div>
    </div>

    <div id="qr-code-modal" class="modal">
        <div class="modal-panel text-center">
            <button class="modal-close" data-modal-close type="button">×</button>

            <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-blue-50 text-blue-700">
                <x-lucide name="qr-code" class="size-6" />
            </div>
            <h2 class="mt-3 text-xl font-black text-slate-950">Scan QR Code</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Ask the customer to scan this code and complete the payment.</p>

            @if($settings['pos_qr_code_image'])
            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
                <img class="mx-auto max-h-72 w-full max-w-72 rounded-xl object-contain" src="{{ asset('storage/'.$settings['pos_qr_code_image']) }}" alt="Payment QR code">
            </div>
            @else
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">
                No QR code image uploaded.
            </div>
            @endif

            <button class="btn-primary mt-5 w-full justify-center" data-modal-close type="button">Done</button>
        </div>
    </div>

    <div id="shortcut-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <div class="flex items-start gap-3 pr-10">
                <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-700">
                    <x-lucide name="keyboard" class="size-6" />
                </div>
                <div>
                    <h2 class="text-2xl font-black text-slate-950">Keyboard Shortcuts</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Fast actions available on the POS screen.</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3">
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <span class="text-sm font-bold text-slate-700">Search items</span>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-blue-700 shadow-sm">/</span>
                </div>

                <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <span class="text-sm font-bold text-slate-700">Hold order</span>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-blue-700 shadow-sm">Ctrl + H</span>
                </div>

                <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <span class="text-sm font-bold text-slate-700">Open or submit payment</span>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-blue-700 shadow-sm">Ctrl + Enter</span>
                </div>

                <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <span class="text-sm font-bold text-slate-700">Close popup</span>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-blue-700 shadow-sm">Esc</span>
                </div>
            </div>

            <button class="btn-primary mt-5 w-full justify-center" data-modal-close type="button">Done</button>
        </div>
    </div>

    <div id="transfer-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <h2 class="text-2xl font-black text-slate-950">Transfer Table</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Move current order to another available table.</p>

            <select class="form-control mt-5" data-transfer-table>
                @foreach($tables->where('status', 'available') as $table)
                <option value="{{ $table->id }}">Table {{ $table->number }}</option>
                @endforeach
            </select>

            <button class="btn-primary mt-5 w-full justify-center" data-submit-transfer type="button">Transfer</button>
        </div>
    </div>

    @can('pos.payment')
    <div id="customer-due-payment-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <h2 class="text-2xl font-black text-slate-950">Receive Due Payment</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Search a due customer and record the amount received today.</p>

            <div class="mt-4 grid gap-3" data-due-payment-modal="customer">
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Customer
                    <input class="form-control" type="search" data-due-search placeholder="Search by name or phone">
                </label>

                <div class="max-h-40 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2" data-due-results></div>

                <input type="hidden" data-due-selected-id>

                <div class="grid gap-2 rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm font-black text-slate-700">
                    <div class="flex items-center justify-between gap-3">
                        <span>Total Due</span>
                        <strong class="text-blue-700" data-due-total>Rs. 0.00</strong>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span>Remaining Due</span>
                        <strong class="text-slate-900" data-due-remaining>Rs. 0.00</strong>
                    </div>
                    <div class="flex items-center justify-between gap-3 text-xs text-slate-500">
                        <span>Date</span>
                        <span data-due-date>{{ now()->toDateString() }}</span>
                    </div>
                </div>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Amount Received
                    <input class="form-control" type="number" step="0.01" min="0" data-due-amount placeholder="0.00">
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Payment Method
                    <select class="form-control" data-due-payment-method>
                        <option value="cash">Cash Drawer</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="card">Card Payment</option>
                        <option value="qr">QR Payment</option>
                    </select>
                </label>

                <label class="hidden gap-2 text-sm font-black text-slate-700" data-due-bank-wrap>
                    Bank Account
                    <select class="form-control" data-due-bank-account>
                        @foreach($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected($account->is_default)>
                            {{ $account->name }}{{ $account->account_no ? ' - '.$account->account_no : '' }}{{ $account->is_default ? ' (Default)' : '' }}
                        </option>
                        @endforeach
                    </select>
                </label>

                <div class="flex gap-3">
                    <button class="btn-secondary flex-1" data-due-cancel type="button">Cancel</button>
                    <button class="btn-primary flex-1" data-due-save type="button">Save Payment</button>
                </div>
            </div>
        </div>
    </div>
    @endcan

    @can('purchases.edit')
    <div id="supplier-payment-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <h2 class="text-2xl font-black text-slate-950">Pay Supplier</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Search a supplier and record the amount paid today.</p>

            <div class="mt-4 grid gap-3" data-due-payment-modal="supplier">
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Supplier
                    <input class="form-control" type="search" data-due-search placeholder="Search by name or phone">
                </label>

                <div class="max-h-40 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2" data-due-results></div>

                <input type="hidden" data-due-selected-id>

                <div class="grid gap-2 rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm font-black text-slate-700">
                    <div class="flex items-center justify-between gap-3">
                        <span>Total Due</span>
                        <strong class="text-amber-700" data-due-total>Rs. 0.00</strong>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span>Remaining Due</span>
                        <strong class="text-slate-900" data-due-remaining>Rs. 0.00</strong>
                    </div>
                    <div class="flex items-center justify-between gap-3 text-xs text-slate-500">
                        <span>Date</span>
                        <span data-due-date>{{ now()->toDateString() }}</span>
                    </div>
                </div>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Amount Paid
                    <input class="form-control" type="number" step="0.01" min="0" data-due-amount placeholder="0.00">
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Payment Method
                    <select class="form-control" data-due-payment-method>
                        <option value="cash">Cash Drawer</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="card">Card Payment</option>
                        <option value="qr">QR Payment</option>
                    </select>
                </label>

                <label class="hidden gap-2 text-sm font-black text-slate-700" data-due-bank-wrap>
                    Bank Account
                    <select class="form-control" data-due-bank-account>
                        @foreach($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected($account->is_default)>
                            {{ $account->name }}{{ $account->account_no ? ' - '.$account->account_no : '' }}{{ $account->is_default ? ' (Default)' : '' }}
                        </option>
                        @endforeach
                    </select>
                </label>

                <div class="flex gap-3">
                    <button class="btn-secondary flex-1" data-due-cancel type="button">Cancel</button>
                    <button class="btn-primary flex-1" data-due-save type="button">Save Payment</button>
                </div>
            </div>
        </div>
    </div>
    @endcan

    <!-- Add Expense Modal -->
    <div id="expense-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <h2 class="text-2xl font-black text-slate-950">Add Expense</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Add expenses that will be deducted from cash or bank during register close.</p>

            <div class="mt-4 grid gap-3">
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Expense Category
                    <div class="flex gap-2">
                        <select class="form-control flex-1" data-expense-category required>
                            <option value="">-- Select Category --</option>
                            @forelse($expenseCategories as $cat)
                            <option value="{{ $cat->id }}" data-name="{{ $cat->name }}">{{ $cat->name }}</option>
                            @empty
                            <option value="supplies">Supplies</option>
                            <option value="fuel">Fuel</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="utilities">Utilities</option>
                            <option value="other">Other</option>
                            @endforelse
                        </select>
                        <button type="button" class="pos-field-add pos-field-add-circle" data-add-category aria-label="Add category">+</button>
                    </div>
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Expense Amount
                    <input class="form-control" type="number" step="0.01" min="0" data-expense-amount placeholder="0.00" required>
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Expense Source
                    <select class="form-control" data-expense-source>
                        <option value="drawer">Drawer Cash</option>
                        <option value="bank">Bank Account</option>
                    </select>
                </label>

                <label class="hidden gap-2 text-sm font-black text-slate-700" data-expense-bank-wrap>
                    Bank Account
                    <select class="form-control" data-expense-bank-account>
                        @foreach($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected($account->is_default)>
                            {{ $account->name }}{{ $account->account_no ? ' - '.$account->account_no : '' }}{{ $account->is_default ? ' (Default)' : '' }}
                        </option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Description (Optional)
                    <textarea class="form-control min-h-20" data-expense-description placeholder="Enter expense description..."></textarea>
                </label>

                <div class="flex gap-3">
                    <button class="btn-secondary flex-1" data-expense-cancel type="button">Cancel</button>
                    <button class="btn-primary flex-1" data-expense-save type="button">Add Expense</button>
                </div>
            </div>

            <div class="absolute inset-0 hidden place-items-center rounded-[18px] bg-slate-950/45 p-4 backdrop-blur-sm" data-expense-category-popup>
                <div class="w-full rounded-lg bg-white p-4 shadow-2xl">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-black text-slate-950">Add Expense Category</h3>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Create a category and select it for this expense.</p>
                        </div>
                        <button type="button" class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-lg font-black text-slate-600 hover:bg-slate-200" data-expense-category-cancel aria-label="Close expense category popup">×</button>
                    </div>

                    <label class="grid gap-2 text-sm font-black text-slate-700">
                        Category Name
                        <input class="form-control" type="text" data-expense-category-name placeholder="e.g. Staff Meals">
                    </label>

                    <p class="mt-2 hidden text-sm font-bold text-red-600" data-expense-category-error></p>

                    <div class="mt-4 flex gap-3">
                        <button class="btn-secondary flex-1" data-expense-category-cancel type="button">Cancel</button>
                        <button class="btn-primary flex-1" data-expense-category-save type="button">Save Category</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Line item editor modal -->
    <div id="line-item-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <h2 class="text-2xl font-black text-slate-950">Edit Line Item</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Change name, unit price, and add a discount for this line.</p>

            <div class="mt-4 grid gap-3">
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Item Name
                    <input class="form-control" type="text" data-line-name>
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Unit Price
                    <input class="form-control" type="number" step="0.01" min="0" data-line-price>
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Discount Type
                    <select class="form-control" data-line-discount-type>
                        <option value="fixed">Fixed Amount</option>
                        <option value="percent">Percentage</option>
                    </select>
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Discount Value
                    <input class="form-control" type="number" step="0.01" min="0" data-line-discount-value>
                </label>

                <div class="flex gap-3">
                    <button class="btn-secondary flex-1" data-line-cancel type="button">Cancel</button>
                    <button class="btn-primary flex-1" data-line-save type="button">Save</button>
                </div>
            </div>
        </div>
    </div>

    @can('waiters.create')
    <div id="waiter-create-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <h2 class="text-2xl font-black text-slate-950">Add Waiter</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Quickly create a waiter without leaving POS.</p>

            <form class="mt-4 grid gap-3" data-quick-create-waiter>
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Name
                    <input class="form-control" type="text" name="name" required placeholder="Waiter name">
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Phone
                    <input class="form-control" type="text" name="phone" placeholder="Phone optional">
                </label>

                <button class="btn-primary mt-2 w-full justify-center" type="submit">Save Waiter</button>
            </form>
        </div>
    </div>
    @endcan

    @can('customers.create')
    <div id="customer-create-modal" class="modal">
        <div class="modal-panel">
            <button class="modal-close" data-modal-close type="button">×</button>

            <h2 class="text-2xl font-black text-slate-950">Add Customer</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Quickly create a customer without leaving POS.</p>

            <form class="mt-4 grid gap-3" data-quick-create-customer>
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Name
                    <input class="form-control" type="text" name="name" required placeholder="Customer name">
                </label>

                <label class="grid gap-2 text-sm font-black text-slate-700">
                    Phone
                    <input class="form-control" type="text" name="phone" placeholder="Phone optional">
                </label>

                <button class="btn-primary mt-2 w-full justify-center" type="submit">Save Customer</button>
            </form>
        </div>
    </div>
    @endcan

    @can('pos.close_register')
    <!-- Close Register Summary Modal -->
    <div class="fixed inset-0 z-40 hidden items-center justify-center bg-black/50 p-3" data-close-register-modal>
        <div class="max-h-[calc(100vh-1.5rem)] w-full max-w-5xl overflow-y-auto rounded-lg bg-white p-4 shadow-xl">
            <h2 class="mb-3 text-lg font-black text-slate-900">Close Register - Summary</h2>

            <div class="mb-4 grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2 grid gap-3">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 p-3 bg-white shadow-sm">
                            <p class="text-xs font-semibold text-slate-600">Overall Cash Balance</p>
                            <p class="mt-1 text-xl font-black text-slate-900" data-cash-in-cashier>Rs. 0.00</p>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-3 bg-white shadow-sm">
                            <p class="text-xs font-semibold text-slate-600">Total Cash Sales</p>
                            <p class="mt-1 text-xl font-black text-emerald-600" data-total-cash-sales>Rs. 0.00</p>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-3 bg-white shadow-sm">
                            <p class="text-xs font-semibold text-slate-600">Bank Amount</p>
                            <p class="mt-1 text-xl font-black text-slate-900" data-bank-amount>Rs. 0.00</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-lg bg-blue-50 p-2.5">
                            <p class="text-xs font-semibold text-slate-600">Total Orders</p>
                            <p class="mt-1 text-base font-black text-blue-600" data-total-orders>0</p>
                        </div>

                        <div class="rounded-lg bg-emerald-50 p-2.5">
                            <p class="text-xs font-semibold text-slate-600">Tables Served</p>
                            <p class="mt-1 text-base font-black text-emerald-600" data-tables-served>0</p>
                        </div>

                        <div class="rounded-lg bg-orange-50 p-2.5">
                            <p class="text-xs font-semibold text-slate-600">Takeaways</p>
                            <p class="mt-1 text-base font-black text-orange-600" data-takeaway-orders>0</p>
                        </div>
                    </div>
                </div>

                <!-- Overall Cash Book Summary -->
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3.5 flex flex-col justify-between shadow-sm">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-600 mb-2.5">Overall Cash Balance</h3>
                        <div class="space-y-1.5 text-xs font-semibold text-slate-600">
                            <div class="flex justify-between">
                                <span>Total Cash Balance (Before)</span>
                                <span class="text-slate-900 font-bold" data-overall-cash-before>Rs. 0.00</span>
                            </div>
                            <div class="flex justify-between border-t border-slate-200/50 pt-1.5">
                                <span>Drawer Open Balance (+)</span>
                                <span class="text-slate-900" data-overall-cash-opening>Rs. 0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Cash In (+)</span>
                                <span class="text-emerald-600" data-overall-cash-in>Rs. 0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Sale Cash (+)</span>
                                <span class="text-emerald-600" data-overall-cash-sales>Rs. 0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Cash Out (-)</span>
                                <span class="text-red-600" data-overall-cash-out>Rs. 0.00</span>
                            </div>
                            <div class="flex justify-between pb-1.5">
                                <span>Total Expense Amount (-)</span>
                                <span class="text-red-600" data-overall-cash-expenses>Rs. 0.00</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-sm font-black text-slate-900">
                        <span>Total Cash Balance (Now)</span>
                        <span class="text-blue-600 font-bold" data-overall-cash-now>Rs. 0.00</span>
                    </div>
                </div>
            </div>

            <div class="mb-4 grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1.5 text-xs font-black text-slate-700">
                    Actual Cash Drawer Balance
                    <input class="form-control" type="number" step="0.01" min="0" data-modal-actual-cash placeholder="0.00" required>
                </label>

                <label class="grid gap-1.5 text-xs font-black text-slate-700">
                    Actual Bank Balance
                    <input class="form-control" type="number" step="0.01" min="0" data-modal-actual-bank placeholder="0.00" required>
                </label>
            </div>

            <div class="mb-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500" data-close-difference-label>Cash Balanced</p>
                    <p class="mt-1 text-base font-black text-slate-700" data-close-difference-amount>Rs. 0.00</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500" data-bank-difference-label>Bank Balanced</p>
                    <p class="mt-1 text-base font-black text-slate-700" data-bank-difference-amount>Rs. 0.00</p>
                </div>
            </div>

            <div class="mb-4 flex justify-end">
                <a href="{{ route('pos.register.close-cash-book.pdf') }}" class="btn-secondary justify-center" target="_blank">
                    <x-lucide name="file-text" class="size-4" />
                    <span>Download Cash Book PDF</span>
                </a>
            </div>

            <!-- Expenses Section -->
            <div class="mb-4 hidden rounded-lg border border-amber-200 bg-amber-50 p-3" data-close-expenses-wrap>
                <p class="mb-2 text-xs font-black uppercase tracking-wide text-amber-800">Expenses</p>
                <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-3" data-close-expenses-list></div>
            </div>

            <div class="mb-4 hidden" data-close-note-wrap>
                <label class="grid gap-1.5 text-xs font-black text-slate-700">
                    Notes (Optional)
                    <textarea class="form-control min-h-16" data-close-note placeholder="Add reason for shortage/overage (optional)"></textarea>
                </label>
            </div>

            <div class="sticky bottom-0 -mx-4 -mb-4 flex gap-3 border-t border-slate-100 bg-white p-4">
                <button type="button" class="btn-secondary flex-1" data-close-register-modal-cancel>Cancel</button>
                <button type="button" class="btn-primary flex-1" data-close-register-modal-submit>Close Register</button>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('pos.register.close') }}" class="pos-close-register-form fixed bottom-4 right-4 z-30">
        @csrf
        <input type="hidden" name="actual_cash" value="0" data-actual-cash>
        <input type="hidden" name="note" value="" data-close-note-hidden>
        <button class="pos-close-register px-5 py-3 text-sm font-black text-white shadow-xl" data-close-register>
            Close Register
        </button>
    </form>
    @endcan

    @if(session('show_close_register'))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const closeRegisterBtn = document.querySelector('[data-close-register]');
            if (closeRegisterBtn) {
                closeRegisterBtn.click();
            }
        });
    </script>
    @endpush
    @endif
</x-layouts.app>
