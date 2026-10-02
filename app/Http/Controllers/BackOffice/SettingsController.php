<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateKitchenPinRequest;
use App\Models\ActivityLog;
use App\Services\KitchenDisplayAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorize('settings.view');

        $sections = $this->sections();
        $sectionKey = $this->sectionKey($request->string('section')->toString(), $sections);

        return view('settings.edit', [
            'sectionKey' => $sectionKey,
            'section' => $sections[$sectionKey],
            'sections' => $sections,
            'settings' => DB::table('settings')->where('group', '!=', 'kitchen')->get()->keyBy(fn ($item) => $item->group.'.'.$item->key),
            'uploadMaxMegabytes' => $this->uploadMaxMegabytes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.update');

        $sections = $this->sections();
        $sectionKey = $this->sectionKey($request->input('section'), $sections);
        $uploadMaxMegabytes = $this->uploadMaxMegabytes();
        $uploadMaxKilobytes = $uploadMaxMegabytes * 1024;

        $request->validate(
            [
                'settings' => ['array'],
                'files' => ['array'],
                'files.*.*' => ['nullable', File::image()->max($uploadMaxKilobytes)],
            ],
            [
                'files.*.*.uploaded' => "The selected image is too large or could not be uploaded. Please choose a JPG, PNG, GIF, BMP, or WEBP image up to {$uploadMaxMegabytes} MB.",
                'files.*.*.image' => 'The selected file must be an image: JPG, PNG, GIF, BMP, or WEBP.',
                'files.*.*.max' => "The selected image must not be larger than {$uploadMaxMegabytes} MB.",
            ],
            [
                'files.business.logo' => 'business logo',
                'files.pos.qr_code_image' => 'QR code image',
            ]
        );

        foreach ($request->input('settings', []) as $group => $values) {
            if ($group === 'kitchen' || ! is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                $this->persistSetting((string) $group, (string) $key, (string) $value);
            }
        }

        foreach ($request->file('files', []) as $group => $files) {
            if ($group === 'kitchen' || ! is_array($files)) {
                continue;
            }

            foreach ($files as $key => $file) {
                if ($file === null) {
                    continue;
                }

                $this->persistSetting((string) $group, (string) $key, $file->store('settings', 'public'));
            }
        }

        ActivityLog::record('update', 'settings', 'Settings updated.');

        return redirect()->route('settings.edit', ['section' => $sectionKey])->with('status', 'Settings updated.');
    }

    public function updateKitchenPin(UpdateKitchenPinRequest $request, KitchenDisplayAccess $access): RedirectResponse
    {
        $access->updatePin($request->validated('pin'));
        ActivityLog::record('update', 'settings', 'Kitchen display PIN changed.');

        return redirect()->route('settings.edit', ['section' => 'kitchen'])
            ->with('status', 'Kitchen PIN updated. Kitchen displays must enter the new PIN.');
    }

    /**
     * @return array<string, array{label: string, groups: array<int, string>}>
     */
    private function sections(): array
    {
        return [
            'business' => [
                'label' => 'Business Info',
                'groups' => ['business'],
            ],
            'general' => [
                'label' => 'General Settings',
                'groups' => ['currency', 'date', 'display', 'stock'],
            ],
            'invoice' => [
                'label' => 'Invoice Settings',
                'groups' => ['invoice'],
            ],
            'pos' => [
                'label' => 'POS Settings',
                'groups' => ['pos'],
            ],
            'barcode' => [
                'label' => 'Barcode Settings',
                'groups' => ['barcode'],
            ],
            'kitchen' => [
                'label' => 'Kitchen Display',
                'groups' => [],
            ],
        ];
    }

    /**
     * @param  array<string, array{label: string, groups: array<int, string>}>  $sections
     */
    private function sectionKey(?string $sectionKey, array $sections): string
    {
        if (is_string($sectionKey) && array_key_exists($sectionKey, $sections)) {
            return $sectionKey;
        }

        return array_key_first($sections) ?: 'business';
    }

    private function persistSetting(string $group, string $key, string $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'key' => $key],
            [
                'value' => $value,
                'type' => $this->isBooleanSetting($group, $key) ? 'boolean' : 'string',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function isBooleanSetting(string $group, string $key): bool
    {
        return in_array($group.'.'.$key, [
            'display.sidebar_collapsed',
            'stock.default_maintain_stock',
            'stock.low_stock_alert',
            'stock.allow_negative_stock',
            'invoice.show_logo',
            'invoice.show_customer',
            'invoice.show_waiter',
            'invoice.show_table',
            'pos.enable_card_fee',
            'pos.enable_waiter_incentive',
            'pos.allow_due_sale',
            'pos.allow_due_walk_in',
            'pos.enable_print_before_payment',
            'pos.enable_service_charge',
            'barcode.auto_generate',
        ], true);
    }

    private function uploadMaxMegabytes(): int
    {
        $uploadMax = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $postMax = $this->iniBytes((string) ini_get('post_max_size'));
        $bytes = min($uploadMax, $postMax, 20 * 1024 * 1024);

        return max(1, (int) floor($bytes / 1024 / 1024));
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
