<?php

namespace Locpx\MoralisTracker\Tests\Feature;

use Locpx\MoralisTracker\MoralisClient;
use Locpx\MoralisTracker\Models\ChainTransaction;
use Locpx\MoralisTracker\Models\TrackedAddress;
use Locpx\MoralisTracker\Services\TransactionSyncService;
use Locpx\MoralisTracker\Tests\TestCase;
use Mockery;

class SyncWorkflowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_complete_sync_workflow()
    {
        $address = TrackedAddress::create([
            'address'           => '0xtest_wallet',
            'chain'             => 'bsc',
            'label'             => 'Test Wallet',
            'is_active'         => true,
            'last_synced_block' => 0,
        ]);

        $mockClient = Mockery::mock(MoralisClient::class);
        
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([
            [
                'hash'              => '0xtx1',
                'block_number'      => '100',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xsender',
                'to_address'        => '0xtest_wallet',
                'value'             => '1000000000000000000',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '21000',
                'receipt_status'    => '1',
            ],
        ]);

        $mockClient->shouldReceive('getTokenTransfers')->andReturn([
            [
                'transaction_hash'  => '0xtx2',
                'block_number'      => '150',
                'block_timestamp'   => '2024-01-02T00:00:00Z',
                'from_address'      => '0xsender',
                'to_address'        => '0xtest_wallet',
                'value'             => '5000000000000000000',
                'token_name'        => 'USDT',
                'token_symbol'      => 'USDT',
                'token_decimals'    => '18',
                'address'           => '0xusdt_contract',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '50000',
                'receipt_status'    => '1',
            ],
        ]);

        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $result = $service->syncAddress($address);

        $this->assertEquals(2, $result['new']);
        $this->assertEquals(150, $result['highestBlock']);

        $this->assertEquals(150, $address->fresh()->last_synced_block);
        $this->assertNotNull($address->fresh()->last_synced_at);

        $this->assertEquals(2, ChainTransaction::count());

        $normalTx = ChainTransaction::ofType('normal')->first();
        $this->assertEquals('0xtx1', $normalTx->tx_hash);
        $this->assertEquals('1.000000000000000000', $normalTx->value_native);

        $tokenTx = ChainTransaction::ofType('token')->first();
        $this->assertEquals('0xtx2', $tokenTx->tx_hash);
        $this->assertEquals('USDT', $tokenTx->token_name);
    }

    public function test_multi_chain_sync()
    {
        $bscAddress = TrackedAddress::create([
            'address'   => '0xwallet',
            'chain'     => 'bsc',
            'is_active' => true,
        ]);

        $ethAddress = TrackedAddress::create([
            'address'   => '0xwallet',
            'chain'     => 'eth',
            'is_active' => true,
        ]);

        $mockClient = Mockery::mock(MoralisClient::class);
        
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([
            [
                'hash'              => '0xbsc_tx',
                'block_number'      => '100',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xfrom',
                'to_address'        => '0xwallet',
                'value'             => '1000000000000000000',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '21000',
                'receipt_status'    => '1',
            ],
        ], [
            [
                'hash'              => '0xeth_tx',
                'block_number'      => '200',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xfrom',
                'to_address'        => '0xwallet',
                'value'             => '2000000000000000000',
                'gas_price'         => '10000000000',
                'receipt_gas_used'  => '21000',
                'receipt_status'    => '1',
            ],
        ]);

        $mockClient->shouldReceive('getTokenTransfers')->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $service->syncAll();

        $this->assertEquals(2, ChainTransaction::count());

        $bscTxs = ChainTransaction::onChain('bsc')->get();
        $ethTxs = ChainTransaction::onChain('eth')->get();

        $this->assertCount(1, $bscTxs);
        $this->assertCount(1, $ethTxs);

        $this->assertEquals('0xbsc_tx', $bscTxs->first()->tx_hash);
        $this->assertEquals('0xeth_tx', $ethTxs->first()->tx_hash);
    }

    public function test_incremental_sync_from_last_block()
    {
        $address = TrackedAddress::create([
            'address'           => '0xwallet',
            'chain'             => 'bsc',
            'is_active'         => true,
            'last_synced_block' => 100,
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xold_tx',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xwallet',
        ]);

        $mockClient = Mockery::mock(MoralisClient::class);
        
        $mockClient->shouldReceive('getNormalTransactions')
            ->with('0xwallet', 'bsc', 100)
            ->andReturn([
                [
                    'hash'              => '0xnew_tx',
                    'block_number'      => '200',
                    'block_timestamp'   => '2024-01-01T00:00:00Z',
                    'from_address'      => '0xfrom',
                    'to_address'        => '0xwallet',
                    'value'             => '1000000000000000000',
                    'gas_price'         => '5000000000',
                    'receipt_gas_used'  => '21000',
                    'receipt_status'    => '1',
                ],
            ]);

        $mockClient->shouldReceive('getTokenTransfers')->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $result = $service->syncAddress($address);

        $this->assertEquals(1, $result['new']);
        $this->assertEquals(200, $address->fresh()->last_synced_block);

        $this->assertEquals(2, ChainTransaction::count());
    }

    public function test_handles_failed_transactions()
    {
        $mockClient = Mockery::mock(MoralisClient::class);
        
        $mockClient->shouldReceive('getNormalTransactions')->andReturn([
            [
                'hash'              => '0xfailed_tx',
                'block_number'      => '100',
                'block_timestamp'   => '2024-01-01T00:00:00Z',
                'from_address'      => '0xfrom',
                'to_address'        => '0xto',
                'value'             => '1000000000000000000',
                'gas_price'         => '5000000000',
                'receipt_gas_used'  => '21000',
                'receipt_status'    => '0',
            ],
        ]);

        $mockClient->shouldReceive('getTokenTransfers')->andReturn([]);
        $mockClient->shouldReceive('getNftTransfers')->andReturn([]);

        $service = new TransactionSyncService($mockClient);
        $service->syncByAddress('0xwallet', 'bsc', 0);

        $tx = ChainTransaction::first();
        $this->assertTrue($tx->is_error);
        $this->assertEquals('0', $tx->tx_receipt_status);
    }

    public function test_query_transactions_with_multiple_filters()
    {
        ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'token',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xother',
            'to_address'      => '0xwallet',
            'token_symbol'    => 'USDT',
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xtx2',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 101,
            'from_address'    => '0xother',
            'to_address'      => '0xwallet',
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xtx3',
            'type'            => 'token',
            'chain'           => 'eth',
            'tracked_address' => '0xwallet',
            'block_number'    => 102,
            'from_address'    => '0xother',
            'to_address'      => '0xwallet',
            'token_symbol'    => 'DAI',
        ]);

        $results = ChainTransaction::forAddress('0xwallet')
            ->onChain('bsc')
            ->ofType('token')
            ->incoming('0xwallet')
            ->latest('block_number')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('0xtx1', $results->first()->tx_hash);
        $this->assertEquals('USDT', $results->first()->token_symbol);
    }
}
