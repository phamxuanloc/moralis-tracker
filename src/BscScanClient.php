<?php

namespace Locpx\BscScanTracker;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BscScanClient
{
    protected Client $http;
    protected string $apiKey;
    protected string $baseUrl;
    protected int $maxRetries;
    protected string $logChannel;

    public function __construct()
    {
        $this->apiKey     = config('bscscan.api_key', '');
        $this->baseUrl    = rtrim(config('bscscan.base_url', 'https://api.bscscan.com/v2/api'), '/');
        $this->maxRetries = (int) config('bscscan.max_retries', 3);
        $this->logChannel = config('bscscan.log_channel', 'stack');

        $this->http = new Client([
            'timeout'         => config('bscscan.timeout', 30),
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Fetch normal (BNB) transactions for an address.
     */
    public function getNormalTransactions(string $address, int $startBlock = 0, int $endBlock = 99999999): array
    {
        return $this->fetch([
            'module'     => 'account',
            'action'     => 'txlist',
            'address'    => $address,
            'startblock' => $startBlock,
            'endblock'   => $endBlock,
            'sort'       => 'asc',
            'offset'     => config('bscscan.max_records_per_page', 10000),
            'page'       => 1,
        ]);
    }

    /**
     * Fetch internal transactions for an address.
     */
    public function getInternalTransactions(string $address, int $startBlock = 0, int $endBlock = 99999999): array
    {
        return $this->fetch([
            'module'     => 'account',
            'action'     => 'txlistinternal',
            'address'    => $address,
            'startblock' => $startBlock,
            'endblock'   => $endBlock,
            'sort'       => 'asc',
            'offset'     => config('bscscan.max_records_per_page', 10000),
            'page'       => 1,
        ]);
    }

    /**
     * Fetch BEP-20 token transfer events for an address.
     */
    public function getTokenTransfers(string $address, int $startBlock = 0, int $endBlock = 99999999): array
    {
        return $this->fetch([
            'module'     => 'account',
            'action'     => 'tokentx',
            'address'    => $address,
            'startblock' => $startBlock,
            'endblock'   => $endBlock,
            'sort'       => 'asc',
            'offset'     => config('bscscan.max_records_per_page', 10000),
            'page'       => 1,
        ]);
    }

    /**
     * Fetch BEP-721 (NFT) transfer events for an address.
     */
    public function getNftTransfers(string $address, int $startBlock = 0, int $endBlock = 99999999): array
    {
        return $this->fetch([
            'module'     => 'account',
            'action'     => 'tokennfttx',
            'address'    => $address,
            'startblock' => $startBlock,
            'endblock'   => $endBlock,
            'sort'       => 'asc',
            'offset'     => config('bscscan.max_records_per_page', 10000),
            'page'       => 1,
        ]);
    }

    /**
     * Get current BNB balance for an address.
     */
    public function getBalance(string $address): string
    {
        $result = $this->fetch([
            'module'  => 'account',
            'action'  => 'balance',
            'address' => $address,
            'tag'     => 'latest',
        ]);

        return $result[0] ?? '0';
    }

    /**
     * Core HTTP fetch with retry logic.
     */
    protected function fetch(array $params): array
    {
        $params['apikey']  = $this->apiKey;
        $params['chainid'] = config('bscscan.chain_id', 56);

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            try {
                $response = $this->http->get($this->baseUrl, ['query' => $params]);
                $body     = json_decode((string) $response->getBody(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('BscScan returned invalid JSON');
                }

                $status  = $body['status']  ?? '0';
                $message = $body['message'] ?? '';
                $result  = $body['result']  ?? [];

                if ($status === '0' && $message !== 'No transactions found') {
                    Log::channel($this->logChannel)->warning('[BscScanTracker] API error', [
                        'message' => $message,
                        'result'  => $result,
                        'params'  => array_merge($params, ['apikey' => '***']),
                    ]);

                    if ($message === 'NOTOK' || str_contains((string) $result, 'rate limit')) {
                        sleep(2 * $attempt);
                        continue;
                    }

                    return [];
                }

                return is_array($result) ? $result : [];

            } catch (GuzzleException $e) {
                $lastException = $e;
                Log::channel($this->logChannel)->error('[BscScanTracker] HTTP error', [
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                    'params'  => array_merge($params, ['apikey' => '***']),
                ]);
                sleep(2 * $attempt);
            }
        }

        throw new RuntimeException(
            '[BscScanTracker] Failed after ' . $this->maxRetries . ' attempts: ' . ($lastException?->getMessage() ?? 'unknown')
        );
    }
}
