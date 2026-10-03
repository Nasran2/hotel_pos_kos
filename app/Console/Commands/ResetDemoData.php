<?php

namespace App\Console\Commands;

use App\Services\DemoMode;
use Illuminate\Console\Command;

class ResetDemoData extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Restore demo data only when demo mode is enabled and its 15-day reset is due';

    public function handle(DemoMode $demo): int
    {
        $this->info(match ($demo->resetIfDue()) {
            'disabled' => 'Demo mode is disabled. No data changed.',
            'initialized' => 'Demo reset cycle started. First reset is due in 15 days.',
            'waiting' => 'Demo reset is not due. No data changed.',
            'reset' => 'Demo data reset successfully. Next reset is due in 15 days.',
        });

        return self::SUCCESS;
    }
}
