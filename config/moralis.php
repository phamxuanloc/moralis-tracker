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
    | Default Chain
    |--------------------------------------------------------------------------
    | Used when no --chain option is passed to artisan commands, and for
    | addresses seeded via MORALIS_ADDRESSES in .env.
    | Must match a key in the 'chains' array below.
    */
    'default_chain' => env('MORALIS_CHAIN', 'bsc'),

    /*
    |--------------------------------------------------------------------------
    | Supported Chains
    |--------------------------------------------------------------------------
    | Add or remove chains here. 'moralis_id' is the identifier Moralis uses.
    | 'native_symbol' is used for display / column naming in logs.
    | 'transaction_types' controls what is fetched: normal, token, nft.
    */
    'chains' => [
        'bsc' => [
            'name'              => 'BNB Smart Chain',
            'moralis_id'        => 'bsc',
            'native_symbol'     => 'BNB',
            'transaction_types' => ['normal', 'token', 'nft'],
        ],
        'eth' => [
            'name'              => 'Ethereum',
            'moralis_id'        => 'eth',
            'native_symbol'     => 'ETH',
            'transaction_types' => ['normal', 'token', 'nft'],
        ],
        'polygon' => [
            'name'              => 'Polygon',
            'moralis_id'        => 'polygon',
            'native_symbol'     => 'MATIC',
            'transaction_types' => ['normal', 'token', 'nft'],
        ],
        'arbitrum' => [
            'name'              => 'Arbitrum One',
            'moralis_id'        => 'arbitrum',
            'native_symbol'     => 'ETH',
            'transaction_types' => ['normal', 'token', 'nft'],
        ],
        'base' => [
            'name'              => 'Base',
            'moralis_id'        => 'base',
            'native_symbol'     => 'ETH',
            'transaction_types' => ['normal', 'token', 'nft'],
        ],
        'avalanche' => [
            'name'              => 'Avalanche C-Chain',
            'moralis_id'        => 'avalanche',
            'native_symbol'     => 'AVAX',
            'transaction_types' => ['normal', 'token', 'nft'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Addresses to Track (seeded from .env)
    |--------------------------------------------------------------------------
    | Comma-separated wallet addresses, tracked on the default_chain.
    | To track on multiple chains, use the tracked_addresses table directly
    | or the `moralis:add-address` command with --chain.
    */
    'addresses' => array_filter(explode(',', env('MORALIS_ADDRESSES', ''))),

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
        'tracked_addresses'  => 'tracked_addresses',
        'chain_transactions' => 'chain_transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'log_channel' => env('MORALIS_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),

];
