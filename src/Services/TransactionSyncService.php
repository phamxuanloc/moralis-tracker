<?php

namespace Locpx\MoralisTracker\Services;

use Illuminate\Support\Facades\Log;
use Locpx\MoralisTracker\MoralisClient;
use Locpx\MoralisTracker\Models\ChainTransaction;
use Locpx\MoralisTracker\Models\TrackedAddress;

class TransactionSyncService
{
    protected MoralisClient $client;
    protected string $logChannel;

    public function __construct(MoralisClient $client)
    {
        $this->client     = $client;
        $this->logChannel = config('moralis.log_channel', 'stack');
    }

    /**
     * Sync all active tracked addresses (all chains).
     * Optionally filter to a specific chain key (e.g. 'bsc', 'eth').
     *
     * @return array{synced: int, new: int, errors: int}
     */
    public function syncAll(?string $chain = null): array
    {
        $totals = ['synced' => 0, 'new' => 0, 'errors' => 0];

        $query = TrackedAddress::active();
        if ($chain) {
            $query->where('chain', $chain);
        }

        $query->each(function (TrackedAddress $tracked) use (&$totals) {
            try {
                $result = $this->syncAddress($tracked);
                $totals['synced']++;
                $totals['new'] += $result['new'];
            } catch (\Throwable $e) {
                $totals['errors']++;
                Log::channel($this->logChannel)->error('[MoralisTracker] Sync failed', [
                    'address' => $tracked->address,
                    'chain'   => $tracked->chain,
                    'error'   => $e->getMessage(),
                ]);
            }
        });

        return $totals;
    }

    /**
     * Sync a single TrackedAddress model (uses its own chain).
     *
     * @return array{new: int, highestBlock: int}
     */
    public function syncAddress(TrackedAddress $trackedAddress): array
    {
        return $this->syncByAddress(
            $trackedAddress->address,
            $trackedAddress->chain,
            $trackedAddress->last_synced_block,
            $trackedAddress
        );
    }

    /**
     * Sync transactions for a raw address + chain string.
     * Optionally updates the TrackedAddress record's last_synced_block.
     *
     * @return array{new: int, highestBlock: int}
     */
    public function syncByAddress(
        string $address,
        string $chain,
        int $startBlock = 0,
        ?TrackedAddress $trackedAddress = null
    ): array {
        $address    = strtolower($address);
        $startBlock = max(0, $startBlock);
        $types      = config("moralis.chains.{$chain}.transaction_types", ['normal', 'token', 'nft']);

        $newCount     = 0;
        $highestBlock = $startBlock;

        Log::channel($this->logChannel)->info('[MoralisTracker] Starting sync', [
            'address'    => $address,
            'chain'      => $chain,
            'startBlock' => $startBlock,
            'types'      => $types,
        ]);

        foreach ($types as $type) {
            $raw = $this->fetchRaw($type, $address, $chain, $startBlock);

            if (empty($raw)) {
                continue;
            }

            $inserted = $this->upsertTransactions($address, $chain, $type, $raw);
            $newCount += $inserted;

            $blockInBatch = (int) (end($raw)['block_number'] ?? 0);
            if ($blockInBatch > $highestBlock) {
                $highestBlock = $blockInBatch;
            }

            Log::channel($this->logChannel)->info('[MoralisTracker] Synced batch', [
                'address' => $address,
                'chain'   => $chain,
                'type'    => $type,
                'fetched' => count($raw),
                'new'     => $inserted,
            ]);
        }

        if ($trackedAddress && $highestBlock > $trackedAddress->last_synced_block) {
            $trackedAddress->markSynced($highestBlock);
        }

        return ['new' => $newCount, 'highestBlock' => $highestBlock];
    }

    /**
     * Call the appropriate MoralisClient method based on type + chain.
     */
    protected function fetchRaw(string $type, string $address, string $chain, int $startBlock): array
    {
        return match ($type) {
            'normal' => $this->client->getNormalTransactions($address, $chain, $startBlock),
            'token'  => $this->client->getTokenTransfers($address, $chain, $startBlock),
            'nft'    => $this->client->getNftTransfers($address, $chain, $startBlock),
            default  => [],
        };
    }

