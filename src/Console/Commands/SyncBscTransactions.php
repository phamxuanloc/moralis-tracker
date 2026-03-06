<?php

namespace Locpx\BscScanTracker\Console\Commands;

/**
 * @deprecated Use Locpx\MoralisTracker\Console\Commands\SyncTransactions instead.
 *             Command: moralis:sync
 */
class SyncBscTransactions extends \Locpx\MoralisTracker\Console\Commands\SyncTransactions
{
    protected $signature = 'bscscan:sync
                            {--address= : Sync a specific address (overrides DB list)}
                            {--from-block=0 : Start syncing from this block number}
                            {--type=* : Transaction types to sync (normal, token, nft)}
                            {--fresh : Ignore last_synced_block and sync from --from-block}';

    protected $description = '[Deprecated] Use moralis:sync instead';

}
