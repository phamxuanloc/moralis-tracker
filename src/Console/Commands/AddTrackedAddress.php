<?php

namespace Locpx\MoralisTracker\Console\Commands;

use Illuminate\Console\Command;
use Locpx\MoralisTracker\Models\TrackedAddress;

class AddTrackedAddress extends Command
{
    protected $signature = 'moralis:add-address
                            {address : Wallet address to track (0x...)}
                            {--chain= : Chain to track on (bsc, eth, polygon, etc.). Defaults to MORALIS_CHAIN}
                            {--label= : Optional human-readable label}';

    protected $description = 'Add a wallet address to the tracked_addresses table for a specific chain';

    public function handle(): int
    {
        $address = strtolower($this->argument('address'));
        $chain   = $this->option('chain') ?: config('moralis.default_chain', 'bsc');
        $label   = $this->option('label');

        if (!preg_match('/^0x[0-9a-f]{40}$/', $address)) {
            $this->error("Invalid address: {$address}");
            return self::FAILURE;
        }

        if (!array_key_exists($chain, config('moralis.chains', []))) {
            $this->error("Unknown chain: {$chain}. Supported: " . implode(', ', array_keys(config('moralis.chains', []))));
            return self::FAILURE;
        }

        $tracked = TrackedAddress::updateOrCreate(
            ['address' => $address, 'chain' => $chain],
            ['label' => $label, 'is_active' => true]
        );

        $action = $tracked->wasRecentlyCreated ? 'Added' : 'Updated';
        $this->info("{$action}: {$address} on {$chain}" . ($label ? " ({$label})" : ''));

        return self::SUCCESS;
    }
}
