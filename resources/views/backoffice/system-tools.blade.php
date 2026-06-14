<x-layouts.app heading="System Tools" title="System Tools" :show-date-filter="false">
    @php
        $protectedPathsText = collect($protectedPaths)->join(', ');
    @endphp

    <div class="space-y-5">
        <section class="pos-card overflow-hidden border border-slate-200/80 p-0">
            <div class="flex flex-col gap-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-blue-50 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-blue-600">Developer Dashboard</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">System Maintenance Controls</h2>
                    <p class="mt-1 max-w-2xl text-sm font-semibold text-slate-500">Run trusted Artisan commands, lock the system for maintenance, and upload a ZIP package while preserving runtime folders and uploaded images.</p>
                </div>

                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-black {{ $maintenanceEnabled ? 'text-amber-700' : 'text-emerald-700' }}">
                    <span class="size-2 rounded-full {{ $maintenanceEnabled ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                    {{ $maintenanceEnabled ? 'Maintenance Enabled' : 'Maintenance Disabled' }}
                </div>
            </div>
        </section>

        <section class="pos-card space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Maintenance Mode</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Toggle a full application lock while keeping the developer dashboard available.</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-black {{ $maintenanceEnabled ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                    {{ $maintenanceEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('system-tools.maintenance') }}">
                    @csrf
                    <input type="hidden" name="enabled" value="1">
                    <button class="btn-primary bg-red-600 hover:bg-red-700">Enable Maintenance</button>
                </form>

                <form method="POST" action="{{ route('system-tools.maintenance') }}">
                    @csrf
                    <input type="hidden" name="enabled" value="0">
                    <button class="btn-primary bg-emerald-600 hover:bg-emerald-700">Disable Maintenance</button>
                </form>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Artisan Command Presets</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Run common system operations one click at a time.</p>
                </div>

                <form method="POST" action="{{ route('system-tools.sequence') }}">
                    @csrf
                    <button class="btn-primary bg-indigo-600 hover:bg-indigo-700">Run Full Maintenance Sequence</button>
                </form>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($commandPresets as $preset)
                    <article class="pos-card flex h-full flex-col justify-between gap-4">
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-base font-black text-slate-900">{{ $preset['label'] }}</h4>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ $preset['description'] }}</p>
                                </div>

                                @if(! empty($preset['danger']))
                                    <span class="rounded-full bg-red-50 px-2 py-1 text-[11px] font-black uppercase tracking-wide text-red-600">Danger</span>
                                @endif
                            </div>

                            <code class="mt-3 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">php artisan {{ $preset['command'] }}</code>
                        </div>

                        <form method="POST" action="{{ route('system-tools.commands.run') }}">
                            @csrf
                            <input type="hidden" name="command" value="{{ $preset['command'] }}">
                            <input type="hidden" name="command_label" value="{{ $preset['label'] }}">
                            <button class="btn-secondary min-h-10 {{ ! empty($preset['danger']) ? 'border-red-200 bg-red-600 text-white hover:bg-red-700 hover:text-white' : '' }}">Run Command</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.3fr_.9fr]">
            <article class="pos-card space-y-4">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Custom Artisan Command</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Run any artisan command not listed above.</p>
                </div>

                <form method="POST" action="{{ route('system-tools.commands.run') }}" class="flex flex-col gap-3 sm:flex-row">
                    @csrf
                    <input class="form-control flex-1" name="command" value="{{ old('command', 'migrate --force') }}" placeholder="migrate --force">
                    <button class="btn-primary min-h-11 shrink-0 bg-emerald-600 hover:bg-emerald-700">Run Custom Command</button>
                </form>
            </article>

            <article class="pos-card space-y-4">
                <div>
                    <h3 class="text-xl font-black text-slate-900">System Upgrade</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Upload a ZIP package to update system files. Protected files and runtime folders are skipped automatically.</p>
                </div>

                <form method="POST" action="{{ route('system-tools.upgrade') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input class="form-control" type="file" name="package" accept=".zip,application/zip">
                    <button class="btn-primary w-full bg-indigo-600 hover:bg-indigo-700">Upload and Upgrade</button>
                </form>

                <p class="text-xs font-semibold text-slate-500">Skipped during upgrade: {{ $protectedPathsText }}.</p>
            </article>
        </section>

        <section class="pos-card space-y-4">
            <div>
                <h3 class="text-xl font-black text-slate-900">Execution Results</h3>
                <p class="mt-1 text-sm font-semibold text-slate-500">Most recent operation output.</p>
            </div>

            @if($executionResult)
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-base font-black text-slate-900">{{ $executionResult['label'] }}</h4>
                            <p class="mt-1 text-sm font-semibold text-slate-500">{{ $executionResult['command'] }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-400">Started: {{ $executionResult['started_at'] }} | Ended: {{ $executionResult['finished_at'] }}</p>
                        </div>

                        <span class="rounded-full px-3 py-1 text-xs font-black {{ $executionResult['exit_code'] === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">Exit {{ $executionResult['exit_code'] }}</span>
                    </div>

                    <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100"><code>{{ $executionResult['output'] !== '' ? $executionResult['output'] : 'Command completed with no output.' }}</code></pre>
                </article>
            @else
                <p class="text-sm font-semibold text-slate-500">No command has been executed yet.</p>
            @endif
        </section>
    </div>
</x-layouts.app>