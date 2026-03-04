<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class Producer
{
    private ?AMQPStreamConnection $connection = null;
    private LoggerInterface $logger;
    private array $config;
    private array $exchangeConfig;

    public function __construct(array $config, LoggerInterface $logger = null, array $exchangeConfig = [])
    {
        $this->logger = $logger ?? new NullLogger();
        $this->config = $this->validateConfig($config);
        $this->exchangeConfig = $this->validateExchangeConfig($exchangeConfig);
    }

    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null) {
            $this->connection = new AMQPStreamConnection(
                host: $this->config['host'],
                port: $this->config['port'],
                user: $this->config['user'],
                password: $this->config['password']
            );
        }

        return $this->connection;
    }

    private function validateConfig(array $config): array
    {
        $requiredKeys = ['host', 'port', 'user', 'password'];

        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException("Missing required configuration key: {$key}");
            }
        }

        return [
            'host' => $config['host'],
            'port' => filter_var($config['port'], FILTER_VALIDATE_INT) ? (int)$config['port'] : $config['port'],
            'user' => $config['user'],
            'password' => $config['password'],
        ];
    }

    private function validateExchangeConfig(array $config): array
    {
        return [
            'exchange' => $config['exchange'] ?? 'main_registro_exchange',
            'type' => $config['type'] ?? 'topic',
            'routing_key' => $config['routing_key'] ?? 'registro.email',
        ];
    }

    public function publish(array $userData): void
    {
        $this->validateUserData($userData);

        $channel = $this->getConnection()->channel();

        $channel->exchange_declare(
            exchange: $this->exchangeConfig['exchange'],
            type: $this->exchangeConfig['type'],
            passive: false,
            durable: true,
            auto_delete: false
        );

        $messageBody = json_encode($userData);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to encode message: ' . json_last_error_msg());
        }

        $message = new AMQPMessage(
            body: $messageBody,
            properties: [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]
        );

        $channel->basic_publish(
            msg: $message,
            exchange: $this->exchangeConfig['exchange'],
            routing_key: $this->exchangeConfig['routing_key']
        );

        $this->logger->info('Message published successfully', [
            'user_id' => $userData['user_id'],
            'exchange' => $this->exchangeConfig['exchange'],
            'routing_key' => $this->exchangeConfig['routing_key'],
        ]);

        echo 'Message sent for user ID: ' . $userData['user_id'] . "\n";

        $channel->close();
        $this->getConnection()->close();
    }

    private function validateUserData(array $data): void
    {
        if (!isset($data['user_id'])) {
            throw new InvalidArgumentException('Missing required field: user_id');
        }

        if (!filter_var($data['user_id'], FILTER_VALIDATE_INT)) {
            throw new InvalidArgumentException('Invalid user_id: must be an integer');
        }

        if (!isset($data['email'])) {
            throw new InvalidArgumentException('Missing required field: email');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }
    }

    public function __destruct()
    {
        if (isset($this->connection) && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
