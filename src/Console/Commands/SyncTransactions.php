<?php

namespace Locpx\MoralisTracker\Console\Commands;

use Illuminate\Console\Command;
use Locpx\MoralisTracker\Models\TrackedAddress;
use Locpx\MoralisTracker\Services\TransactionSyncService;

class SyncTransactions extends Command
{
    protected $signature = 'moralis:sync
                            {--address= : Sync a specific address (overrides DB list)}
                            {--chain= : Chain to sync (bsc, eth, polygon, etc.). Defaults to all active chains}
                            {--from-block=0 : Start syncing from this block number}
                            {--fresh : Ignore last_synced_block and sync from --from-block}';

    protected $description = 'Sync transactions via Moralis API for tracked addresses (multi-chain)';

    public function handle(TransactionSyncService $service): int
    {
        $specificAddress = $this->option('address');
        $chain           = $this->option('chain') ?: null;
        $fromBlock       = (int) $this->option('from-block');
        $fresh           = $this->option('fresh');

        $chainLabel = $chain ? "[{$chain}]" : '[all chains]';
        $this->info("[MoralisTracker] Starting sync {$chainLabel}...");

        if ($specificAddress) {
            $chain = $chain ?: config('moralis.default_chain', 'bsc');
            return $this->syncSingleAddress($service, $specificAddress, $chain, $fromBlock, $fresh);
        }

        return $this->syncAllAddresses($service, $chain, $fromBlock, $fresh);
    }

    protected function syncSingleAddress(TransactionSyncService $service, string $address, string $chain, int $fromBlock, bool $fresh): int
    {
        $address = strtolower($address);
        $tracked = TrackedAddress::where('address', $address)->where('chain', $chain)->first();

        $startBlock = $fromBlock;
        if (!$fresh && $tracked && $tracked->last_synced_block > 0) {
            $startBlock = $tracked->last_synced_block;
        }

        $this->line("  → <comment>{$address}</comment> on <info>{$chain}</info> from block <comment>{$startBlock}</comment>");

        try {
            $result = $service->syncByAddress($address, $chain, $startBlock, $tracked);
            $this->info("  ✔ New: <comment>{$result['new']}</comment> | Top block: <comment>{$result['highestBlock']}</comment>");
        } catch (\Throwable $e) {
            $this->error("  ✘ Failed: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function syncAllAddresses(TransactionSyncService $service, ?string $chain, int $fromBlock, bool $fresh): int
    {
        $query = TrackedAddress::active();
        if ($chain) {
            $query->where('chain', $chain);
        }
        $addresses = $query->get();

        if ($addresses->isEmpty()) {
            $this->warn('[MoralisTracker] No active tracked addresses found. Use `moralis:add-address` or set MORALIS_ADDRESSES in .env.');
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
            $this->line("  → <comment>{$tracked->address}</comment>{$label} [<info>{$tracked->chain}</info>] from block <comment>{$startBlock}</comment>");

            try {
                $result = $service->syncByAddress($tracked->address, $tracked->chain, $startBlock, $tracked);
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
