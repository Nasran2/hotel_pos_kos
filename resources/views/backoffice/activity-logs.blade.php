<x-layouts.app heading="Activity Log" title="Activity Log">
    <div class="pos-card p-0">
        <div class="data-table-wrap">
            <table class="data-table min-w-full">
            <thead>
                <tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Module</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">IP</th><th class="px-4 py-3">Date</th></tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                    <tr><td class="px-4 py-3">{{ $log->user_name ?? 'System' }}</td><td class="px-4 py-3">{{ $log->action }}</td><td class="px-4 py-3">{{ $log->module }}</td><td class="px-4 py-3">{{ $log->description }}</td><td class="px-4 py-3">{{ $log->ip_address }}</td><td class="px-4 py-3">{{ $log->created_at }}</td></tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="border-t border-slate-100 px-4 py-3">{{ $logs->links() }}</div>
    </div>
</x-layouts.app>
