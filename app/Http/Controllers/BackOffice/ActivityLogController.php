<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(\Illuminate\Http\Request $request): View
    {
        $this->authorize('activity_logs.view');

        $range = $request->string('range', 'today')->toString();
        [$from, $to] = match ($range) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'all_time' => [\Carbon\Carbon::create(2000, 1, 1)->startOfDay(), now()->endOfDay()],
            'custom' => [\Carbon\Carbon::parse($request->input('from', today()))->startOfDay(), \Carbon\Carbon::parse($request->input('to', today()))->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };

        return view('backoffice.activity-logs', [
            'logs' => DB::table('activity_logs')
                ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
                ->whereBetween('activity_logs.created_at', [$from, $to])
                ->select('activity_logs.*', 'users.name as user_name')
                ->latest('activity_logs.created_at')
                ->paginate(30)->withQueryString(),
        ]);
    }
}
