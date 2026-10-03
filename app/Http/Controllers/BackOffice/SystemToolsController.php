<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\Console\Output\BufferedOutput;
use ZipArchive;

class SystemToolsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSystemTools($request);

        $settings = $this->settings();

        return view('backoffice.system-tools', [
            'maintenanceEnabled' => $this->booleanSetting($settings, 'system_tools.maintenance_enabled'),
            'commandPresets' => $this->commandPresets(),
            'sequenceCommands' => $this->sequenceCommands(),
            'executionResult' => $this->executionResult($settings),
            'protectedPaths' => $this->protectedPaths(),
            'currentUserId' => $request->user()?->id,
        ]);
    }

    public function toggleMaintenance(Request $request): RedirectResponse
    {
        $this->authorizeSystemTools($request);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];

        $this->persistSetting('system_tools', 'maintenance_enabled', $enabled ? '1' : '0', 'boolean');

        ActivityLog::record(
            'update',
            'system_tools',
            $enabled ? 'System maintenance mode enabled.' : 'System maintenance mode disabled.',
            ['enabled' => $enabled]
        );

        return redirect()->route('system-tools.index')->with('status', $enabled ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.');
    }

    public function runCommand(Request $request): RedirectResponse
    {
        $this->authorizeSystemTools($request);

        $validated = $request->validate([
            'command' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9\s:_=.,\/\-\[\]@]+$/'],
            'command_label' => ['nullable', 'string', 'max:255'],
        ]);

        $command = trim($validated['command']);
        $label = (string) ($validated['command_label'] ?? 'Custom Artisan Command');
        $startedAt = now();
        $result = $this->executeCommand($command);

        $this->storeExecutionResult($label, $command, $startedAt->toDateTimeString(), now()->toDateTimeString(), $result);

        ActivityLog::record(
            $result['exit_code'] === 0 ? 'run' : 'failed',
            'system_tools',
            $label.' executed.',
            ['command' => $command, 'exit_code' => $result['exit_code']]
        );

        return redirect()->route('system-tools.index')->with('status', $this->commandStatusMessage($label, $result));
    }

    public function runSequence(): RedirectResponse
    {
        $this->authorizeSystemTools(request());

        $startedAt = now();
        $output = [];
        $exitCode = 0;

        foreach ($this->sequenceCommands() as $command) {
            $result = $this->executeCommand($command['command']);

            $output[] = '$ '.$command['command'];
            $output[] = trim($result['output']) !== '' ? $result['output'] : 'Command completed with no output.';
            $output[] = 'Exit code: '.$result['exit_code'];
            $output[] = '';

            if ($result['exit_code'] !== 0) {
                $exitCode = $result['exit_code'];
                break;
            }
        }

        $result = [
            'output' => trim(implode(PHP_EOL, $output)),
            'exit_code' => $exitCode,
        ];

        $this->storeExecutionResult('Full Maintenance Sequence', 'maintenance-sequence', $startedAt->toDateTimeString(), now()->toDateTimeString(), $result);

        ActivityLog::record(
            $exitCode === 0 ? 'run' : 'failed',
            'system_tools',
            'Full maintenance sequence executed.',
            ['exit_code' => $exitCode]
        );

        return redirect()->route('system-tools.index')->with('status', $exitCode === 0 ? 'Maintenance sequence completed.' : 'Maintenance sequence stopped after a command failed.');
    }

    public function uploadUpgrade(Request $request): RedirectResponse
    {
        $this->authorizeSystemTools($request);

        $validated = $request->validate([
            'package' => ['required', File::types(['zip'])->max('200mb')],
        ]);

        $package = $validated['package'];
        $storedPath = $package->storeAs('system-tools', 'upgrade-'.now()->format('YmdHis').'.zip', 'local');
        $zipPath = Storage::disk('local')->path($storedPath);

        try {
            $summary = $this->extractUpgradePackage($zipPath);
            $message = sprintf('Upgrade package applied. %d files updated, %d files skipped.', $summary['updated'], $summary['skipped']);

            $this->storeExecutionResult('System Upgrade', 'upgrade-package', now()->toDateTimeString(), now()->toDateTimeString(), [
                'output' => $message.PHP_EOL.$summary['details'],
                'exit_code' => 0,
            ]);

            ActivityLog::record(
                'update',
                'system_tools',
                'System upgrade package applied.',
                ['updated' => $summary['updated'], 'skipped' => $summary['skipped']]
            );

            return redirect()->route('system-tools.index')->with('status', $message);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }

    public function toggleUserStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSystemTools($request);

        abort_if($request->user()?->is($user), 422, 'You cannot change your own account status here.');

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        ActivityLog::record(
            'update',
            'system_tools',
            $user->is_active ? 'User activated from system tools.' : 'User deactivated from system tools.',
            ['user_id' => $user->id, 'is_active' => $user->is_active]
        );

        return redirect()->route('system-tools.index')->with('status', $user->is_active ? 'User activated.' : 'User deactivated.');
    }

    /**
     * @return array<int, array{label: string, description: string, command: string, tone: string, danger?: bool}>
     */
    private function commandPresets(): array
    {
        return [
            ['label' => 'Optimize Clear', 'description' => 'Clear config, route, view, and event caches.', 'command' => 'optimize:clear', 'tone' => 'blue'],
            ['label' => 'Cache Clear', 'description' => 'Clear the application cache store.', 'command' => 'cache:clear', 'tone' => 'blue'],
            ['label' => 'Config Cache', 'description' => 'Rebuild the cached configuration file.', 'command' => 'config:cache', 'tone' => 'blue'],
            ['label' => 'Route Cache', 'description' => 'Rebuild the route cache for faster bootstrap.', 'command' => 'route:cache', 'tone' => 'blue'],
            ['label' => 'View Cache', 'description' => 'Compile and cache Blade templates.', 'command' => 'view:cache', 'tone' => 'blue'],
            ['label' => 'Event Cache', 'description' => 'Cache discovered events and listeners.', 'command' => 'event:cache', 'tone' => 'blue'],
            ['label' => 'Storage Link', 'description' => 'Create the public storage symlink if needed.', 'command' => 'storage:link', 'tone' => 'green'],
            ['label' => 'Run Migrations', 'description' => 'Apply any pending database migrations.', 'command' => 'migrate --force', 'tone' => 'red', 'danger' => true],
            ['label' => 'Migration Status', 'description' => 'Show the current migration status.', 'command' => 'migrate:status', 'tone' => 'blue'],
            ['label' => 'Queue Restart', 'description' => 'Gracefully restart queue workers.', 'command' => 'queue:restart', 'tone' => 'blue'],
            ['label' => 'Schedule Run', 'description' => 'Run due scheduled tasks once.', 'command' => 'schedule:run', 'tone' => 'blue'],
            ['label' => 'About', 'description' => 'Show framework and environment details.', 'command' => 'about', 'tone' => 'blue'],
        ];
    }

    /**
     * @return array<int, array{label: string, command: string}>
     */
    private function sequenceCommands(): array
    {
        return [
            ['label' => 'Optimize Clear', 'command' => 'optimize:clear'],
            ['label' => 'Cache Clear', 'command' => 'cache:clear'],
            ['label' => 'Config Cache', 'command' => 'config:cache'],
            ['label' => 'Route Cache', 'command' => 'route:cache'],
            ['label' => 'View Cache', 'command' => 'view:cache'],
            ['label' => 'Event Cache', 'command' => 'event:cache'],
            ['label' => 'Storage Link', 'command' => 'storage:link'],
        ];
    }

    /**
     * @return array<string, object>
     */
    private function settings(): array
    {
        return DB::table('settings')->get()->keyBy(fn ($setting) => $setting->group.'.'.$setting->key)->all();
    }

    /**
     * @param  array<string, object>  $settings
     */
    private function booleanSetting(array $settings, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        return filter_var($settings[$key]->value ?? $default, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, object>  $settings
     * @return array{label: string, command: string, output: string, exit_code: int, started_at: string, finished_at: string}|null
     */
    private function executionResult(array $settings): ?array
    {
        $encoded = $settings['system_tools.last_execution']->value ?? null;

        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $decoded = json_decode($encoded, true);

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'label' => (string) ($decoded['label'] ?? 'Custom Artisan Command'),
            'command' => (string) ($decoded['command'] ?? ''),
            'output' => (string) ($decoded['output'] ?? ''),
            'exit_code' => (int) ($decoded['exit_code'] ?? 0),
            'started_at' => (string) ($decoded['started_at'] ?? now()->toDateTimeString()),
            'finished_at' => (string) ($decoded['finished_at'] ?? now()->toDateTimeString()),
        ];
    }

    /**
     * @return array{output: string, exit_code: int}
     */
    private function executeCommand(string $command): array
    {
        $tokens = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return ['output' => 'No command was provided.', 'exit_code' => 1];
        }

        if ($tokens[0] === 'php' && ($tokens[1] ?? null) === 'artisan') {
            $tokens = array_slice($tokens, 2);
        }

        $name = array_shift($tokens);

        if (! is_string($name) || $name === '') {
            return ['output' => 'No command was provided.', 'exit_code' => 1];
        }

        if ($name === 'storage:link') {
            return $this->createStorageLinks();
        }

        [$arguments, $options] = $this->artisanParameters($tokens);
        $output = new BufferedOutput;

        try {
            $exitCode = Artisan::call($name, array_merge($arguments, $options), $output);
        } catch (\Throwable $exception) {
            return [
                'output' => $exception->getMessage(),
                'exit_code' => 1,
            ];
        }

        return [
            'output' => trim($output->fetch()),
            'exit_code' => $exitCode,
        ];
    }

    /**
     * @return array{output: string, exit_code: int}
     */
    private function createStorageLinks(): array
    {
        $this->ensureRuntimeDirectories();

        $links = config('filesystems.links', [
            public_path('storage') => storage_path('app/public'),
        ]);

        $output = [];
        $exitCode = 0;

        foreach ($links as $link => $target) {
            $link = (string) $link;
            $target = (string) $target;

            try {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                if (is_link($link)) {
                    $output[] = 'The ['.$link.'] link already exists.';

                    continue;
                }

                if (file_exists($link) && ! is_dir($link)) {
                    $output[] = 'Unable to create link. A file already exists at ['.$link.'].';
                    $exitCode = 1;

                    continue;
                }

                if (! is_dir(dirname($link))) {
                    mkdir(dirname($link), 0755, true);
                }

                if (! file_exists($link) && $this->trySymlink($target, $link)) {
                    $output[] = 'The ['.$link.'] link has been connected to ['.$target.'].';

                    continue;
                }

                if (! is_dir($link)) {
                    mkdir($link, 0755, true);
                }

                $this->copyDirectory($target, $link);
                $output[] = 'Symlinks are not available on this server. A public storage directory was created and synced at ['.$link.'].';
            } catch (\Throwable $exception) {
                $output[] = 'Failed to prepare ['.$link.']: '.$exception->getMessage();
                $exitCode = 1;
            }
        }

        return [
            'output' => implode(PHP_EOL, $output),
            'exit_code' => $exitCode,
        ];
    }

    private function trySymlink(string $target, string $link): bool
    {
        if (! function_exists('symlink')) {
            return false;
        }

        try {
            return @symlink($target, $link);
        } catch (\Throwable) {
            return false;
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($source)) {
            return;
        }

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            $target = $destination.DIRECTORY_SEPARATOR.$items->getSubPathName();

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                continue;
            }

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            copy($item->getPathname(), $target);
        }
    }

    private function ensureRuntimeDirectories(): void
    {
        foreach ([
            storage_path('app/public'),
            storage_path('app/private'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/testing'),
            storage_path('framework/views'),
            storage_path('logs'),
            public_path('storage'),
            base_path('bootstrap/cache'),
        ] as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
        }
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function artisanParameters(array $tokens): array
    {
        $arguments = [];
        $options = [];

        foreach ($tokens as $token) {
            if (Str::startsWith($token, '--')) {
                $option = substr($token, 2);

                if (str_contains($option, '=')) {
                    [$key, $value] = explode('=', $option, 2);
                    $options['--'.$key] = $value;

                    continue;
                }

                $options['--'.$option] = true;

                continue;
            }

            if (Str::startsWith($token, '-')) {
                $option = substr($token, 1);

                if (str_contains($option, '=')) {
                    [$key, $value] = explode('=', $option, 2);
                    $options['-'.$key] = $value;

                    continue;
                }

                $options['-'.$option] = true;

                continue;
            }

            $arguments[] = $token;
        }

        if ($arguments !== []) {
            $options = array_merge($options, ['--' => $arguments]);
        }

        return [$arguments, $options];
    }

    /**
     * @param  array{output: string, exit_code: int}  $result
     */
    private function storeExecutionResult(string $label, string $command, string $startedAt, string $finishedAt, array $result): void
    {
        $payload = [
            'label' => $label,
            'command' => $command,
            'output' => Str::limit($result['output'], 12000, ''),
            'exit_code' => $result['exit_code'],
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ];

        $this->persistSetting('system_tools', 'last_execution', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    private function commandStatusMessage(string $label, array $result): string
    {
        if ($result['exit_code'] === 0) {
            return $label.' completed successfully.';
        }

        return $label.' failed with exit code '.$result['exit_code'].'.';
    }

    /**
     * @return array<int, string>
     */
    private function protectedPaths(): array
    {
        return [
            '.env',
            'storage/',
            'public/storage/',
            'bootstrap/cache/',
            'node_modules/',
            '.git/',
        ];
    }

    private function authorizeSystemTools(Request $request): void
    {
        abort_unless($request->user()?->hasRole('Developer'), 403);
        abort_if(config('demo.enabled') && ! $request->isMethod('GET'), 403, 'System tools cannot modify the demo installation.');
    }

    /**
     * @return array{updated: int, skipped: int, details: string}
     */
    private function extractUpgradePackage(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Unable to open the uploaded ZIP package.');
        }

        $updated = 0;
        $skipped = 0;
        $details = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    continue;
                }

                $relativePath = str_replace('\\', '/', (string) $stat['name']);
                $normalizedPath = ltrim($relativePath, '/');

                if ($normalizedPath === '' || str_contains($normalizedPath, '../') || $this->pathIsProtected($normalizedPath)) {
                    $skipped++;

                    continue;
                }

                if (str_ends_with($normalizedPath, '/')) {
                    $targetDirectory = base_path($normalizedPath);

                    if (! is_dir($targetDirectory)) {
                        mkdir($targetDirectory, 0755, true);
                    }

                    continue;
                }

                $targetPath = base_path($normalizedPath);
                $targetDirectory = dirname($targetPath);

                if (! is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0755, true);
                }

                $sourceStream = $zip->getStream((string) $stat['name']);

                if ($sourceStream === false) {
                    $skipped++;

                    continue;
                }

                $targetStream = fopen($targetPath, 'wb');

                if ($targetStream === false) {
                    fclose($sourceStream);
                    $skipped++;

                    continue;
                }

                stream_copy_to_stream($sourceStream, $targetStream);
                fclose($sourceStream);
                fclose($targetStream);

                $updated++;
                $details[] = 'Updated: '.$normalizedPath;
            }
        } finally {
            $zip->close();
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'details' => implode(PHP_EOL, $details),
        ];
    }

    private function pathIsProtected(string $path): bool
    {
        foreach ($this->protectedPaths() as $protectedPath) {
            if ($path === $protectedPath || str_starts_with($path, $protectedPath)) {
                return true;
            }
        }

        return false;
    }

    private function persistSetting(string $group, string $key, string $value, string $type = 'string'): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
