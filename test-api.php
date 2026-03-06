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
$CHAIN    = env_get('MORALIS_CHAIN', 'bsc');

// Test address — Binance hot wallet (public, many txs)
$TEST_ADDRESS = '0x60cd66c5BBFB0c0FA6891d5358036D2fD78Ac83D';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function moralis_get(string $path, array $params = []): array
{
    $params['chain'] = $GLOBALS['CHAIN'];
    $url = $GLOBALS['BASE_URL'] . $path . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'BscScanTracker-Test/1.0',
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

function wei_to_bnb(string $wei): string
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

function print_ok(string $msg): void  { echo "  ✔  \033[32m{$msg}\033[0m\n"; }
function print_err(string $msg): void { echo "  ✘  \033[31m{$msg}\033[0m\n"; }
function print_info(string $msg): void { echo "  →  {$msg}\n"; }

// ---------------------------------------------------------------------------
// Prerequisites
// ---------------------------------------------------------------------------
print_section('Moralis BSC API Test');

if (empty($API_KEY)) {
    print_err('MORALIS_API_KEY is not set in .env');
    print_info('Get your free key at: https://admin.moralis.io/api-keys');
    exit(1);
}

print_ok('API key loaded: ' . substr($API_KEY, 0, 6) . '...' . substr($API_KEY, -4));
print_info("Base URL : {$BASE_URL}");
print_info("Chain    : {$CHAIN}");
print_info("Address  : {$TEST_ADDRESS}");

// ---------------------------------------------------------------------------
// Test 1: Native BNB balance (validates key + chain access)
// ---------------------------------------------------------------------------
print_section('Test 1 — Native BNB Balance');

$res = moralis_get("/{$TEST_ADDRESS}/balance");

if (isset($res['__error'])) {
    print_err($res['__error']);
    exit(1);
}
if ($res['__http'] !== 200) {
    print_err("HTTP {$res['__http']}: " . ($res['message'] ?? json_encode($res)));
    exit(1);
}

$bnb = wei_to_bnb($res['balance'] ?? '0');
print_ok("HTTP 200 OK");
print_info("Balance : {$bnb} BNB  (raw: {$res['balance']} wei)");

// ---------------------------------------------------------------------------
// Test 2: Last 5 native transactions
// ---------------------------------------------------------------------------
print_section('Test 2 — Last 5 Native Transactions');

$res = moralis_get("/{$TEST_ADDRESS}", ['limit' => 5]);

if ($res['__http'] !== 200) {
    print_err("HTTP {$res['__http']}: " . ($res['message'] ?? ''));
} elseif (empty($res['result'])) {
    print_info('No transactions found.');
} else {
    print_ok(count($res['result']) . ' transactions returned');
    foreach ($res['result'] as $i => $tx) {
        $bnb    = wei_to_bnb($tx['value'] ?? '0');
        $status = ($tx['receipt_status'] ?? '1') === '1' ? "\033[32mOK\033[0m" : "\033[31mFAILED\033[0m";
        echo sprintf(
            "  [%d] Block %-10s  From %-10s  To %-10s  %s BNB  %s\n",
            $i + 1,
            $tx['block_number'] ?? '?',
            substr($tx['from_address'] ?? '', 0, 10) . '...',
            substr($tx['to_address']   ?? '', 0, 10) . '...',
            $bnb,
            $status
        );
    }
}

// ---------------------------------------------------------------------------
// Test 3: Last 5 BEP-20 token transfers
// ---------------------------------------------------------------------------
print_section('Test 3 — Last 5 BEP-20 Token Transfers');

$res = moralis_get("/{$TEST_ADDRESS}/erc20/transfers", ['limit' => 5]);

if ($res['__http'] !== 200) {
    print_err("HTTP {$res['__http']}: " . ($res['message'] ?? ''));
} elseif (empty($res['result'])) {
    print_info('No token transfers found.');
} else {
    print_ok(count($res['result']) . ' token transfers returned');
    foreach ($res['result'] as $i => $tx) {
        $decimals = max(1, (int) ($tx['token_decimals'] ?? 18));
        $amount   = number_format(
            (float) bcdiv($tx['value'] ?? '0', bcpow('10', (string) $decimals, 0), $decimals),
            4
        );
        echo sprintf(
            "  [%d] Block %-10s  %s %s  (%s)\n",
            $i + 1,
            $tx['block_number']  ?? '?',
            $amount,
            $tx['token_symbol']  ?? '?',
            $tx['token_name']    ?? '?'
        );
    }
}

// ---------------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------------
print_section('All tests complete');
print_ok('Moralis API key is valid and working on BSC.');
echo "\n";
