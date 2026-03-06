<?php

namespace Locpx\MoralisTracker;

use RuntimeException;

class MoralisClient
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $chain;
    protected int $maxRetries;
    protected int $limit;

    public function __construct()
    {
        $this->apiKey     = config('moralis.api_key', '');
        $this->baseUrl    = rtrim(config('moralis.base_url', 'https://deep-index.moralis.io/api/v2.2'), '/');
        $this->chain      = config('moralis.chain', 'bsc');
        $this->maxRetries = (int) config('moralis.max_retries', 3);
        $this->limit      = (int) config('moralis.max_records_per_page', 100);
    }

    /**
     * Fetch all native (BNB) transactions for an address (all pages).
     */
    public function getNormalTransactions(string $address, int $fromBlock = 0): array
    {
        return $this->fetchAllPages("/{$address}", array_filter([
            'from_block' => $fromBlock > 0 ? $fromBlock : null,
        ]));
    }

    /**
     * Fetch all BEP-20 token transfers for an address (all pages).
     */
    public function getTokenTransfers(string $address, int $fromBlock = 0): array
    {
        return $this->fetchAllPages("/{$address}/erc20/transfers", array_filter([
            'from_block' => $fromBlock > 0 ? $fromBlock : null,
        ]));
    }

    /**
     * Fetch all NFT transfers for an address (all pages).
     */
    public function getNftTransfers(string $address, int $fromBlock = 0): array
    {
        return $this->fetchAllPages("/{$address}/nft/transfers", array_filter([
            'from_block' => $fromBlock > 0 ? $fromBlock : null,
        ]));
    }

    /**
     * Get native BNB balance for an address.
     */
    public function getBalance(string $address): string
    {
        $data = $this->request("/{$address}/balance");
        return $data['balance'] ?? '0';
    }

    /**
     * Paginate through all cursor pages and return a flat array of results.
     */
    protected function fetchAllPages(string $path, array $extraParams = []): array
    {
        $all    = [];
        $cursor = null;

        do {
            $params = array_merge($extraParams, ['limit' => $this->limit]);
            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $data   = $this->request($path, $params);
            $result = $data['result'] ?? [];

            if (!empty($result)) {
                $all = array_merge($all, $result);
            }

            $cursor = $data['cursor'] ?? null;

        } while (!empty($cursor));

        return $all;
    }

    /**
     * Make a single HTTP GET request to the Moralis API with retry logic.
     */
    protected function request(string $path, array $params = []): array
    {
        $params['chain'] = $this->chain;
        $url = $this->baseUrl . $path . '?' . http_build_query($params);

        $attempt       = 0;
        $lastError     = '';

        while ($attempt < $this->maxRetries) {
            $attempt++;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => config('moralis.timeout', 30),
                CURLOPT_USERAGENT      => 'MoralisTracker-Laravel/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => [
                    'X-API-Key: ' . $this->apiKey,
                    'Accept: application/json',
                ],
            ]);

            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno    = curl_errno($ch);
            $lastError = curl_error($ch);
            curl_close($ch);

            if ($errno !== 0) {
                sleep(2 * $attempt);
                continue;
            }

            $decoded = json_decode((string) $body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('[MoralisClient] Invalid JSON response from: ' . $path);
            }

            if ($httpCode === 429) {
                sleep(2 * $attempt);
                continue;
            }

            if ($httpCode >= 400) {
                $msg = $decoded['message'] ?? $body;
                throw new RuntimeException("[MoralisClient] HTTP {$httpCode}: {$msg}");
            }

            return $decoded;
        }

        throw new RuntimeException("[MoralisClient] Failed after {$this->maxRetries} attempts on {$path}: {$lastError}");
    }
}