    /**
     * Map raw Moralis records to DB rows and upsert. Returns count of truly new inserts.
     */
    protected function upsertTransactions(
        string $trackedAddress,
        string $chain,
        string $type,
        array $rawList
    ): int {
        $rows = [];

        foreach ($rawList as $raw) {
            $txHash   = strtolower($raw['hash'] ?? $raw['transaction_hash'] ?? '');
            $weiValue = (string) ($raw['value'] ?? '0');
            $gasPrice = (string) ($raw['gas_price'] ?? '0');
            $gasUsed  = (string) ($raw['receipt_gas_used'] ?? '0');

            $valueNative = $this->weiToNative($weiValue);
            $feeNative   = $this->weiToNative(bcmul($gasPrice, $gasUsed, 0));

            $blockTs = isset($raw['block_timestamp'])
                ? date('Y-m-d H:i:s', strtotime($raw['block_timestamp']))
                : null;

            $contractAddress = strtolower(
                $raw['address'] ?? $raw['token_address'] ?? $raw['contract_address'] ?? ''
            );

            $isError = isset($raw['receipt_status'])
                ? $raw['receipt_status'] !== '1'
                : false;

            $rows[] = [
                'tx_hash'           => $txHash,
                'type'              => $type,
                'chain'             => $chain,
                'tracked_address'   => $trackedAddress,
                'block_number'      => (int) ($raw['block_number'] ?? 0),
                'block_timestamp'   => $blockTs,
                'transaction_index' => isset($raw['transaction_index']) ? (int) $raw['transaction_index'] : null,
                'from_address'      => strtolower($raw['from_address'] ?? ''),
                'to_address'        => strtolower($raw['to_address'] ?? ''),
                'value'             => $weiValue,
                'value_native'      => $valueNative,
                'gas'               => $raw['gas'] ?? null,
                'gas_price'         => $gasPrice,
                'gas_used'          => $gasUsed,
                'tx_fee_native'     => $feeNative,
                'nonce'             => $raw['nonce'] ?? null,
                'input'             => $raw['input'] ?? null,
                'is_error'          => $isError,
                'tx_receipt_status' => $raw['receipt_status'] ?? null,
                'contract_address'  => $contractAddress,
                'token_name'        => $raw['token_name'] ?? null,
                'token_symbol'      => $raw['token_symbol'] ?? null,
                'token_decimal'     => isset($raw['token_decimals']) ? (int) $raw['token_decimals'] : null,
                'raw_data'          => json_encode($raw),
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        $existingHashes = ChainTransaction::query()
            ->where('tracked_address', $trackedAddress)
            ->where('chain', $chain)
            ->where('type', $type)
            ->whereIn('tx_hash', array_column($rows, 'tx_hash'))
            ->pluck('tx_hash')
            ->map('strtolower')
            ->flip()
            ->all();

        $newRows = array_values(
            array_filter($rows, fn($r) => !isset($existingHashes[$r['tx_hash']]))
        );

        ChainTransaction::upsert(
            array_values($rows),
            ['tx_hash', 'type', 'chain', 'tracked_address'],
            [
                'block_number', 'block_timestamp', 'transaction_index',
                'from_address', 'to_address', 'value', 'value_native',
                'gas', 'gas_price', 'gas_used', 'tx_fee_native',
                'nonce', 'input', 'is_error', 'tx_receipt_status',
                'contract_address', 'token_name', 'token_symbol', 'token_decimal',
                'raw_data', 'updated_at',
            ]
        );

        foreach ($newRows as $row) {
            Log::channel($this->logChannel)->info('[MoralisTracker] New transaction saved', [
                'address' => $trackedAddress,
                'chain'   => $chain,
                'type'    => $type,
                'hash'    => $row['tx_hash'],
                'block'   => $row['block_number'],
                'from'    => $row['from_address'],
                'to'      => $row['to_address'],
                'value'   => $row['value_native'],
            ]);
        }

        return count($newRows);
    }

    /**
     * Convert a wei string to native currency (18 decimals) using bcmath.
     */
    protected function weiToNative(string $wei): string
    {
        if (!is_numeric($wei) || $wei === '0') {
            return '0';
        }

        $divisor = bcpow('10', '18', 0);
        return bcdiv($wei, $divisor, 18);
    }
}
