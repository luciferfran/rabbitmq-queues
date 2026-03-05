<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/TestChannel.php';

use App\Topology;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;

class TopologyTest extends TestCase
{
    public function testConstructorThrowsExceptionWhenConfigIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: host');

        new Topology([]);
    }

    public function testConstructorThrowsExceptionWhenPortIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: port');

        new Topology(['host' => 'localhost']);
    }

    public function testConstructorThrowsExceptionWhenUserIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: user');

        new Topology([
            'host' => 'localhost',
            'port' => 5672,
        ]);
    }

    public function testConstructorThrowsExceptionWhenPasswordIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: password');

        new Topology([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
        ]);
    }

    public function testTopologyConfigCanBeCustomized(): void
    {
        $topology = new Topology(
            [
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
            ],
            new NullLogger(),
            [
                'main_exchange' => 'custom_main_exchange',
                'main_queue' => 'custom_main_queue',
                'retry_ttl' => 60000,
            ]
        );

        $this->assertInstanceOf(Topology::class, $topology);
    }

    public function testSetupCreatesExchangesAndQueues(): void
    {
        $topology = new Topology([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $connMock = $this->createMock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);

        $channelMock = $this->getMockBuilder(TestChannel::class)
            ->onlyMethods([
                'exchange_declare',
                'queue_declare',
                'queue_bind',
                'close',
            ])
            ->getMock();

        $channelMock->expects($this->exactly(3))->method('exchange_declare');
        $channelMock->expects($this->exactly(3))->method('queue_declare');
        $channelMock->expects($this->exactly(3))->method('queue_bind');
        $channelMock->expects($this->once())->method('close');

        $connMock->method('channel')->willReturn($channelMock);
        $connMock->method('close')->willReturn(null);

        $ref = new ReflectionProperty(Topology::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($topology, $connMock);

        $this->expectOutputString("Topology configured successfully\n");

        $topology->setup();
    }

    public function testSetupWithCustomTopologyConfig(): void
    {
        $topology = new Topology(
            [
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
            ],
            new NullLogger(),
            [
                'main_exchange' => 'custom_main',
                'dlx_exchange' => 'custom_dlx',
                'final_dlx_exchange' => 'custom_final_dlx',
                'main_queue' => 'custom_queue',
                'retry_queue' => 'custom_retry',
                'dead_letter_queue' => 'custom_dead',
                'main_routing_key' => 'custom.routing.key',
                'retry_routing_key' => 'custom.retry.key',
                'dead_letter_routing_key' => 'custom.dead.key',
                'retry_ttl' => 60000,
            ]
        );

        $connMock = $this->createMock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);

        $channelMock = $this->getMockBuilder(TestChannel::class)
            ->onlyMethods([
                'exchange_declare',
                'queue_declare',
                'queue_bind',
                'close',
            ])
            ->getMock();

        $connMock->method('channel')->willReturn($channelMock);
        $connMock->method('close')->willReturn(null);

        $ref = new ReflectionProperty(Topology::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($topology, $connMock);

        $topology->setup();

        $this->assertTrue(true);
    }
}
