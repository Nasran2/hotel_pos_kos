<form method="GET" class="page-filter-bar" data-date-filter-form>
    @foreach(request()->except(['range', 'from', 'to', 'page']) as $name => $value)
        @if(is_scalar($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach
    <label class="page-filter-field">
        <span>Range</span>
        <select name="range" class="form-control min-w-44" data-date-range required>
            @foreach(config('hotelpos.date_filters') as $value => $label)
                <option value="{{ $value }}" @selected((request('range') ?: 'today') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="page-filter-field">
        <span>From Date</span>
        <input class="form-control" type="date" name="from" value="{{ request('from', today()->toDateString()) }}" data-date-from required>
    </label>
    <label class="page-filter-field">
        <span>To Date</span>
        <input class="form-control" type="date" name="to" value="{{ request('to', today()->toDateString()) }}" data-date-to required>
    </label>
    <button class="btn-primary page-filter-apply" type="submit">
        <x-lucide name="sliders-horizontal" class="size-4" />
        <span>Apply Filter</span>
    </button>
</form>
