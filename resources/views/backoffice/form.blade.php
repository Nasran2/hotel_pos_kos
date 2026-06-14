<x-layouts.app :heading="($record ? 'Edit ' : 'Add ').str($config['label'])->singular()" :title="$config['label']">
    <form method="POST" enctype="multipart/form-data" action="{{ $record ? route('backoffice.modules.update', [$module, $record->id]) : route('backoffice.modules.store', $module) }}" class="pos-card max-w-5xl space-y-4">
        @csrf
        @if($record) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($config['fields'] as $name => $field)
                @can($config['permission_prefix'].'.field.'.$name)
                    @php
                        $fieldValue = old($name, $record->{$name} ?? null);

                        if ($fieldValue === null && ($field['default'] ?? null) === 'now') {
                            $fieldValue = now();
                        }

                        if ($fieldValue === null && ($field['default'] ?? null) === 'default_bank') {
                            $fieldValue = DB::table('bank_accounts')->where('is_default', true)->value('id');
                        }

                        if (($field['type'] ?? null) === 'datetime-local' && $fieldValue) {
                            $fieldValue = \Illuminate\Support\Carbon::parse($fieldValue)->format('Y-m-d\TH:i');
                        }
                    @endphp
                    @if(($field['type'] ?? null) === 'permissions')
                        <div class="md:col-span-2">
                            <h2 class="mb-3 font-black">Permissions</h2>
                            <div class="grid gap-4 lg:grid-cols-3">
                                @foreach($permissionGroups as $category => $permissions)
                                    <div class="rounded-lg border border-slate-200 p-4">
                                        <label class="mb-3 flex items-center gap-2 font-bold">
                                            <input type="checkbox" class="permission-category size-4">
                                            {{ config("hotelpos.permission_categories.$category", str($category)->headline()) }}
                                        </label>
                                        <div class="grid gap-2 text-sm">
                                            @foreach($permissions as $permission)
                                                <label class="flex items-start gap-2">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" class="permission-item mt-1 size-4" @checked($record && DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')->where('role_permissions.role_id', $record->id)->where('permissions.name', $permission->name)->exists())>
                                                    <span>{{ $permission->label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div data-field="{{ $name }}" class="grid gap-2 text-sm font-bold {{ ($field['type'] ?? null) === 'textarea' ? 'md:col-span-2' : '' }}" @if($module === 'expenses' && $name === 'bank_account_id') data-expense-bank-field @endif>
                            {{ $field['label'] }}
                            @if(($field['type'] ?? null) === 'textarea')
                                <textarea class="form-control min-h-28" name="{{ $name }}">{{ $fieldValue ?? '' }}</textarea>
                            @elseif(($field['type'] ?? null) === 'select')
                                <div class="{{ $module === 'products' && $name === 'category_id' ? 'flex gap-2' : '' }}">
                                    <select class="form-control {{ $module === 'products' && $name === 'category_id' ? 'flex-1' : '' }}" name="{{ $name }}">
                                        <option value="">Select {{ strtolower($field['label']) }}</option>
                                        @foreach($lookups[$field['source']] ?? [] as $option)
                                            <option value="{{ $option->id }}" @selected($fieldValue == $option->id)>{{ $option->name ?? $option->number }}</option>
                                        @endforeach
                                    </select>
                                    @if($module === 'products' && $name === 'category_id')
                                        <button type="button" class="grid min-h-14 w-14 shrink-0 place-items-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 transition hover:border-blue-300 hover:bg-blue-100" data-category-modal-open aria-label="Add category">
                                            <x-lucide name="circle-plus" class="size-5" />
                                        </button>
                                    @endif
                                </div>
                            @elseif(($field['type'] ?? null) === 'select_static')
                                <select class="form-control" name="{{ $name }}">
                                    @foreach($field['options'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($fieldValue ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @elseif(($field['type'] ?? null) === 'boolean')
                                <label class="inline-flex cursor-pointer items-center gap-3">
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only toggle-switch" @checked(old($name, $record->{$name} ?? true))>
                                    <span class="relative h-7 w-[3.25rem] rounded-full bg-red-500 shadow-sm transition after:absolute after:left-1 after:top-1 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-emerald-500 peer-checked:after:translate-x-6 peer-focus-visible:ring-4 peer-focus-visible:ring-blue-100"></span>
                                    <span class="text-sm font-semibold text-slate-600 peer-checked:hidden">Inactive</span>
                                    <span class="hidden text-sm font-semibold text-emerald-700 peer-checked:inline">Active</span>
                                </label>
                            @elseif(($field['type'] ?? null) === 'file')
                                <div class="rounded-2xl border border-slate-200 bg-white p-3">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <input id="file-{{ $name }}" class="sr-only" type="file" name="{{ $name }}" data-file-input data-file-target="file-name-{{ $name }}" data-preview-target="file-preview-{{ $name }}">
                                        <label for="file-{{ $name }}" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-black text-slate-800 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">Choose file</label>
                                        <span id="file-name-{{ $name }}" class="text-sm font-semibold text-slate-500">No file chosen</span>
                                    </div>
                                    @if($record && ! blank($record->{$name} ?? null))
                                        <div id="file-preview-{{ $name }}-wrap" class="mt-4">
                                            <img id="file-preview-{{ $name }}" class="h-28 w-44 rounded-lg border border-slate-100 object-cover shadow-sm" src="{{ asset('storage/'.$record->{$name}) }}" alt="{{ $field['label'] }} preview">
                                        </div>
                                    @else
                                        <div id="file-preview-{{ $name }}-wrap" class="mt-4 hidden">
                                            <img id="file-preview-{{ $name }}" class="h-28 w-44 rounded-lg border border-slate-100 object-cover shadow-sm" src="" alt="{{ $field['label'] }} preview">
                                        </div>
                                    @endif
                                </div>
                            @else
                                <input
                                    class="form-control"
                                    type="{{ $field['type'] === 'password' ? 'password' : ($field['type'] ?? 'text') }}"
                                    name="{{ $name }}"
                                    value="{{ $field['type'] === 'password' ? '' : ($fieldValue ?? '') }}"
                                >
                            @endif
                        </div>
                    @endif
                @endcan
            @endforeach
        </div>
        <div class="flex gap-3 border-t border-slate-200 pt-5">
            <button class="btn-primary" name="save_action" value="save">Save</button>
            @if($module === 'products' && ! $record)
                <button class="btn-secondary border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100" name="save_action" value="add_new">Save and add new product</button>
            @endif
            <a class="btn-secondary" href="{{ route('backoffice.modules.index', $module) }}">Cancel</a>
        </div>
    </form>

    @if($module === 'products')
        <div class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-category-modal>
            <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-md" data-category-modal-close></div>
            <form class="relative z-10 w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-2xl" data-category-form action="{{ route('backoffice.modules.store', 'categories') }}">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Add Category</h2>
                    </div>
                    <button type="button" class="grid size-9 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900" data-category-modal-close aria-label="Close category popup">
                        <x-lucide name="x" class="size-4" />
                    </button>
                </div>
                <div class="grid gap-4">
                    <label class="grid gap-2 text-sm font-bold">
                        Category Name
                        <input class="form-control" name="name" required autocomplete="off">
                    </label>
                    <label class="grid gap-2 text-sm font-bold">
                        Description
                        <textarea class="form-control min-h-24" name="description"></textarea>
                    </label>
                    <input type="hidden" name="is_active" value="1">
                    <p class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700" data-category-error></p>
                    <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                        <button type="button" class="btn-secondary" data-category-modal-close>Cancel</button>
                        <button type="submit" class="btn-primary" data-category-submit>Save</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form.pos-card');
            if (!form) return;

            const maintain = form.querySelector('[name="maintain_stock"][type="checkbox"]');
            const stockWrapper = form.querySelector('[data-field="stock_quantity"]');
            const alertWrapper = form.querySelector('[data-field="alert_quantity"]');

            function updateStockVisibility() {
                const show = !!(maintain && maintain.checked);
                [stockWrapper, alertWrapper].forEach((wrapper) => {
                    if (!wrapper) return;
                    wrapper.style.display = show ? '' : 'none';
                    wrapper.querySelectorAll('input, select, textarea').forEach((input) => {
                        input.disabled = !show;
                    });
                });
            }

            updateStockVisibility();
            if (maintain) maintain.addEventListener('change', updateStockVisibility);

            // SKU <-> Barcode sync and auto-generate when empty on submit
            const sku = form.querySelector('[name="sku"]');
            const barcode = form.querySelector('[name="barcode"]');

            if (sku && barcode) {
                sku.addEventListener('input', () => { barcode.value = sku.value; });
                barcode.addEventListener('input', () => { sku.value = barcode.value; });
            }

            document.querySelectorAll('[data-file-input]').forEach((input) => {
                input.addEventListener('change', () => {
                    const file = input.files && input.files[0] ? input.files[0] : null;
                    const fileName = document.getElementById(input.dataset.fileTarget);
                    const preview = document.getElementById(input.dataset.previewTarget);
                    const previewWrap = preview ? document.getElementById(`${input.dataset.previewTarget}-wrap`) : null;

                    if (fileName) fileName.textContent = file ? file.name : 'No file chosen';

                    if (file && preview) {
                        preview.src = URL.createObjectURL(file);
                        preview.onload = () => URL.revokeObjectURL(preview.src);
                        previewWrap?.classList.remove('hidden');
                    }
                });
            });

            const paymentMethod = form.querySelector('[name="payment_method"]');
            const expenseBankField = form.querySelector('[data-expense-bank-field]');

            function updateExpenseBankField() {
                if (!paymentMethod || !expenseBankField) return;
                const show = paymentMethod.value === 'bank';
                expenseBankField.style.display = show ? '' : 'none';
                const input = expenseBankField.querySelector('select');
                if (input) input.disabled = !show;
            }

            updateExpenseBankField();
            paymentMethod?.addEventListener('change', updateExpenseBankField);

            const categoryModal = document.querySelector('[data-category-modal]');
            const categoryForm = document.querySelector('[data-category-form]');
            const categorySelect = form.querySelector('[name="category_id"]');
            const categoryError = document.querySelector('[data-category-error]');
            const categorySubmit = document.querySelector('[data-category-submit]');

            function openCategoryModal() {
                if (!categoryModal || !categoryForm) return;
                categoryForm.reset();
                if (categoryError) {
                    categoryError.textContent = '';
                    categoryError.classList.add('hidden');
                }
                categoryModal.classList.remove('hidden');
                categoryModal.classList.add('flex');
                categoryForm.querySelector('[name="name"]')?.focus();
            }

            function closeCategoryModal() {
                categoryModal?.classList.add('hidden');
                categoryModal?.classList.remove('flex');
            }

            document.querySelectorAll('[data-category-modal-open]').forEach((button) => {
                button.addEventListener('click', openCategoryModal);
            });

            document.querySelectorAll('[data-category-modal-close]').forEach((button) => {
                button.addEventListener('click', closeCategoryModal);
            });

            categoryForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!categorySelect) return;

                if (categoryError) {
                    categoryError.textContent = '';
                    categoryError.classList.add('hidden');
                }

                if (categorySubmit) {
                    categorySubmit.disabled = true;
                    categorySubmit.textContent = 'Saving...';
                }

                try {
                    const response = await fetch(categoryForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: new FormData(categoryForm),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const message = payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Category could not be saved.';
                        throw new Error(message);
                    }

                    const category = payload.category;
                    if (category) {
                        const option = new Option(category.name, category.id, true, true);
                        categorySelect.add(option);
                        categorySelect.value = category.id;
                    }

                    closeCategoryModal();
                } catch (error) {
                    if (categoryError) {
                        categoryError.textContent = error.message || 'Category could not be saved.';
                        categoryError.classList.remove('hidden');
                    }
                } finally {
                    if (categorySubmit) {
                        categorySubmit.disabled = false;
                        categorySubmit.textContent = 'Save';
                    }
                }
            });

            form.addEventListener('submit', function () {
                function generateCode() {
                    return 'P' + Math.random().toString(36).slice(2, 9).toUpperCase();
                }

                if (sku && barcode) {
                    if (!sku.value.trim()) {
                        const val = generateCode();
                        sku.value = val;
                        barcode.value = val;
                    } else {
                        // ensure barcode mirrors sku
                        barcode.value = sku.value;
                    }
                }
            });
        });
        </script>
    @endpush
</x-layouts.app>
