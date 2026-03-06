<?php

namespace Locpx\MoralisTracker\Console\Commands;

use Illuminate\Console\Command;
use Locpx\MoralisTracker\Models\TrackedAddress;

class AddTrackedAddress extends Command
{
    protected $signature = 'moralis:add-address
                            {address : BSC wallet address to track}
                            {--label= : Optional human-readable label}';

    protected $description = 'Add a BSC address to the tracked_addresses table';

    public function handle(): int
    {
        $address = strtolower($this->argument('address'));
        $label   = $this->option('label');

        if (!preg_match('/^0x[0-9a-f]{40}$/', $address)) {
            $this->error("Invalid BSC address: {$address}");
            return self::FAILURE;
        }

        $tracked = TrackedAddress::updateOrCreate(
            ['address' => $address],
            ['label' => $label, 'is_active' => true]
        );

        $action = $tracked->wasRecentlyCreated ? 'Added' : 'Updated';
        $this->info("{$action} address: {$address}" . ($label ? " ({$label})" : ''));

        return self::SUCCESS;
    }
}
