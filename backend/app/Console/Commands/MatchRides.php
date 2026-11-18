<?php

namespace App\Console\Commands;

use App\Services\MatchingService;
use Illuminate\Console\Command;

class MatchRides extends Command
{
    protected $signature = 'rides:match {--mode= : bike or car_rickshaw (default: both)}';

    protected $description = 'Match waiting ride requests into groups and close expired ones';

    public function handle(MatchingService $matching): int
    {
        $mode = $this->option('mode') ?: null;
        if ($mode !== null && ! in_array($mode, ['bike', 'car_rickshaw'], true)) {
            $this->error('Mode must be bike or car_rickshaw.');

            return self::FAILURE;
        }

        $s = $matching->run($mode);
        $this->info("Expired: {$s['expired']}  Groups created: {$s['groups_created']}  Requests matched: {$s['requests_matched']}");

        return self::SUCCESS;
    }
}
