@props(['drawerOnly' => false])

@php
    $user = auth()->user();
    $isDeveloper = $user?->hasRole('Developer') ?? false;
    $currentRoute = request()->route()?->getName();
    $currentModule = request()->route('module');
    $currentReport = request()->route('report');

    $item = function (string $label, string $icon, string $permission, string $url, bool $active = false) use ($user) {
        if (! $user?->can($permission)) {
            return null;
        }

        return compact('label', 'icon', 'permission', 'url', 'active');
    };

    $moduleUrl = fn (string $module, ?string $action = null) => $action === 'create'
        ? route('backoffice.modules.create', $module)
        : route('backoffice.modules.index', $module);

    $reportUrl = fn (string $report, array $parameters = []) => route('reports.show', array_merge(['report' => $report], $parameters));

    $groups = [
        [
            'label' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'children' => [
                $item('Dashboard', 'layout-dashboard', 'dashboard.view', route('dashboard'), $currentRoute === 'dashboard'),
            ],
        ],
        [
            'label' => 'POS',
            'icon' => 'shopping-cart',
            'children' => [
                $item('POS Screen', 'monitor', 'pos.access', route('pos.index'), $currentRoute === 'pos.index'),
                $item('Online Orders', 'smartphone', 'online_orders.view', route('online-orders.index'), $currentRoute === 'online-orders.index'),
                $item('Sales List', 'receipt', 'sales.view', $moduleUrl('sales'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'sales'),
                $item('Hold Orders', 'pause-circle', 'pos.resume_hold_order', route('pos.index', ['view' => 'holds']), request('view') === 'holds'),
                $item('Payment Pending Tables', 'clock', 'pos.print_bill', route('pos.index', ['view' => 'payment-pending']), request('view') === 'payment-pending'),
                $item('Register Opening', 'door-open', 'pos.open_register', route('pos.index', ['register' => 'open']), request('register') === 'open'),
                $item('Register Closing', 'door-closed', 'pos.close_register', route('pos.index', ['register' => 'close']), request('register') === 'close'),
            ],
        ],
        [
            'label' => 'Users',
            'icon' => 'users',
            'children' => [
                $item('Users', 'users', 'users.view', $moduleUrl('users'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'users'),
                $item('Roles', 'shield-check', 'roles.view', $moduleUrl('roles'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'roles'),
                $item('Permissions', 'key-round', 'roles.edit', $moduleUrl('roles'), $currentModule === 'roles' && str_contains((string) $currentRoute, 'backoffice.modules')),
            ],
        ],
        [
            'label' => 'Suppliers',
            'icon' => 'truck',
            'children' => [
                $item('Suppliers', 'truck', 'suppliers.view', $moduleUrl('suppliers'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'suppliers'),
                $item('Add Supplier', 'circle-plus', 'suppliers.create', $moduleUrl('suppliers', 'create'), $currentRoute === 'backoffice.modules.create' && $currentModule === 'suppliers'),
                $item('Supplier Report', 'chart-bar', 'reports.supplier-due.view', $reportUrl('supplier-due'), $currentReport === 'supplier-due'),
            ],
        ],
        [
            'label' => 'Customers',
            'icon' => 'user-round',
            'children' => [
                $item('Customers', 'user-round', 'customers.view', $moduleUrl('customers'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'customers'),
                $item('Add Customer', 'user-plus', 'customers.create', $moduleUrl('customers', 'create'), $currentRoute === 'backoffice.modules.create' && $currentModule === 'customers'),
                $item('Customer Due Report', 'chart-bar', 'reports.customer-due.view', $reportUrl('customer-due'), $currentReport === 'customer-due'),
            ],
        ],
        [
            'label' => 'Products',
            'icon' => 'package',
            'children' => [
                $item('Categories', 'tags', 'categories.view', $moduleUrl('categories'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'categories'),
                $item('Products', 'package', 'products.view', $moduleUrl('products'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'products'),
                $item('Add Product', 'circle-plus', 'products.create', $moduleUrl('products', 'create'), $currentRoute === 'backoffice.modules.create' && $currentModule === 'products'),
                $item('Tables', 'armchair', 'tables.view', $moduleUrl('tables'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'tables'),
                $item('Damage Write-Offs', 'triangle-alert', 'damage_write_offs.view', $moduleUrl('damage_write_offs'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'damage_write_offs'),
                $item('Stock Report', 'chart-bar', 'reports.stock.view', $reportUrl('stock'), $currentReport === 'stock'),
            ],
        ],
        [
            'label' => 'Purchases',
            'icon' => 'shopping-bag',
            'children' => [
                $item('Purchases', 'shopping-bag', 'purchases.view', $moduleUrl('purchases'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'purchases'),
                $item('Add Purchase', 'circle-plus', 'purchases.create', $moduleUrl('purchases', 'create'), $currentRoute === 'backoffice.modules.create' && $currentModule === 'purchases'),
                $item('Purchase Returns', 'rotate-ccw', 'purchases.view', $moduleUrl('purchases'), false),
                $item('Purchase Report', 'chart-bar', 'reports.purchases.view', $reportUrl('purchases'), $currentReport === 'purchases'),
            ],
        ],
        [
            'label' => 'Waiters',
            'icon' => 'user-check',
            'children' => [
                $item('Waiters', 'user-check', 'waiters.view', $moduleUrl('waiters'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'waiters'),
                $item('Add Waiter', 'user-plus', 'waiters.create', $moduleUrl('waiters', 'create'), $currentRoute === 'backoffice.modules.create' && $currentModule === 'waiters'),
                $item('Waiter Availability', 'toggle-right', 'waiters.edit', $moduleUrl('waiters'), false),
                $item('Waiter Report', 'chart-bar', 'reports.waiters.view', $reportUrl('waiters'), $currentReport === 'waiters'),
                $item('Incentive Report', 'badge-dollar-sign', 'reports.waiters.view', $reportUrl('waiters',), false),
            ],
        ],
        [
            'label' => 'Expenses',
            'icon' => 'wallet',
            'children' => [
                $item('Expense Categories', 'tags', 'expense_categories.view', $moduleUrl('expense_categories'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'expense_categories'),
                $item('Expenses', 'wallet', 'expenses.view', $moduleUrl('expenses'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'expenses'),
                $item('Add Expense', 'circle-plus', 'expenses.create', $moduleUrl('expenses', 'create'), $currentRoute === 'backoffice.modules.create' && $currentModule === 'expenses'),
                $item('Expense Report', 'chart-bar', 'reports.expenses.view', $reportUrl('expenses'), $currentReport === 'expenses'),
            ],
        ],
        [
            'label' => 'Accounts',
            'icon' => 'landmark',
            'children' => [
                $item('Cash Book', 'book-open-text', 'accounts.view', route('accounts.cash-book'), $currentRoute === 'accounts.cash-book'),
                $item('Daily Cash Closing', 'banknote', 'accounts.view', $reportUrl('cash-flow'), $currentReport === 'cash-flow' && ! in_array(request('account_flow'), ['cash-in', 'cash-out', 'balance', 'bank'], true)),
                $item('Daily Register Closing', 'clipboard-check', 'reports.register-closing.view', $reportUrl('register-closing'), $currentReport === 'register-closing'),
                $item('Cash In', 'arrow-down-to-line', 'accounts.cash_in.create', $reportUrl('cash-flow', ['account_flow' => 'cash-in']), $currentReport === 'cash-flow' && request('account_flow') === 'cash-in'),
                $item('Cash Out', 'arrow-up-from-line', 'accounts.cash_out.create', $reportUrl('cash-flow', ['account_flow' => 'cash-out']), $currentReport === 'cash-flow' && request('account_flow') === 'cash-out'),
                $item('Cash Balance', 'wallet-cards', 'accounts.view', $reportUrl('cash-flow', ['account_flow' => 'balance']), $currentReport === 'cash-flow' && request('account_flow') === 'balance'),
                $item('Bank Transfers', 'landmark', 'accounts.bank_transfer.create', $reportUrl('cash-flow', ['account_flow' => 'bank']), $currentReport === 'cash-flow' && request('account_flow') === 'bank'),
                $item('Payment Method Report', 'credit-card', 'reports.payment-methods.view', $reportUrl('payment-methods'), $currentReport === 'payment-methods'),
                $item('T Accounts', 'columns-3', 'reports.t-accounts.view', $reportUrl('t-accounts'), $currentReport === 't-accounts'),
            ],
        ],
        [
            'label' => 'Reports',
            'icon' => 'chart-bar',
            'children' => [
                $item('Sales Report', 'chart-bar', 'reports.sales.view', $reportUrl('sales'), $currentReport === 'sales'),
                $item('Purchase Report', 'chart-bar', 'reports.purchases.view', $reportUrl('purchases'), $currentReport === 'purchases'),
                $item('Profit & Loss', 'trending-up', 'reports.profit-loss.view', $reportUrl('profit-loss'), $currentReport === 'profit-loss'),
                $item('Stock Report', 'boxes', 'reports.stock.view', $reportUrl('stock'), $currentReport === 'stock'),
                $item('Expense Report', 'wallet', 'reports.expenses.view', $reportUrl('expenses'), $currentReport === 'expenses'),
                $item('Receive Report', 'arrow-down-to-line', 'reports.receive.view', $reportUrl('receive'), $currentReport === 'receive'),
                $item('Debit Report', 'arrow-up-from-line', 'reports.debit.view', $reportUrl('debit'), $currentReport === 'debit'),
                $item('Due Bills Report', 'clock', 'reports.due-bills.view', $reportUrl('due-bills'), $currentReport === 'due-bills'),
                $item('Customer Due Report', 'user-round', 'reports.customer-due.view', $reportUrl('customer-due'), $currentReport === 'customer-due'),
                $item('Supplier Due Report', 'truck', 'reports.supplier-due.view', $reportUrl('supplier-due'), $currentReport === 'supplier-due'),
                $item('QR Payment Report', 'qr-code', 'reports.qr-payments.view', $reportUrl('qr-payments'), $currentReport === 'qr-payments'),
                $item('Card Fee Report', 'credit-card', 'reports.card-fees.view', $reportUrl('card-fees'), $currentReport === 'card-fees'),
                $item('Online Orders Report', 'smartphone', 'reports.online-orders.view', $reportUrl('online-orders'), $currentReport === 'online-orders'),
            ],
        ],
        [
            'label' => 'Settings',
            'icon' => 'settings',
            'children' => [
                $item('Business Info', 'building-2', 'settings.view', route('settings.edit', ['section' => 'business']), request('section') === 'business'),
                $item('General Settings', 'sliders-horizontal', 'settings.view', route('settings.edit', ['section' => 'general']), request('section') === 'general'),
                $item('Invoice Settings', 'receipt', 'settings.view', route('settings.edit', ['section' => 'invoice']), request('section') === 'invoice'),
                $item('POS Settings', 'monitor', 'settings.view', route('settings.edit', ['section' => 'pos']), request('section') === 'pos'),
                $item('Online Platforms', 'smartphone', 'online_order_sources.view', $moduleUrl('online_order_sources'), $currentRoute === 'backoffice.modules.index' && $currentModule === 'online_order_sources'),
                $item('Barcode Settings', 'scan-barcode', 'settings.view', route('settings.edit', ['section' => 'barcode']), request('section') === 'barcode'),
                $item('Permission Settings', 'shield-check', 'roles.edit', $moduleUrl('roles'), $currentModule === 'roles'),
            ],
        ],
        [
            'label' => 'System',
            'icon' => 'shield-check',
            'children' => [
                $isDeveloper ? $item('Developer Tools', 'settings', 'system_tools.view', route('system-tools.index'), $currentRoute === 'system-tools.index') : null,
            ],
        ],
        [
            'label' => 'Activity',
            'icon' => 'history',
            'children' => [
                $item('Activity Log', 'history', 'activity_logs.view', route('activity-logs.index'), $currentRoute === 'activity-logs.index'),
            ],
        ],
    ];

    $groups = collect($groups)
        ->map(function (array $group) {
            $children = collect($group['children'])->filter()->values();

            if ($children->isEmpty()) {
                return null;
            }

            $group['children'] = $children;
            $group['active'] = $children->contains(fn (array $child) => $child['active']);

            return $group;
        })
        ->filter()
        ->values();
@endphp

<div class="sidebar-overlay {{ $drawerOnly ? 'sidebar-overlay-drawer' : '' }}" data-sidebar-overlay></div>

<aside id="sidebar" class="sidebar-shell {{ $drawerOnly ? 'sidebar-drawer-only' : '' }}" data-sidebar @if($drawerOnly) data-sidebar-drawer-only="1" @endif>
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="sidebar-logo" aria-label="Hotel POS dashboard">
            <span class="sidebar-logo-mark">HP</span>
            <span class="sidebar-brand-text">
                <strong>Hotel POS</strong>
                <small>Restaurant operations</small>
            </span>
        </a>
        <button class="sidebar-toggle {{ $drawerOnly ? 'hidden' : '' }}" data-sidebar-collapse aria-label="Collapse sidebar">
            <x-lucide name="panel-left-close" class="sidebar-toggle-expanded size-5" />
            <x-lucide name="panel-left-open" class="sidebar-toggle-collapsed size-5" />
        </button>
        <button class="sidebar-close {{ $drawerOnly ? '' : 'lg:hidden' }}" data-sidebar-close aria-label="Close sidebar">
            <x-lucide name="x" class="size-5" />
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">
        <p class="sidebar-section-label">Main</p>
        @foreach($groups as $group)
            @if($group['label'] === 'Dashboard')
                @php($dashboard = $group['children']->first())
                <div class="sidebar-menu-group {{ $dashboard['active'] ? 'is-active' : '' }}">
                    <a href="{{ $dashboard['url'] }}" class="sidebar-menu-button" title="{{ $dashboard['label'] }}">
                        <span class="sidebar-icon"><x-lucide :name="$group['icon']" class="size-5" /></span>
                        <span class="sidebar-label">{{ $dashboard['label'] }}</span>
                    </a>
                </div>
                @continue
            @endif

            <div class="sidebar-menu-group {{ $group['active'] ? 'is-open is-active' : '' }}" data-sidebar-menu-group data-active="{{ $group['active'] ? '1' : '0' }}">
                <button class="sidebar-menu-button" type="button" data-sidebar-group-toggle title="{{ $group['label'] }}" aria-expanded="{{ $group['active'] ? 'true' : 'false' }}">
                    <span class="sidebar-icon"><x-lucide :name="$group['icon']" class="size-5" /></span>
                    <span class="sidebar-label">{{ $group['label'] }}</span>
                    <span class="sidebar-arrow"><x-lucide name="chevron-right" class="size-4" /></span>
                </button>
                <div class="sidebar-submenu" data-sidebar-submenu>
                    <div class="sidebar-floating-title">{{ $group['label'] }}</div>
                    @foreach($group['children'] as $child)
                        <a href="{{ $child['url'] }}" class="sidebar-submenu-link {{ $child['active'] ? 'active' : '' }}" title="{{ $child['label'] }}">
                            <span class="sidebar-submenu-icon"><x-lucide :name="$child['icon']" class="size-4" /></span>
                            <span class="sidebar-submenu-text">{{ $child['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>
