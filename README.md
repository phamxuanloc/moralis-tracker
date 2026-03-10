# Moralis Tracker — Laravel Package

A Laravel package that tracks and logs all BSC (Binance Smart Chain) transactions for one or more wallet addresses using the **Moralis API**, storing them in your database with full logging support.

---

## Features

- Tracks **native BNB**, **BEP-20 token**, and **NFT** transactions
- Stores all transaction data in your database (fully normalized)
- Incremental sync — resumes from the last synced block per address
- Cursor-based pagination — handles wallets with thousands of transactions
- Configurable via `.env` and `config/moralis.php`
- Artisan commands: `moralis:sync` and `moralis:add-address`
- Auto-discovers via Laravel's package discovery (no manual registration needed)
- Rate-limit aware with retry logic

---

## Requirements

- PHP ≥ 8.1
- Laravel 10 or 11
- A free [Moralis API key](https://admin.moralis.io/api-keys)
- `ext-bcmath` enabled (for wei → BNB conversion)
- `ext-curl` enabled

---

## Installation

### 1. Require via Composer

```bash
composer require locpx/moralis-tracker
```

For local development, add to your host app's `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../moralis-tracker"
        }
    ],
    "require": {
        "locpx/moralis-tracker": "*"
    }
}
```

### 2. Publish Config & Migrations

```bash
php artisan vendor:publish --tag=moralis-config
php artisan vendor:publish --tag=moralis-migrations
php artisan migrate
```

### 3. Configure `.env`

```dotenv
# Get your free key at https://admin.moralis.io/api-keys
MORALIS_API_KEY=your_api_key_here

# BSC Mainnet: bsc | BSC Testnet: 0x61
MORALIS_CHAIN=bsc

# Optional: comma-separated addresses to seed automatically
MORALIS_ADDRESSES=0xabc123...,0xdef456...

# Optional overrides
MORALIS_TIMEOUT=30
MORALIS_MAX_RETRIES=3
MORALIS_LOG_CHANNEL=stack
```

---

## Usage

### Add an address to track

```bash
php artisan moralis:add-address 0xYourAddressHere --label="My Wallet"
```

### Sync transactions

```bash
# Sync all active addresses
php artisan moralis:sync

# Sync a specific address
php artisan moralis:sync --address=0xYourAddressHere

# Sync from a specific block
php artisan moralis:sync --address=0xYourAddressHere --from-block=25000000

# Force full re-sync (ignore last_synced_block)
php artisan moralis:sync --fresh
```

### Schedule automatic syncing

**Laravel 10 — `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('moralis:sync')->everyFiveMinutes();
}
```

**Laravel 11 — `routes/console.php`:**
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('moralis:sync')->everyFiveMinutes();
```

---

## Database Tables

### `tracked_addresses`

| Column              | Type      | Description                         |
|---------------------|-----------|-------------------------------------|
| `address`           | string    | BSC wallet address (0x...)          |
| `label`             | string    | Human-readable name (optional)      |
| `is_active`         | boolean   | Whether to sync this address        |
| `last_synced_block` | bigint    | Last block synced (for incremental) |
| `last_synced_at`    | timestamp | Timestamp of last sync              |
| `meta`              | json      | Extra metadata                      |

### `bsc_transactions`

| Column              | Type      | Description                         |
|---------------------|-----------|-------------------------------------|
| `tx_hash`           | string    | Transaction hash                    |
| `type`              | string    | `normal`, `token`, `nft`            |
| `tracked_address`   | string    | Address being tracked               |
| `block_number`      | bigint    | Block number                        |
| `block_timestamp`   | timestamp | Block time                          |
| `from_address`      | string    | Sender                              |
| `to_address`        | string    | Receiver                            |
| `value_bnb`         | decimal   | BNB value (18 decimals)             |
| `tx_fee_bnb`        | decimal   | Transaction fee in BNB              |
| `token_name`        | string    | Token name (for token/nft txs)      |
| `token_symbol`      | string    | Token symbol                        |
| `is_error`          | boolean   | Whether the tx failed               |
| `raw_data`          | json      | Full raw Moralis response           |

---

## Programmatic Usage

```php
use Locpx\MoralisTracker\Services\TransactionSyncService;
use Locpx\MoralisTracker\Models\BscTransaction;
use Locpx\MoralisTracker\Models\TrackedAddress;

// Sync a specific address
$service = app(TransactionSyncService::class);
$result  = $service->syncByAddress('0xYourAddress');
// $result = ['new' => 12, 'highestBlock' => 27340000]

// Query transactions
BscTransaction::forAddress('0xYourAddress')->ofType('token')->latest('block_number')->get();

// Get all incoming transactions
BscTransaction::where('to_address', strtolower('0xYourAddress'))->get();
```

---

## Configuration Reference

```php
// config/moralis.php
return [
    'api_key'              => env('MORALIS_API_KEY', ''),
    'base_url'             => env('MORALIS_BASE_URL', 'https://deep-index.moralis.io/api/v2.2'),
    'chain'                => env('MORALIS_CHAIN', 'bsc'),
    'addresses'            => [],
    'transaction_types'    => ['normal', 'token', 'nft'],
    'timeout'              => env('MORALIS_TIMEOUT', 30),
    'max_retries'          => env('MORALIS_MAX_RETRIES', 3),
    'max_records_per_page' => 100,
    'start_block'          => 0,
    'log_channel'          => env('MORALIS_LOG_CHANNEL', 'stack'),
    'table_names'          => [
        'tracked_addresses' => 'tracked_addresses',
        'bsc_transactions'  => 'bsc_transactions',
    ],
];
```

---

## License

MIT
