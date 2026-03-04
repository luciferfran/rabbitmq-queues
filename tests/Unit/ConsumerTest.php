<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Consumer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConsumerTest extends TestCase
{
    public function testConstructorThrowsExceptionWhenConfigIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: host');

        new Consumer([]);
    }

    public function testConstructorThrowsExceptionWhenPortIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: port');

        new Consumer(['host' => 'localhost']);
    }

    public function testConstructorThrowsExceptionWhenUserIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: user');

        new Consumer([
            'host' => 'localhost',
            'port' => 5672,
        ]);
    }

    public function testConstructorThrowsExceptionWhenPasswordIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: password');

        new Consumer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
        ]);
    }

    public function testQueueConfigCanBeCustomized(): void
    {
        $consumer = new Consumer(
            [
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
            ],
            new NullLogger(),
            [
                'queue' => 'custom_queue',
                'retry_exchange' => 'custom_retry_exchange',
            ]
        );

        $this->assertInstanceOf(Consumer::class, $consumer);
    }
}
