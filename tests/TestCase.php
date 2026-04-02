<?php

namespace Locpx\MoralisTracker\Tests;

use Locpx\MoralisTracker\MoralisServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            MoralisServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('moralis.api_key', 'test_api_key');
        $app['config']->set('moralis.default_chain', 'bsc');
        $app['config']->set('moralis.base_url', 'https://deep-index.moralis.io/api/v2.2');
        $app['config']->set('moralis.max_retries', 3);
        $app['config']->set('moralis.timeout', 30);
        $app['config']->set('moralis.max_records_per_page', 100);
        $app['config']->set('moralis.log_channel', 'stack');

        $app['config']->set('moralis.chains', [
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
        ]);

        $app['config']->set('moralis.table_names', [
            'tracked_addresses'  => 'tracked_addresses',
            'chain_transactions' => 'chain_transactions',
        ]);
    }
}
