<x-layouts.app heading="Settings" title="Settings" :show-date-filter="false">
    @php
        $sectionIcons = [
            'business' => 'building-2',
            'general' => 'settings',
            'invoice' => 'receipt',
            'pos' => 'monitor',
            'barcode' => 'scan-barcode',
        ];

        $groups = [
            'business' => [
                [
                    'title' => 'Business Information',
                    'icon' => 'building-2',
                    'accent' => 'text-blue-600',
                    'fields' => [
                        ['key' => 'logo', 'label' => 'Business Logo', 'type' => 'file', 'span' => 'lg:col-span-2'],
                        ['key' => 'name', 'label' => 'Business Name', 'type' => 'text', 'required' => true, 'default' => 'Hotel POS'],
                        ['key' => 'tagline', 'label' => 'Tagline / Small Heading', 'type' => 'text', 'default' => 'Restaurant operations'],
                        ['key' => 'email', 'label' => 'Business Email', 'type' => 'email'],
                        ['key' => 'phone', 'label' => 'Business Phone', 'type' => 'text'],
                        ['key' => 'address', 'label' => 'Business Address', 'type' => 'textarea', 'span' => 'lg:col-span-2'],
                    ],
                ],
            ],
            'currency' => [
                [
                    'title' => 'Currency Settings',
                    'icon' => 'wallet',
                    'accent' => 'text-emerald-600',
                    'fields' => [
                        ['key' => 'symbol', 'label' => 'Currency Symbol', 'type' => 'text', 'default' => 'Rs.'],
                        ['key' => 'position', 'label' => 'Currency Position', 'type' => 'select', 'default' => 'before', 'options' => ['before' => 'Before Amount (Rs 100)', 'after' => 'After Amount (100 Rs)']],
                        ['key' => 'decimal_places', 'label' => 'Decimal Places', 'type' => 'number', 'default' => '2'],
                    ],
                ],
            ],
            'date' => [
                [
                    'title' => 'Date & Time Settings',
                    'icon' => 'clock',
                    'accent' => 'text-blue-600',
                    'fields' => [
                        ['key' => 'format', 'label' => 'Date Format', 'type' => 'select', 'default' => 'Y-m-d', 'options' => ['Y-m-d' => 'YYYY-MM-DD (2026-04-29)', 'd/m/Y' => 'DD/MM/YYYY (29/04/2026)', 'm/d/Y' => 'MM/DD/YYYY (04/29/2026)']],
                        ['key' => 'time_format', 'label' => 'Time Format', 'type' => 'select', 'default' => '12', 'options' => ['12' => '12 Hour (02:30 PM)', '24' => '24 Hour (14:30)']],
                        ['key' => 'timezone', 'label' => 'Timezone', 'type' => 'select', 'default' => 'Asia/Colombo', 'options' => ['Asia/Colombo' => 'Asia/Colombo (Sri Lanka)', 'UTC' => 'UTC', 'Asia/Dubai' => 'Asia/Dubai']],
                    ],
                ],
            ],
            'display' => [
                [
                    'title' => 'Display Settings',
                    'icon' => 'monitor',
                    'accent' => 'text-indigo-600',
                    'fields' => [
                        ['key' => 'language', 'label' => 'Language', 'type' => 'select', 'default' => 'en', 'options' => ['en' => 'English', 'si' => 'Sinhala', 'ta' => 'Tamil']],
                        ['key' => 'items_per_page', 'label' => 'Items Per Page', 'type' => 'number', 'default' => '10', 'hint' => 'Number of items to display in lists'],
                        ['key' => 'theme_color', 'label' => 'Theme Color', 'type' => 'text', 'default' => '#2563eb'],
                        ['key' => 'sidebar_collapsed', 'label' => 'Sidebar Collapsed Default', 'type' => 'toggle', 'default' => '0', 'hint' => 'Start with a compact sidebar on large screens.'],
                    ],
                ],
            ],
            'stock' => [
                [
                    'title' => 'Stock Management',
                    'icon' => 'boxes',
                    'accent' => 'text-amber-600',
                    'fields' => [
                        ['key' => 'default_maintain_stock', 'label' => 'Default Maintain Stock', 'type' => 'toggle', 'default' => '1', 'hint' => 'New products will track inventory by default.'],
                        ['key' => 'low_stock_alert', 'label' => 'Low Stock Warning', 'type' => 'toggle', 'default' => '1', 'hint' => 'Show warnings when products are running low.'],
                        ['key' => 'allow_negative_stock', 'label' => 'Allow Negative Stock', 'type' => 'toggle', 'default' => '0', 'hint' => 'Allow selling even when stock is below zero.'],
                    ],
                ],
            ],
            'invoice' => [
                [
                    'title' => 'Invoice Settings',
                    'icon' => 'receipt',
                    'accent' => 'text-blue-600',
                    'fields' => [
                        ['key' => 'prefix', 'label' => 'Invoice Prefix', 'type' => 'text', 'default' => 'INV'],
                        ['key' => 'paper_size', 'label' => 'Paper Size', 'type' => 'select', 'default' => '80mm', 'options' => ['80mm' => '80mm Thermal', 'A4' => 'A4', 'A5' => 'A5']],
                        ['key' => 'show_logo', 'label' => 'Show Logo', 'type' => 'toggle', 'default' => '1'],
                        ['key' => 'show_customer', 'label' => 'Show Customer Info', 'type' => 'toggle', 'default' => '1'],
                        ['key' => 'show_waiter', 'label' => 'Show Waiter Info', 'type' => 'toggle', 'default' => '1'],
                        ['key' => 'show_table', 'label' => 'Show Table Info', 'type' => 'toggle', 'default' => '1'],
                        ['key' => 'footer_text', 'label' => 'Footer Text', 'type' => 'textarea'],
                        ['key' => 'terms', 'label' => 'Terms & Conditions', 'type' => 'textarea'],
                    ],
                ],
            ],
            'pos' => [
                [
                    'title' => 'Payment Settings',
                    'icon' => 'wallet-cards',
                    'accent' => 'text-violet-600',
                    'fields' => [
                        ['key' => 'qr_code_image', 'label' => 'QR Code Image', 'type' => 'file', 'span' => 'lg:col-span-2'],
                    ],
                ],
                [
                    'title' => 'Card Sale Fee',
                    'icon' => 'credit-card',
                    'accent' => 'text-emerald-600',
                    'fields' => [
                        ['key' => 'enable_card_fee', 'label' => 'Enable card fee', 'type' => 'toggle', 'default' => '0', 'hint' => 'Apply a percentage fee for card payments.'],
                        ['key' => 'card_fee_rate', 'label' => 'Card Fee Rate %', 'type' => 'number', 'default' => '3'],
                    ],
                ],
                [
                    'title' => 'Sale Rules',
                    'icon' => 'toggle-right',
                    'accent' => 'text-blue-600',
                    'fields' => [
                        ['key' => 'default_customer', 'label' => 'Default Customer', 'type' => 'text', 'default' => 'Walk-in Customer'],
                        ['key' => 'enable_waiter_incentive', 'label' => 'Enable Waiter Incentive', 'type' => 'toggle', 'default' => '1'],
                        ['key' => 'default_waiter_incentive', 'label' => 'Default Waiter Incentive %', 'type' => 'number', 'default' => '2'],
                        ['key' => 'allow_due_sale', 'label' => 'Allow Due Sale', 'type' => 'toggle', 'default' => '1'],
                        ['key' => 'allow_due_walk_in', 'label' => 'Allow Due For Walk-in Customer', 'type' => 'toggle', 'default' => '0'],
                        ['key' => 'enable_print_before_payment', 'label' => 'Enable Print Before Payment', 'type' => 'toggle', 'default' => '1'],
                        ['key' => 'enable_service_charge', 'label' => 'Enable Service Charge', 'type' => 'toggle', 'default' => '1'],
                        ['key' => 'service_charge_percentage', 'label' => 'Service Charge %', 'type' => 'number', 'default' => '10'],
                    ],
                ],
            ],
            'barcode' => [
                [
                    'title' => 'Barcode Settings',
                    'icon' => 'scan-barcode',
                    'accent' => 'text-blue-600',
                    'fields' => [
                        ['key' => 'auto_generate', 'label' => 'Auto Generate Barcode', 'type' => 'toggle', 'default' => '1', 'hint' => 'Create barcode numbers automatically for new products.'],
                        ['key' => 'prefix', 'label' => 'Barcode Prefix', 'type' => 'text', 'default' => 'POS'],
                        ['key' => 'length', 'label' => 'Barcode Length', 'type' => 'number', 'default' => '8'],
                        ['key' => 'next_number', 'label' => 'Next Barcode Number', 'type' => 'number', 'default' => '1'],
                        ['key' => 'label_size', 'label' => 'Label Size Preset', 'type' => 'select', 'default' => 'small', 'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']],
                        ['key' => 'default_preset', 'label' => 'Default Barcode Preset', 'type' => 'text'],
                        ['key' => 'print_settings', 'label' => 'Print Settings', 'type' => 'textarea', 'span' => 'lg:col-span-2'],
                    ],
                ],
            ],
        ];

        $valueFor = function (string $group, array $field) use ($settings) {
            $compound = $group.'.'.$field['key'];
            return old('settings.'.$group.'.'.$field['key'], $settings[$compound]->value ?? ($field['default'] ?? ''));
        };
    @endphp

    <div class="space-y-4 text-sm">
        <section class="pos-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-600/20">
                        <x-lucide :name="$sectionIcons[$sectionKey] ?? 'settings'" class="size-5" />
                    </div>
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-blue-600">Settings</p>
                        <h2 class="text-xl font-black text-slate-950">{{ $section['label'] }}</h2>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($sections as $key => $definition)
                        <a
                            href="{{ route('settings.edit', ['section' => $key]) }}"
                            class="inline-flex min-h-9 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black transition {{ $sectionKey === $key ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900' }}"
                        >
                            <x-lucide :name="$sectionIcons[$key] ?? 'settings'" class="size-4" />
                            {{ $definition['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <form method="POST" enctype="multipart/form-data" action="{{ route('settings.update', ['section' => $sectionKey]) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="{{ $sectionKey }}">

            @foreach($section['groups'] as $group)
                @foreach($groups[$group] ?? [] as $panel)
                    <section class="pos-card space-y-4 !p-4">
                        <div class="flex items-center gap-3">
                            <x-lucide :name="$panel['icon']" class="size-5 {{ $panel['accent'] }}" />
                            <h3 class="text-lg font-black text-slate-900">{{ $panel['title'] }}</h3>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach($panel['fields'] as $field)
                                @php
                                    $compound = $group.'.'.$field['key'];
                                    $inputName = 'settings['.$group.']['.$field['key'].']';
                                    $fileName = 'files['.$group.']['.$field['key'].']';
                                    $fieldId = str_replace('.', '-', $compound);
                                    $value = $valueFor($group, $field);
                                    $isChecked = filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
                                    $span = $field['span'] ?? '';
                                    $currentFile = $settings[$compound]->value ?? null;
                                    $hasImage = is_string($currentFile) && $currentFile !== '';
                                @endphp

                                @if(($field['type'] ?? 'text') === 'toggle')
                                    <div class="{{ $span }} rounded-xl bg-slate-50 p-3">
                                        <label for="{{ $fieldId }}" class="flex cursor-pointer items-center justify-between gap-4">
                                            <span>
                                                <span class="block text-sm font-black text-slate-800">{{ $field['label'] }}</span>
                                                @if(! empty($field['hint']))
                                                    <span class="mt-1 block text-xs font-semibold text-slate-500">{{ $field['hint'] }}</span>
                                                @endif
                                            </span>
                                            <span class="shrink-0">
                                                <input type="hidden" name="{{ $inputName }}" value="0">
                                                <input id="{{ $fieldId }}" class="peer sr-only" type="checkbox" name="{{ $inputName }}" value="1" @checked($isChecked)>
                                                <span class="relative block h-7 w-[3.25rem] rounded-full bg-slate-300 transition after:absolute after:left-1 after:top-1 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-blue-600 peer-checked:after:translate-x-6"></span>
                                            </span>
                                        </label>
                                    </div>
                                @else
                                    <div class="{{ $span }} grid gap-2 text-xs font-black text-slate-800">
                                        <label for="{{ $fieldId }}">{{ $field['label'] }}@if(! empty($field['required'])) <span class="text-red-500">*</span>@endif</label>

                                        @if(($field['type'] ?? 'text') === 'file')
                                            <div class="rounded-xl border border-slate-200 bg-white p-2.5">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                                    <input id="{{ $fieldId }}" class="sr-only" type="file" name="{{ $fileName }}" accept="image/jpeg,image/png,image/gif,image/bmp,image/webp" data-settings-file data-max-bytes="{{ $uploadMaxMegabytes * 1024 * 1024 }}" data-file-target="{{ $fieldId }}-name" data-preview-target="{{ $fieldId }}-preview">
                                                    <label for="{{ $fieldId }}" class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-800 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                                                        Choose file
                                                    </label>
                                                    <span id="{{ $fieldId }}-name" class="text-xs font-semibold text-slate-500">No file chosen</span>
                                                </div>
                                                <div id="{{ $fieldId }}-preview-wrap" class="{{ $hasImage ? '' : 'hidden' }} mt-4">
                                                    <img id="{{ $fieldId }}-preview" class="h-24 w-36 rounded-lg border border-slate-100 object-cover shadow-sm" src="{{ $hasImage ? asset('storage/'.$currentFile) : '' }}" alt="{{ $field['label'] }} preview">
                                                </div>
                                            </div>
                                            <span class="text-[11px] font-semibold text-slate-500">JPG, PNG, GIF, BMP, or WEBP. Max {{ $uploadMaxMegabytes }} MB.</span>
                                        @elseif(($field['type'] ?? 'text') === 'textarea')
                                            <textarea id="{{ $fieldId }}" class="form-control min-h-28 !text-sm" name="{{ $inputName }}">{{ $value }}</textarea>
                                        @elseif(($field['type'] ?? 'text') === 'select')
                                            <select id="{{ $fieldId }}" class="form-control !min-h-11 !text-sm" name="{{ $inputName }}">
                                                @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input
                                                id="{{ $fieldId }}"
                                                class="form-control !min-h-11 !text-sm"
                                                type="{{ $field['type'] ?? 'text' }}"
                                                name="{{ $inputName }}"
                                                value="{{ $value }}"
                                                @if(($field['type'] ?? 'text') === 'number') step="0.01" @endif
                                                @if(! empty($field['required'])) required @endif
                                            >
                                        @endif

                                        @if(! empty($field['hint']) && ($field['type'] ?? 'text') !== 'toggle')
                                            <span class="text-[11px] font-semibold text-slate-500">{{ $field['hint'] }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @endforeach

            @can('settings.update')
                <div class="border-t border-slate-200 pt-4">
                    <button class="btn-primary min-h-11 gap-2 px-5 text-sm">
                        <x-lucide name="wallet-cards" class="size-4" />
                        Save Settings
                    </button>
                </div>
            @endcan
        </form>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-settings-file]').forEach((input) => {
                input.addEventListener('change', () => {
                    const file = input.files && input.files[0] ? input.files[0] : null;
                    const fileName = document.getElementById(input.dataset.fileTarget);
                    const preview = document.getElementById(input.dataset.previewTarget);
                    const previewWrap = preview ? document.getElementById(`${input.dataset.previewTarget}-wrap`) : null;
                    const maxBytes = Number(input.dataset.maxBytes || 0);

                    if (file && maxBytes && file.size > maxBytes) {
                        input.value = '';
                        if (fileName) {
                            fileName.textContent = `File is too large. Max ${Math.floor(maxBytes / 1024 / 1024)} MB.`;
                        }
                        return;
                    }

                    if (fileName) {
                        fileName.textContent = file ? file.name : 'No file chosen';
                    }

                    if (file && preview) {
                        preview.src = URL.createObjectURL(file);
                        preview.onload = () => URL.revokeObjectURL(preview.src);
                        previewWrap?.classList.remove('hidden');
                    }
                });
            });
        </script>
    @endpush
</x-layouts.app>
