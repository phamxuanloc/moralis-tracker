<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moralis API Key
    |--------------------------------------------------------------------------
    | Get your free API key at https://admin.moralis.io/api-keys
    */
    'api_key' => env('MORALIS_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Moralis API Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('MORALIS_BASE_URL', 'https://deep-index.moralis.io/api/v2.2'),

    /*
    |--------------------------------------------------------------------------
    | Moralis Chain Identifier
    |--------------------------------------------------------------------------
    | BSC Mainnet: bsc | BSC Testnet: 0x61
    */
    'chain' => env('MORALIS_CHAIN', 'bsc'),

    /*
    |--------------------------------------------------------------------------
    | Addresses to Track
    |--------------------------------------------------------------------------
    | Comma-separated BSC wallet addresses. Can also be managed via the
    | tracked_addresses database table.
    */
    'addresses' => array_filter(explode(',', env('MORALIS_ADDRESSES', ''))),

    /*
    |--------------------------------------------------------------------------
    | Transaction Types to Sync
    |--------------------------------------------------------------------------
    | normal - Regular BNB transactions
    | token  - BEP-20 token transfers
    | nft    - BEP-721 / BEP-1155 NFT transfers
    */
    'transaction_types' => ['normal', 'token', 'nft'],

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Options
    |--------------------------------------------------------------------------
    */
    'timeout'     => env('MORALIS_TIMEOUT', 30),
    'max_retries' => env('MORALIS_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    | max_records_per_page: Moralis allows up to 100 per page (auto-paginated)
    | start_block: The block to start syncing from (0 = genesis)
    */
    'max_records_per_page' => 100,
    'start_block'          => 0,

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    */
    'table_names' => [
        'tracked_addresses' => 'tracked_addresses',
        'bsc_transactions'  => 'bsc_transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'log_channel' => env('MORALIS_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),

];
