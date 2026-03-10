<?php

/**
 * Moralis API Test Script
 * Run: php test-api.php
 */

// ---------------------------------------------------------------------------
// Config — reads from .env if present, else falls back to defaults
// ---------------------------------------------------------------------------
function env_get(string $key, string $default = ''): string
{
    $envFile = __DIR__ . '/.env';
    static $parsed = null;

    if ($parsed === null) {
        $parsed = [];
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $parsed[trim($k)] = trim($v);
            }
        }
    }

    return $parsed[$key] ?? getenv($key) ?: $default;
}

$API_KEY  = env_get('MORALIS_API_KEY');
$BASE_URL = rtrim(env_get('MORALIS_BASE_URL', 'https://deep-index.moralis.io/api/v2.2'), '/');

// Chains to test: [chain_id => [label, native_symbol, test_address]]
$CHAINS_TO_TEST = [
    'bsc' => [
        'label'         => 'BNB Smart Chain',
        'native_symbol' => 'BNB',
        'address'       => env_get('MORALIS_ADDRESSES', '0x60cd66c5BBFB0c0FA6891d5358036D2fD78Ac83D'),
    ],
    'eth' => [
        'label'         => 'Ethereum',
        'native_symbol' => 'ETH',
        'address'       => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', // vitalik.eth (public)
    ],
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function moralis_get(string $path, string $chain, array $params = []): array
{
    $params['chain'] = $chain;
    $url = $GLOBALS['BASE_URL'] . $path . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'MoralisTracker-Test/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: ' . $GLOBALS['API_KEY'],
            'Accept: application/json',
        ],
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return ['__error' => "cURL error: {$error}", '__http' => 0];
    }

    $decoded = json_decode((string) $raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['__error' => 'Invalid JSON', '__raw' => substr($raw, 0, 300), '__http' => $httpCode];
    }

    $decoded['__http'] = $httpCode;
    return $decoded;
}

function wei_to_native(string $wei): string
{
    if (!is_numeric($wei) || $wei === '0') return '0.00000000';
    return number_format((float) bcdiv($wei, bcpow('10', '18', 0), 18), 8);
}

function print_section(string $title): void
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  {$title}\n";
    echo str_repeat('=', 60) . "\n";
}

function print_ok(string $msg): void   { echo "  ✔  \033[32m{$msg}\033[0m\n"; }
function print_err(string $msg): void  { echo "  ✘  \033[31m{$msg}\033[0m\n"; }
function print_info(string $msg): void { echo "  →  {$msg}\n"; }

// ---------------------------------------------------------------------------
// Prerequisites
// ---------------------------------------------------------------------------
print_section('Moralis Multi-Chain API Test');

if (empty($API_KEY)) {
    print_err('MORALIS_API_KEY is not set in .env');
    print_info('Get your free key at: https://admin.moralis.io/api-keys');
    exit(1);
}

print_ok('API key loaded: ' . substr($API_KEY, 0, 6) . '...' . substr($API_KEY, -4));
print_info("Base URL : {$BASE_URL}");
print_info("Chains   : " . implode(', ', array_keys($CHAINS_TO_TEST)));

// ---------------------------------------------------------------------------
// Loop over each chain
// ---------------------------------------------------------------------------
$allPassed = true;

foreach ($CHAINS_TO_TEST as $chainId => $chainInfo) {
    $label   = $chainInfo['label'];
    $symbol  = $chainInfo['native_symbol'];
    $address = strtolower(explode(',', $chainInfo['address'])[0]); // first address if comma-separated

    print_section("Chain: {$label} ({$chainId})  |  Address: {$address}");

    // Test A: Native balance
    echo "  [A] Native {$symbol} balance\n";
    $res = moralis_get("/{$address}/balance", $chainId);

    if (isset($res['__error'])) {
        print_err($res['__error']);
        $allPassed = false;
        continue;
    }
    if ($res['__http'] !== 200) {
        print_err("HTTP {$res['__http']}: " . ($res['message'] ?? json_encode($res)));
        $allPassed = false;
        continue;
    }

    $native = wei_to_native($res['balance'] ?? '0');
    print_ok("HTTP 200 — Balance: {$native} {$symbol}  (raw: " . ($res['balance'] ?? '0') . " wei)");

    // Test B: Last 3 native transactions
    echo "\n  [B] Last 3 native transactions\n";
    $res = moralis_get("/{$address}", $chainId, ['limit' => 3]);

    if ($res['__http'] !== 200) {
        print_err("HTTP {$res['__http']}: " . ($res['message'] ?? ''));
        $allPassed = false;
    } elseif (empty($res['result'])) {
        print_info('No native transactions found.');
    } else {
        print_ok(count($res['result']) . ' transaction(s) returned');
        foreach ($res['result'] as $i => $tx) {
            $val    = wei_to_native($tx['value'] ?? '0');
            $status = ($tx['receipt_status'] ?? '1') === '1' ? "\033[32mOK\033[0m" : "\033[31mFAIL\033[0m";
            echo sprintf(
                "    [%d] Block %-10s  %s → %s  %s %s  %s\n",
                $i + 1,
                $tx['block_number'] ?? '?',
                substr($tx['from_address'] ?? '', 0, 10) . '..',
                substr($tx['to_address']   ?? '', 0, 10) . '..',
                $val, $symbol, $status
            );
        }
    }

    // Test C: Last 3 token transfers
    echo "\n  [C] Last 3 token transfers\n";
    $res = moralis_get("/{$address}/erc20/transfers", $chainId, ['limit' => 3]);

    if ($res['__http'] !== 200) {
        print_err("HTTP {$res['__http']}: " . ($res['message'] ?? ''));
        $allPassed = false;
    } elseif (empty($res['result'])) {
        print_info('No token transfers found.');
    } else {
        print_ok(count($res['result']) . ' token transfer(s) returned');
        foreach ($res['result'] as $i => $tx) {
            $dec    = max(1, (int) ($tx['token_decimals'] ?? 18));
            $amount = number_format((float) bcdiv($tx['value'] ?? '0', bcpow('10', (string) $dec, 0), $dec), 4);
            echo sprintf(
                "    [%d] Block %-10s  %s %s  (%s)\n",
                $i + 1,
                $tx['block_number'] ?? '?',
                $amount,
                $tx['token_symbol'] ?? '?',
                $tx['token_name']   ?? '?'
            );
        }
    }
}

// ---------------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------------
print_section('Done');
if ($allPassed) {
    print_ok('All chains passed. Moralis API is working correctly.');
} else {
    print_err('Some chains had errors (see above).');
}
echo "\n";
