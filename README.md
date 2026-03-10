# Moralis Tracker — Laravel Package

A Laravel package that tracks and logs transactions for one or more wallet addresses across **multiple EVM chains** (BSC, Ethereum, Polygon, Arbitrum, Base, Avalanche, and more) using the **Moralis API**, storing them in your database with full logging support.

---

## Features

- **Multi-chain** — track the same or different addresses on BSC, ETH, Polygon, Arbitrum, Base, Avalanche simultaneously
- Tracks **native**, **ERC-20/BEP-20 token**, and **NFT** transfers per chain
- Incremental sync — resumes from the last synced block per address per chain
- Cursor-based pagination — handles wallets with thousands of transactions
- Upsert strategy — new transactions inserted, existing ones updated on re-sync
- Configurable via `.env` and `config/moralis.php`
- Artisan commands: `moralis:sync` and `moralis:add-address`
- Auto-discovers via Laravel's package discovery
- Rate-limit aware with retry logic

---

## Requirements

- PHP ≥ 8.1
- Laravel 10 or 11
- A free [Moralis API key](https://admin.moralis.io/api-keys)
- `ext-bcmath` and `ext-curl` enabled

---

## Installation

### 1. Require via Composer

```bash
composer require locpx/moralis-tracker
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

# Default chain when --chain is not specified (bsc, eth, polygon, arbitrum, base, avalanche)
MORALIS_CHAIN=bsc

# Optional: comma-separated addresses seeded on the default chain at first boot
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
# Track on the default chain (MORALIS_CHAIN)
php artisan moralis:add-address 0xYourAddress --label="My Wallet"

# Track the same address on multiple chains
php artisan moralis:add-address 0xYourAddress --chain=bsc  --label="BSC Wallet"
php artisan moralis:add-address 0xYourAddress --chain=eth  --label="ETH Wallet"
php artisan moralis:add-address 0xYourAddress --chain=polygon
```

### Sync transactions

```bash
# Sync all active addresses on all chains
php artisan moralis:sync

# Sync only BSC addresses
php artisan moralis:sync --chain=bsc

# Sync a specific address on a specific chain
php artisan moralis:sync --address=0xYourAddress --chain=eth

# Sync from a specific block
php artisan moralis:sync --address=0xYourAddress --chain=bsc --from-block=25000000

# Force full re-sync (ignore last_synced_block)
php artisan moralis:sync --fresh
```

### Schedule automatic syncing

**Laravel 10 — `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('moralis:sync')->everyMinute();
}
```

**Laravel 11 — `routes/console.php`:**
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('moralis:sync')->everyMinute();
```

Add this cron entry to your server:
```bash
* * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1
```

> Each sync fetches only blocks **newer than the last synced block**, so running every minute is efficient even across multiple chains.

---

## Database Tables

### `tracked_addresses`

| Column              | Type      | Description                              |
|---------------------|-----------|------------------------------------------|
| `address`           | string    | Wallet address (0x...)                   |
| `chain`             | string    | Chain key: `bsc`, `eth`, `polygon`, etc. |
| `label`             | string    | Human-readable name (optional)           |
| `is_active`         | boolean   | Whether to sync this address             |
| `last_synced_block` | bigint    | Last block synced (incremental)          |
| `last_synced_at`    | timestamp | Timestamp of last sync                   |
| `meta`              | json      | Extra metadata                           |

> Unique key: `(address, chain)` — the same address can be tracked on multiple chains independently.

### `chain_transactions`

| Column              | Type      | Description                              |
|---------------------|-----------|------------------------------------------|
| `tx_hash`           | string    | Transaction hash                         |
| `type`              | string    | `normal`, `token`, `nft`                 |
| `chain`             | string    | Chain key: `bsc`, `eth`, `polygon`, etc. |
| `tracked_address`   | string    | Address being tracked                    |
| `block_number`      | bigint    | Block number                             |
| `block_timestamp`   | timestamp | Block time                               |
| `from_address`      | string    | Sender                                   |
| `to_address`        | string    | Receiver                                 |
| `value_native`      | decimal   | Value in native currency (ETH/BNB/MATIC) |
| `tx_fee_native`     | decimal   | Fee in native currency                   |
| `token_name`        | string    | Token name (token/nft txs)               |
| `token_symbol`      | string    | Token symbol                             |
| `is_error`          | boolean   | Whether the tx failed                    |
| `raw_data`          | json      | Full raw Moralis response                |

> Unique key: `(tx_hash, type, chain, tracked_address)`

---

## Programmatic Usage

```php
use Locpx\MoralisTracker\Services\TransactionSyncService;
use Locpx\MoralisTracker\Models\ChainTransaction;
use Locpx\MoralisTracker\Models\TrackedAddress;

// Sync all chains
$service = app(TransactionSyncService::class);
$service->syncAll();

// Sync only BSC
$service->syncAll('bsc');

// Sync a specific address on a specific chain
$result = $service->syncByAddress('0xYourAddress', 'eth');
// $result = ['new' => 12, 'highestBlock' => 19340000]

// Query transactions
ChainTransaction::forAddress('0xYourAddress')->onChain('bsc')->ofType('token')->latest('block_number')->get();

// Get all incoming ETH transactions
ChainTransaction::onChain('eth')->incoming('0xYourAddress')->get();
```

---

## Adding a Custom Chain

Add any Moralis-supported chain to `config/moralis.php`:

```php
'chains' => [
    // ... existing chains ...
    'optimism' => [
        'name'              => 'Optimism',
        'moralis_id'        => 'optimism',
        'native_symbol'     => 'ETH',
        'transaction_types' => ['normal', 'token', 'nft'],
    ],
],
```

Then track an address on it:
```bash
php artisan moralis:add-address 0xYourAddress --chain=optimism
```

---

## Configuration Reference

```php
// config/moralis.php
return [
    'api_key'       => env('MORALIS_API_KEY', ''),
    'base_url'      => env('MORALIS_BASE_URL', 'https://deep-index.moralis.io/api/v2.2'),
    'default_chain' => env('MORALIS_CHAIN', 'bsc'),

    'chains' => [
        'bsc'       => ['name' => 'BNB Smart Chain',    'moralis_id' => 'bsc',       'native_symbol' => 'BNB',  'transaction_types' => ['normal','token','nft']],
        'eth'       => ['name' => 'Ethereum',            'moralis_id' => 'eth',       'native_symbol' => 'ETH',  'transaction_types' => ['normal','token','nft']],
        'polygon'   => ['name' => 'Polygon',             'moralis_id' => 'polygon',   'native_symbol' => 'MATIC','transaction_types' => ['normal','token','nft']],
        'arbitrum'  => ['name' => 'Arbitrum One',        'moralis_id' => 'arbitrum',  'native_symbol' => 'ETH',  'transaction_types' => ['normal','token','nft']],
        'base'      => ['name' => 'Base',                'moralis_id' => 'base',      'native_symbol' => 'ETH',  'transaction_types' => ['normal','token','nft']],
        'avalanche' => ['name' => 'Avalanche C-Chain',   'moralis_id' => 'avalanche', 'native_symbol' => 'AVAX', 'transaction_types' => ['normal','token','nft']],
    ],

    'addresses'            => [],
    'timeout'              => env('MORALIS_TIMEOUT', 30),
    'max_retries'          => env('MORALIS_MAX_RETRIES', 3),
    'max_records_per_page' => 100,
    'start_block'          => 0,
    'log_channel'          => env('MORALIS_LOG_CHANNEL', 'stack'),

    'table_names' => [
        'tracked_addresses'  => 'tracked_addresses',
        'chain_transactions' => 'chain_transactions',
    ],
];
```

---

## License

MIT
