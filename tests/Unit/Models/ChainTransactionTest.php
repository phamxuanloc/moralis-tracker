<?php

namespace Locpx\MoralisTracker\Tests\Unit\Models;

use Locpx\MoralisTracker\Models\ChainTransaction;
use Locpx\MoralisTracker\Models\TrackedAddress;
use Locpx\MoralisTracker\Tests\TestCase;

class ChainTransactionTest extends TestCase
{
    public function test_can_create_chain_transaction()
    {
        $tx = ChainTransaction::create([
            'tx_hash'         => '0xabc123',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 12345,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
            'value_native'    => '1.5',
            'tx_fee_native'   => '0.001',
        ]);

        $this->assertDatabaseHas('chain_transactions', [
            'tx_hash'      => '0xabc123',
            'type'         => 'normal',
            'chain'        => 'bsc',
            'block_number' => 12345,
        ]);
    }

    public function test_of_type_scope()
    {
        ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xtx2',
            'type'            => 'token',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 101,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
        ]);

        $normalTxs = ChainTransaction::ofType('normal')->get();
        $tokenTxs = ChainTransaction::ofType('token')->get();

        $this->assertCount(1, $normalTxs);
        $this->assertCount(1, $tokenTxs);
    }

    public function test_on_chain_scope()
    {
        ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xtx2',
            'type'            => 'normal',
            'chain'           => 'eth',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
        ]);

        $bscTxs = ChainTransaction::onChain('bsc')->get();
        $ethTxs = ChainTransaction::onChain('eth')->get();

        $this->assertCount(1, $bscTxs);
        $this->assertCount(1, $ethTxs);
    }

    public function test_for_address_scope()
    {
        ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet1',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xtx2',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet2',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
        ]);

        $wallet1Txs = ChainTransaction::forAddress('0xwallet1')->get();

        $this->assertCount(1, $wallet1Txs);
        $this->assertEquals('0xwallet1', $wallet1Txs->first()->tracked_address);
    }

    public function test_incoming_scope()
    {
        ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xother',
            'to_address'      => '0xwallet',
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xtx2',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 101,
            'from_address'    => '0xwallet',
            'to_address'      => '0xother',
        ]);

        $incoming = ChainTransaction::incoming('0xwallet')->get();

        $this->assertCount(1, $incoming);
        $this->assertEquals('0xwallet', $incoming->first()->to_address);
    }

    public function test_outgoing_scope()
    {
        ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xother',
            'to_address'      => '0xwallet',
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xtx2',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 101,
            'from_address'    => '0xwallet',
            'to_address'      => '0xother',
        ]);

        $outgoing = ChainTransaction::outgoing('0xwallet')->get();

        $this->assertCount(1, $outgoing);
        $this->assertEquals('0xwallet', $outgoing->first()->from_address);
    }

    public function test_raw_data_is_cast_to_array()
    {
        $tx = ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
            'raw_data'        => ['custom' => 'data'],
        ]);

        $this->assertIsArray($tx->raw_data);
        $this->assertEquals('data', $tx->raw_data['custom']);
    }

    public function test_combined_scopes()
    {
        ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'token',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xother',
            'to_address'      => '0xwallet',
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

        $tokenIncoming = ChainTransaction::ofType('token')
            ->onChain('bsc')
            ->incoming('0xwallet')
            ->get();

        $this->assertCount(1, $tokenIncoming);
        $this->assertEquals('token', $tokenIncoming->first()->type);
    }
}
