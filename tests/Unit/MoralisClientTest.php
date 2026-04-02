<?php

namespace Locpx\MoralisTracker\Tests\Unit;

use Locpx\MoralisTracker\MoralisClient;
use Locpx\MoralisTracker\Tests\TestCase;

class MoralisClientTest extends TestCase
{
    public function test_client_initializes_with_config()
    {
        $client = new MoralisClient();

        $this->assertInstanceOf(MoralisClient::class, $client);
    }

    public function test_resolve_chain_returns_moralis_id()
    {
        $client = new MoralisClient();
        
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('resolveChain');
        $method->setAccessible(true);

        $this->assertEquals('bsc', $method->invoke($client, 'bsc'));
        $this->assertEquals('eth', $method->invoke($client, 'eth'));
        $this->assertEquals('polygon', $method->invoke($client, 'polygon'));
    }

    public function test_client_methods_exist()
    {
        $client = new MoralisClient();

        $this->assertTrue(method_exists($client, 'getNormalTransactions'));
        $this->assertTrue(method_exists($client, 'getTokenTransfers'));
        $this->assertTrue(method_exists($client, 'getNftTransfers'));
        $this->assertTrue(method_exists($client, 'getBalance'));
    }
}
