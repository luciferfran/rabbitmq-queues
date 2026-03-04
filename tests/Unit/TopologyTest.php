<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Topology;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

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
}
