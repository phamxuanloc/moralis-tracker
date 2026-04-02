<?php

namespace Locpx\MoralisTracker\Tests\Unit\Services;

use Locpx\MoralisTracker\MoralisClient;
use Locpx\MoralisTracker\Models\ChainTransaction;
use Locpx\MoralisTracker\Models\TrackedAddress;
use Locpx\MoralisTracker\Services\TransactionSyncService;
use Locpx\MoralisTracker\Tests\TestCase;
use Mockery;

class TransactionSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_all_syncs_all_active_addresses()
    {
        TrackedAddress::create(['address' => '0xactive1', 'chain' => 'bsc', 'is_active' => true]);
        TrackedAddress::create(['address' => '0xactive2', 'chain' => 'eth', 'is_active' => true]);
        TrackedAddress::create(['address' => '0xinactive', 'chain' => 'bsc', 'is_active' => false]);

        $mockClient = Mockery::mock(MoralisClient::class);
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([]);
        $mockClient->shouldReceive('getTokenTransfers')->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $result = $service->syncAll();

        $this->assertEquals(2, $result['synced']);
        $this->assertEquals(0, $result['errors']);
    }

    public function test_sync_all_filters_by_chain()
    {
        TrackedAddress::create(['address' => '0xbsc', 'chain' => 'bsc', 'is_active' => true]);
        TrackedAddress::create(['address' => '0xeth', 'chain' => 'eth', 'is_active' => true]);

        $mockClient = Mockery::mock(MoralisClient::class);
        $mockClient->shouldReceive('getNormalTransactions')->once()->andReturn([]);
        $mockClient->shouldReceive('getTokenTransfers')->once()->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->once()->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $result = $service->syncAll('bsc');

        $this->assertEquals(1, $result['synced']);
    }

    public function test_sync_by_address_creates_transactions()
    {
        $mockClient = Mockery::mock(MoralisClient::class);
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([
            [
                'hash'              => '0xtx1',
                'block_number'      => '100',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xfrom',
                'to_address'        => '0xto',
                'value'             => '1000000000000000000',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '21000',
                'receipt_status'    => '1',
            ],
        ]);
        $mockClient->shouldReceive('getTokenTransfers')->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $result = $service->syncByAddress('0xwallet', 'bsc', 0);

        $this->assertEquals(1, $result['new']);
        $this->assertEquals(100, $result['highestBlock']);

        $this->assertDatabaseHas('chain_transactions', [
            'tx_hash'         => '0xtx1',
            'tracked_address' => '0xwallet',
            'chain'           => 'bsc',
            'type'            => 'normal',
        ]);
    }

    public function test_sync_by_address_updates_tracked_address_last_synced_block()
    {
        $tracked = TrackedAddress::create([
            'address'           => '0xwallet',
            'chain'             => 'bsc',
            'is_active'         => true,
            'last_synced_block' => 0,
        ]);

        $mockClient = Mockery::mock(MoralisClient::class);
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([
            [
                'hash'              => '0xtx1',
                'block_number'      => '200',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xfrom',
                'to_address'        => '0xto',
                'value'             => '1000000000000000000',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '21000',
                'receipt_status'    => '1',
            ],
        ]);
        $mockClient->shouldReceive('getTokenTransfers')->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $service->syncByAddress('0xwallet', 'bsc', 0, $tracked);

        $this->assertEquals(200, $tracked->fresh()->last_synced_block);
        $this->assertNotNull($tracked->fresh()->last_synced_at);
    }

    public function test_upsert_does_not_duplicate_existing_transactions()
    {
        ChainTransaction::create([
            'tx_hash'         => '0xexisting',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
            'value_native'    => '1.0',
        ]);

        $mockClient = Mockery::mock(MoralisClient::class);
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([
            [
                'hash'              => '0xexisting',
                'block_number'      => '100',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xfrom',
                'to_address'        => '0xto',
                'value'             => '2000000000000000000',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '21000',
                'receipt_status'    => '1',
            ],
        ]);
        $mockClient->shouldReceive('getTokenTransfers')->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $result = $service->syncByAddress('0xwallet', 'bsc', 0);

        $this->assertEquals(0, $result['new']);
        $this->assertEquals(1, ChainTransaction::count());
    }

    public function test_wei_to_native_conversion()
    {
        $mockClient = Mockery::mock(MoralisClient::class);
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([
            [
                'hash'              => '0xtx1',
                'block_number'      => '100',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xfrom',
                'to_address'        => '0xto',
                'value'             => '1500000000000000000',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '21000',
                'receipt_status'    => '1',
            ],
        ]);
        $mockClient->shouldReceive('getTokenTransfers')->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $service->syncByAddress('0xwallet', 'bsc', 0);

        $tx = ChainTransaction::first();
        $this->assertEquals('1.500000000000000000', $tx->value_native);
    }

    public function test_handles_token_transfers()
    {
        $mockClient = Mockery::mock(MoralisClient::class);
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([]);
        $mockClient->shouldReceive('getTokenTransfers')->andReturn([
            [
                'transaction_hash'  => '0xtoken1',
                'block_number'      => '150',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xfrom',
                'to_address'        => '0xto',
                'value'             => '1000000000000000000',
                'token_name'        => 'Test Token',
                'token_symbol'      => 'TEST',
                'token_decimals'    => '18',
                'address'           => '0xtoken_contract',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '50000',
                'receipt_status'    => '1',
            ],
        ]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $result = $service->syncByAddress('0xwallet', 'bsc', 0);

        $this->assertEquals(1, $result['new']);

        $tx = ChainTransaction::first();
        $this->assertEquals('token', $tx->type);
        $this->assertEquals('Test Token', $tx->token_name);
        $this->assertEquals('TEST', $tx->token_symbol);
    }
}
