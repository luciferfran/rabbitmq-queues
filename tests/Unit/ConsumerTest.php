<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/TestChannel.php';

use App\Consumer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;

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

    public function testProcessMessageSuccessAcknowledges(): void
    {
        $consumer = new Consumer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $connMock = $this->createMock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);

        $channelMock = $this->getMockBuilder(TestChannel::class)
            ->onlyMethods(['basic_ack', 'basic_publish', 'basic_nack'])
            ->getMock();

        $channelMock->expects($this->once())->method('basic_ack');

        $connMock->method('channel')->willReturn($channelMock);
        $connMock->method('close')->willReturn(null);

        $ref = new ReflectionProperty(Consumer::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($consumer, $connMock);

        $messageMock = $this->getMockBuilder(\PhpAmqpLib\Message\AMQPMessage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChannel', 'getDeliveryTag'])
            ->getMock();

        $messageMock->body = json_encode(['user_id' => 1, 'email' => 'a@b.com']);
        $messageMock->method('getChannel')->willReturn($channelMock);
        $messageMock->method('getDeliveryTag')->willReturn(1);

        $this->expectOutputRegex('/Processing message for user ID:/');
        $consumer->processMessage($messageMock);
    }

    public function testProcessMessageRetriesAndMovesToDeadLetter(): void
    {
        $consumer = new Consumer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $connMock = $this->createMock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);

        $channelMock = $this->getMockBuilder(TestChannel::class)
            ->onlyMethods(['basic_ack', 'basic_publish', 'basic_nack'])
            ->getMock();

        $channelMock->expects($this->any())->method('basic_publish');
        $channelMock->expects($this->any())->method('basic_ack');

        $connMock->method('channel')->willReturn($channelMock);
        $connMock->method('close')->willReturn(null);

        $ref = new ReflectionProperty(Consumer::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($consumer, $connMock);

        $messageMock = $this->getMockBuilder(\PhpAmqpLib\Message\AMQPMessage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChannel', 'getDeliveryTag'])
            ->getMock();

        $messageMock->body = json_encode(['user_id' => 2, 'email' => 'a@b.com', 'retries' => 0]);
        $messageMock->method('getChannel')->willReturn($channelMock);
        $messageMock->method('getDeliveryTag')->willReturn(1);

        $this->expectOutputRegex('/Retrying later/');
        $consumer->processMessage($messageMock);
    }

    public function testProcessMessageWithInvalidJson(): void
    {
        $consumer = new Consumer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $channelMock = $this->createMock(\PhpAmqpLib\Channel\AMQPChannel::class);
        $channelMock->expects($this->once())->method('basic_nack');

        $messageMock = $this->getMockBuilder(\PhpAmqpLib\Message\AMQPMessage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChannel', 'getDeliveryTag'])
            ->getMock();

        $messageMock->body = 'invalid json';
        $messageMock->method('getChannel')->willReturn($channelMock);
        $messageMock->method('getDeliveryTag')->willReturn(1);

        $consumer->processMessage($messageMock);
    }

    public function testProcessMessageWithMissingUserId(): void
    {
        $consumer = new Consumer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $channelMock = $this->createMock(\PhpAmqpLib\Channel\AMQPChannel::class);
        $channelMock->expects($this->once())->method('basic_nack');

        $messageMock = $this->getMockBuilder(\PhpAmqpLib\Message\AMQPMessage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChannel', 'getDeliveryTag'])
            ->getMock();

        $messageMock->body = json_encode(['email' => 'a@b.com']);
        $messageMock->method('getChannel')->willReturn($channelMock);
        $messageMock->method('getDeliveryTag')->willReturn(1);

        $consumer->processMessage($messageMock);
    }

    public function testProcessMessageMovesToDeadLetterAfterMaxRetries(): void
    {
        $consumer = new Consumer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $connMock = $this->createMock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);

        $channelMock = $this->getMockBuilder(TestChannel::class)
            ->onlyMethods(['basic_ack', 'basic_publish', 'basic_nack'])
            ->getMock();

        $channelMock->expects($this->once())->method('basic_publish');
        $channelMock->expects($this->once())->method('basic_ack');

        $connMock->method('channel')->willReturn($channelMock);
        $connMock->method('close')->willReturn(null);

        $ref = new ReflectionProperty(Consumer::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($consumer, $connMock);

        $messageMock = $this->getMockBuilder(\PhpAmqpLib\Message\AMQPMessage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChannel', 'getDeliveryTag'])
            ->getMock();

        $messageMock->body = json_encode(['user_id' => 2, 'email' => 'a@b.com', 'retries' => 2]);
        $messageMock->method('getChannel')->willReturn($channelMock);
        $messageMock->method('getDeliveryTag')->willReturn(1);

        $this->expectOutputRegex('/dead letter/');
        $consumer->processMessage($messageMock);
    }

    public function testProcessMessageWithMissingEmail(): void
    {
        $consumer = new Consumer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ], new NullLogger());

        $channelMock = $this->createMock(\PhpAmqpLib\Channel\AMQPChannel::class);
        $channelMock->expects($this->once())->method('basic_nack');

        $messageMock = $this->getMockBuilder(\PhpAmqpLib\Message\AMQPMessage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChannel', 'getDeliveryTag'])
            ->getMock();

        $messageMock->body = json_encode(['user_id' => 1]);
        $messageMock->method('getChannel')->willReturn($channelMock);
        $messageMock->method('getDeliveryTag')->willReturn(1);

        $consumer->processMessage($messageMock);
    }

    public function testDestructorClosesConnection(): void
    {
        $consumer = new Consumer([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
        ]);

        $connMock = $this->createMock(\PhpAmqpLib\Connection\AMQPStreamConnection::class);
        $connMock->expects($this->once())->method('isConnected')->willReturn(true);
        $connMock->expects($this->once())->method('close');

        $ref = new ReflectionProperty(Consumer::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($consumer, $connMock);

        unset($consumer);
    }
}
