@if(config('demo.enabled'))
    <div {{ $attributes->class(['demo-banner flex items-center justify-center gap-2 px-4 py-2 text-center text-xs font-semibold']) }} role="status">
        <span class="rounded bg-white/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">Demo</span>
        <span>This is a demo. All data will reset every 15 days.</span>
    </div>
@endif
