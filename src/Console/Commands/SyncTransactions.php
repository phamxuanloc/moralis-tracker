<?php

namespace Locpx\MoralisTracker\Console\Commands;

use Illuminate\Console\Command;
use Locpx\MoralisTracker\Models\TrackedAddress;
use Locpx\MoralisTracker\Services\TransactionSyncService;

class SyncTransactions extends Command
{
    protected $signature = 'moralis:sync
                            {--address= : Sync a specific address (overrides DB list)}
                            {--from-block=0 : Start syncing from this block number}
                            {--type=* : Transaction types to sync (normal, token, nft)}
                            {--fresh : Ignore last_synced_block and sync from --from-block}';

    protected $description = 'Sync BSC transactions via Moralis API and store them in the database';

    public function handle(TransactionSyncService $service): int
    {
        $this->info('[MoralisTracker] Starting transaction sync...');

        $specificAddress = $this->option('address');
        $fromBlock       = (int) $this->option('from-block');
        $fresh           = $this->option('fresh');

        if ($specificAddress) {
            return $this->syncSingleAddress($service, $specificAddress, $fromBlock, $fresh);
        }

        return $this->syncAllAddresses($service, $fromBlock, $fresh);
    }

    protected function syncSingleAddress(TransactionSyncService $service, string $address, int $fromBlock, bool $fresh): int
    {
        $address = strtolower($address);
        $tracked = TrackedAddress::where('address', $address)->first();

        $startBlock = $fromBlock;
        if (!$fresh && $tracked && $tracked->last_synced_block > 0) {
            $startBlock = $tracked->last_synced_block;
        }

        $this->line("  → Syncing address: <comment>{$address}</comment> from block <comment>{$startBlock}</comment>");

        try {
            $result = $service->syncByAddress($address, $startBlock, $tracked);
            $this->info("  ✔ Done. New transactions: <comment>{$result['new']}</comment> | Highest block: <comment>{$result['highestBlock']}</comment>");
        } catch (\Throwable $e) {
            $this->error("  ✘ Failed: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function syncAllAddresses(TransactionSyncService $service, int $fromBlock, bool $fresh): int
    {
        $addresses = TrackedAddress::active()->get();

        if ($addresses->isEmpty()) {
            $this->warn('[MoralisTracker] No active tracked addresses found. Add addresses via `moralis:add-address` or set MORALIS_ADDRESSES in .env.');
            return self::SUCCESS;
        }

        $this->line("  Found <comment>{$addresses->count()}</comment> active address(es).");

        $totalNew    = 0;
        $totalErrors = 0;

        foreach ($addresses as $tracked) {
            $startBlock = $fromBlock;
            if (!$fresh && $tracked->last_synced_block > 0) {
                $startBlock = $tracked->last_synced_block;
            }

            $label = $tracked->label ? " ({$tracked->label})" : '';
            $this->line("  → <comment>{$tracked->address}</comment>{$label} from block <comment>{$startBlock}</comment>");

            try {
                $result = $service->syncByAddress($tracked->address, $startBlock, $tracked);
                $totalNew += $result['new'];
                $this->line("      New: <info>{$result['new']}</info> | Top block: <info>{$result['highestBlock']}</info>");
            } catch (\Throwable $e) {
                $totalErrors++;
                $this->error("      Failed: " . $e->getMessage());
            }
        }

        $this->info("\n[MoralisTracker] Sync complete. Total new: <comment>{$totalNew}</comment> | Errors: <comment>{$totalErrors}</comment>");

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
