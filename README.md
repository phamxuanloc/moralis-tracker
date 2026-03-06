# BscScan Tracker — Laravel Package

A Laravel package that tracks and logs all BSC (Binance Smart Chain) transactions for one or more wallet addresses using the **BscScan API**, storing them in your database with full logging support.

---

## Features

- Tracks **normal**, **internal**, **BEP-20 token**, and **NFT** transactions
- Stores all transaction data in your database (fully normalized)
- Incremental sync — resumes from the last synced block per address
- Configurable via `.env` and `config/bscscan.php`
- Artisan commands: `bscscan:sync` and `bscscan:add-address`
- Auto-discovers via Laravel's package discovery (no manual registration needed)
- Rate-limit aware with retry logic

---

## Requirements

- PHP ≥ 8.1
- Laravel 10 or 11
- A free [BscScan API key](https://bscscan.com/myapikey)
- `ext-bcmath` enabled (for wei → BNB conversion)

---

## Installation

### 1. Require via Composer

If published to Packagist:
```bash
composer require locpx/bscscan-tracker
```

For local development, add to your host app's `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../tracking address"
        }
    ],
    "require": {
        "locpx/bscscan-tracker": "*"
    }
}
```

### 2. Publish Config & Migrations

```bash
php artisan vendor:publish --tag=bscscan-config
php artisan vendor:publish --tag=bscscan-migrations
php artisan migrate
```

### 3. Configure `.env`

```dotenv
BSCSCAN_API_KEY=your_api_key_here

# Optional: comma-separated addresses to seed automatically
BSCSCAN_ADDRESSES=0xabc123...,0xdef456...

# Optional overrides
BSCSCAN_BASE_URL=https://api.bscscan.com/api
BSCSCAN_TIMEOUT=30
BSCSCAN_LOG_CHANNEL=stack
```

---

## Usage

### Add an address to track

```bash
php artisan bscscan:add-address 0xYourAddressHere --label="My Wallet"
```

### Sync transactions

```bash
# Sync all active addresses
php artisan bscscan:sync

# Sync a specific address
php artisan bscscan:sync --address=0xYourAddressHere

# Sync from a specific block
php artisan bscscan:sync --address=0xYourAddressHere --from-block=25000000

# Force full re-sync (ignore last_synced_block)
php artisan bscscan:sync --fresh
```

### Schedule automatic syncing

In your `app/Console/Kernel.php` (Laravel 10) or `routes/console.php` (Laravel 11):

**Laravel 10 — `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('bscscan:sync')->everyFiveMinutes();
}
```

**Laravel 11 — `routes/console.php`:**
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('bscscan:sync')->everyFiveMinutes();
```

---

## Database Tables

### `tracked_addresses`

| Column             | Type      | Description                          |
|--------------------|-----------|--------------------------------------|
| `address`          | string    | BSC wallet address (0x...)           |
| `label`            | string    | Human-readable name (optional)       |
| `is_active`        | boolean   | Whether to sync this address         |
| `last_synced_block`| bigint    | Last block synced (for incremental)  |
| `last_synced_at`   | timestamp | Timestamp of last sync               |
| `meta`             | json      | Extra metadata                       |

### `bsc_transactions`

| Column             | Type      | Description                          |
|--------------------|-----------|--------------------------------------|
| `tx_hash`          | string    | Transaction hash                     |
| `type`             | string    | `normal`, `internal`, `token`, `nft` |
| `tracked_address`  | string    | Address being tracked                |
| `block_number`     | bigint    | Block number                         |
| `block_timestamp`  | timestamp | Block time                           |
| `from_address`     | string    | Sender                               |
| `to_address`       | string    | Receiver                             |
| `value_bnb`        | decimal   | BNB value (18 decimals)              |
| `tx_fee_bnb`       | decimal   | Transaction fee in BNB               |
| `token_name`       | string    | Token name (for token/nft txs)       |
| `token_symbol`     | string    | Token symbol                         |
| `is_error`         | boolean   | Whether the tx failed                |
| `raw_data`         | json      | Full raw BscScan response            |

---

## Programmatic Usage

```php
use Locpx\BscScanTracker\Services\TransactionSyncService;
use Locpx\BscScanTracker\Models\BscTransaction;
use Locpx\BscScanTracker\Models\TrackedAddress;

// Sync a specific address
$service = app(TransactionSyncService::class);
$result  = $service->syncByAddress('0xYourAddress');
// $result = ['new' => 12, 'highestBlock' => 27340000]

// Query transactions
BscTransaction::forAddress('0xYourAddress')->ofType('token')->latest('block_number')->get();

// Get all incoming transactions
BscTransaction::incoming('0xYourAddress')->get();
```

---

## Configuration Reference

```php
// config/bscscan.php
return [
    'api_key'              => env('BSCSCAN_API_KEY', ''),
    'base_url'             => env('BSCSCAN_BASE_URL', 'https://api.bscscan.com/api'),
    'addresses'            => [],           // seed from .env
    'transaction_types'    => ['normal', 'internal', 'token'],
    'timeout'              => 30,
    'max_retries'          => 3,
    'max_records_per_page' => 10000,
    'start_block'          => 0,
    'log_channel'          => env('BSCSCAN_LOG_CHANNEL', 'stack'),
    'table_names'          => [
        'tracked_addresses' => 'tracked_addresses',
        'bsc_transactions'  => 'bsc_transactions',
    ],
];
```

---

## License

MIT
