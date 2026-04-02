<?php

namespace Locpx\MoralisTracker\Tests\Unit\Models;

use Locpx\MoralisTracker\Models\TrackedAddress;
use Locpx\MoralisTracker\Models\ChainTransaction;
use Locpx\MoralisTracker\Tests\TestCase;

class TrackedAddressTest extends TestCase
{
    public function test_can_create_tracked_address()
    {
        $address = TrackedAddress::create([
            'address'    => '0x1234567890abcdef',
            'chain'      => 'bsc',
            'label'      => 'Test Wallet',
            'is_active'  => true,
        ]);

        $this->assertDatabaseHas('tracked_addresses', [
            'address' => '0x1234567890abcdef',
            'chain'   => 'bsc',
            'label'   => 'Test Wallet',
        ]);

        $this->assertTrue($address->is_active);
    }

    public function test_can_mark_address_as_synced()
    {
        $address = TrackedAddress::create([
            'address'           => '0xtest',
            'chain'             => 'eth',
            'is_active'         => true,
            'last_synced_block' => 0,
        ]);

        $address->markSynced(12345);

        $this->assertEquals(12345, $address->fresh()->last_synced_block);
        $this->assertNotNull($address->fresh()->last_synced_at);
    }

    public function test_active_scope_filters_correctly()
    {
        TrackedAddress::create(['address' => '0xactive', 'chain' => 'bsc', 'is_active' => true]);
        TrackedAddress::create(['address' => '0xinactive', 'chain' => 'bsc', 'is_active' => false]);

        $active = TrackedAddress::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('0xactive', $active->first()->address);
    }

    public function test_get_native_symbol_returns_correct_symbol()
    {
        $bscAddress = TrackedAddress::create(['address' => '0xbsc', 'chain' => 'bsc', 'is_active' => true]);
        $ethAddress = TrackedAddress::create(['address' => '0xeth', 'chain' => 'eth', 'is_active' => true]);

        $this->assertEquals('BNB', $bscAddress->getNativeSymbol());
        $this->assertEquals('ETH', $ethAddress->getNativeSymbol());
    }

    public function test_get_chain_config_returns_correct_config()
    {
        $address = TrackedAddress::create(['address' => '0xtest', 'chain' => 'polygon', 'is_active' => true]);

        $config = $address->getChainConfig();

        $this->assertEquals('Polygon', $config['name']);
        $this->assertEquals('MATIC', $config['native_symbol']);
    }

    public function test_meta_field_is_cast_to_array()
    {
        $address = TrackedAddress::create([
            'address'   => '0xtest',
            'chain'     => 'bsc',
            'is_active' => true,
            'meta'      => ['custom_field' => 'value'],
        ]);

        $this->assertIsArray($address->meta);
        $this->assertEquals('value', $address->meta['custom_field']);
    }

    public function test_transactions_relationship()
    {
        $address = TrackedAddress::create([
            'address'   => '0xwallet',
            'chain'     => 'bsc',
            'is_active' => true,
        ]);

        ChainTransaction::create([
            'tx_hash'         => '0xtx1',
            'type'            => 'normal',
            'chain'           => 'bsc',
            'tracked_address' => '0xwallet',
            'block_number'    => 100,
            'from_address'    => '0xfrom',
            'to_address'      => '0xto',
            'value_native'    => '1.5',
        ]);

        $this->assertCount(1, $address->transactions);
    }
}
