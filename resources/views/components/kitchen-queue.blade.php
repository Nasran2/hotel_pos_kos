@props(['board' => false, 'kiosk' => false])
<section class="kitchen-display {{ $board ? 'kitchen-board' : 'kitchen-strip' }}" data-kitchen-display
    data-feed-url="{{ route($kiosk ? 'kod.feed' : 'kitchen.feed') }}" data-mode="{{ $board ? 'board' : 'pos' }}" data-kiosk="{{ $kiosk ? '1' : '0' }}"
    data-can-update="{{ ($kiosk || auth()->user()?->can('kitchen.update')) ? '1' : '0' }}"
    data-can-serve="{{ (!$kiosk && auth()->user()?->can('pos.send_kitchen')) ? '1' : '0' }}"
    data-can-request-stop="{{ (!$kiosk && auth()->user()?->can('pos.send_kitchen')) ? '1' : '0' }}"
    data-can-resume="{{ (!$kiosk && auth()->user()?->can('pos.resume_hold_order')) ? '1' : '0' }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-blue-50 text-blue-700"><x-lucide name="cooking-pot" class="size-5" /></span>
            <div>
                <h2 class="text-base font-bold text-slate-900">{{ $board ? 'Orders in service' : 'Kitchen orders' }}</h2>
                <p class="text-xs text-slate-500" data-kitchen-sync role="status">Connecting to kitchen…</p>
            </div>
        </div>
        <div class="flex items-center gap-3 text-xs font-semibold">
            @if(!$board)
                @can('kitchen.view')<a class="text-blue-700 hover:underline" href="{{ route('kod.index') }}">Open kitchen display ↗</a>@endcan
            @endif
            <button type="button" class="btn-secondary !min-h-8 !px-3 !py-1 !text-xs" data-kitchen-refresh>Refresh</button>
        </div>
    </div>
    <p class="hidden mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700" data-kitchen-error role="alert"></p>
    @if($board)
        <div class="kitchen-board-toolbar flex flex-wrap items-center justify-between gap-3">
            <p>Start the next ticket, then mark it ready for collection.</p>
            <label class="flex items-center gap-2 text-sm font-semibold">Show
                <select data-kitchen-filter aria-label="Filter kitchen orders">
                    <option value="all">All orders</option><option value="queued">In queue</option>
                    <option value="preparing">Preparing</option><option value="ready">Ready to serve</option>
                    <option value="stopped">Stopped orders</option>
                </select>
            </label>
        </div>
    @endif
    <div class="kitchen-attention" data-kitchen-attention hidden>
        <div class="kitchen-lane kitchen-lane-stop_requested" data-kitchen-stop-requests hidden>
            <div class="kitchen-lane-heading"><span class="kitchen-dot"></span><h3>Stop requests</h3><span class="kitchen-count" data-kitchen-count="stop_requested">0</span></div>
            <div class="kitchen-order-list" data-kitchen-list="stop_requested"></div>
        </div>
    </div>
    <div class="kitchen-lanes" data-kitchen-active-lanes>
        @foreach(['queued' => 'In queue', 'preparing' => 'Preparing', 'ready' => 'Ready to serve'] as $status => $label)
            <div class="kitchen-lane kitchen-lane-{{ $status }}">
                <div class="kitchen-lane-heading"><span class="kitchen-dot"></span><h3>{{ $label }}</h3><span class="kitchen-count" data-kitchen-count="{{ $status }}">0</span></div>
                <div class="kitchen-order-list" data-kitchen-list="{{ $status }}"><p class="kitchen-empty">Loading orders…</p></div>
            </div>
        @endforeach
    </div>
    <div class="kitchen-stopped-section kitchen-lane kitchen-lane-stopped" data-kitchen-stopped hidden>
        <div class="kitchen-lane-heading"><span class="kitchen-dot"></span><h3>Stopped orders</h3><span class="kitchen-count" data-kitchen-count="stopped">0</span></div>
        <div class="kitchen-order-list" data-kitchen-list="stopped"></div>
    </div>
    <p class="sr-only" data-kitchen-announcement aria-live="polite" role="status"></p>
    <dialog class="kitchen-dialog" data-kitchen-dialog aria-labelledby="kitchen-dialog-title">
        <div data-kitchen-dialog-content></div>
    </dialog>
</section>
