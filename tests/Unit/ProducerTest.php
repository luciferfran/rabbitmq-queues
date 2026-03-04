<?php

declare(strict_types=1);

namespace Tests\Unit;
require_once __DIR__ . '/Support/AmqpFakes.php';
use App\Producer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ProducerTest extends TestCase
{
    public function testConstructorThrowsExceptionWhenConfigIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: host');

        new Producer([]);
    }

    public function testConstructorThrowsExceptionWhenConfigIsIncomplete(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key:');

        new Producer(['host' => 'localhost']);
    }

    public function testPublishThrowsExceptionWhenUserDataIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: user_id');

        $producer = new Producer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $producer->publish([]);
    }

    public function testPublishThrowsExceptionWhenUserIdIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user_id: must be an integer');

        $producer = new Producer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $producer->publish(['user_id' => 'invalid', 'email' => 'test@test.com']);
    }

    public function testPublishThrowsExceptionWhenEmailIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: email');

        $producer = new Producer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $producer->publish(['user_id' => 1]);
    }

    public function testPublishThrowsExceptionWhenEmailIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');

        $producer = new Producer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $producer->publish(['user_id' => 1, 'email' => 'invalid-email']);
    }

    public function testExchangeConfigCanBeCustomized(): void
    {
        $producer = new Producer(
            [
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
            ],
            new NullLogger(),
            [
                'exchange' => 'custom_exchange',
                'routing_key' => 'custom.key',
            ]
        );

        $this->assertInstanceOf(Producer::class, $producer);
    }

    public function testPublishSendsMessage(): void
    {
        $producer = new Producer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $connMock = $this->createMock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);

        $channelMock = $this->getMockBuilder(TestChannel::class)
            ->onlyMethods(['exchange_declare', 'basic_publish', 'close'])
            ->getMock();

        $channelMock->expects($this->once())->method('exchange_declare');
        $channelMock->expects($this->once())->method('basic_publish');
        $channelMock->expects($this->once())->method('close');

        $connMock->method('channel')->willReturn($channelMock);
        $connMock->method('close')->willReturn(null);

        $ref = new \ReflectionProperty(Producer::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($producer, $connMock);

        $this->expectOutputString("Message sent for user ID: 1\n");

        $producer->publish(['user_id' => 1, 'email' => 'test@example.com']);
    }
}
