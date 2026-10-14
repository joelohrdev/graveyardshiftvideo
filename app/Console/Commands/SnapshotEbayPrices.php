<?php

namespace App\Console\Commands;

use App\Jobs\SnapshotWatchPrices;
use App\Models\Watch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ebay:snapshot-prices')]
#[Description('Queue a daily eBay price snapshot for every active want-list entry')]
class SnapshotEbayPrices extends Command
{
    public function handle(): int
    {
        $count = 0;

        Watch::query()->active()->with('movie')->lazyById()->each(function (Watch $watch) use (&$count): void {
            SnapshotWatchPrices::dispatch($watch);
            $count++;
        });

        $this->components->info("Queued {$count} eBay price snapshots.");

        return self::SUCCESS;
    }
}
