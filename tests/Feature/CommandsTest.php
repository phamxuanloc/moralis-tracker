<?php

namespace Locpx\MoralisTracker\Tests\Feature;

use Locpx\MoralisTracker\Models\TrackedAddress;
use Locpx\MoralisTracker\Tests\TestCase;

class CommandsTest extends TestCase
{
    public function test_add_address_command_creates_tracked_address()
    {
        $this->artisan('moralis:add-address', [
            'address' => '0x742d35cc6634c0532925a3b844bc9e7595f0bee1',
            '--label' => 'Test Wallet',
        ])->assertSuccessful();

        $this->assertDatabaseHas('tracked_addresses', [
            'address'   => '0x742d35cc6634c0532925a3b844bc9e7595f0bee1',
            'chain'     => 'bsc',
            'label'     => 'Test Wallet',
            'is_active' => true,
        ]);
    }

    public function test_add_address_command_with_custom_chain()
    {
        $this->artisan('moralis:add-address', [
            'address' => '0x8ba1f109551bd432803012645ac136ddd64dba72',
            '--chain' => 'eth',
            '--label' => 'ETH Wallet',
        ])->assertSuccessful();

        $this->assertDatabaseHas('tracked_addresses', [
            'address' => '0x8ba1f109551bd432803012645ac136ddd64dba72',
            'chain'   => 'eth',
            'label'   => 'ETH Wallet',
        ]);
    }

    public function test_add_address_command_prevents_duplicates()
    {
        TrackedAddress::create([
            'address'   => '0x1234567890123456789012345678901234567890',
            'chain'     => 'bsc',
            'is_active' => true,
        ]);

        $this->artisan('moralis:add-address', [
            'address' => '0x1234567890123456789012345678901234567890',
        ])->assertSuccessful();

        $this->assertEquals(1, TrackedAddress::where('address', '0x1234567890123456789012345678901234567890')->count());
    }

    public function test_add_address_normalizes_to_lowercase()
    {
        $this->artisan('moralis:add-address', [
            'address' => '0xABCDEF1234567890ABCDEF1234567890ABCDEF12',
        ])->assertSuccessful();

        $this->assertDatabaseHas('tracked_addresses', [
            'address' => '0xabcdef1234567890abcdef1234567890abcdef12',
        ]);
    }
}
