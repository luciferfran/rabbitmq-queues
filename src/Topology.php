<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Topology
{
    private const DEFAULT_RETRY_TTL = 30000;

    private ?AMQPStreamConnection $connection = null;
    private LoggerInterface $logger;
    private array $config;
    private array $topologyConfig;

    public function __construct(array $config, ?LoggerInterface $logger = null, array $topologyConfig = [])
    {
        $this->logger = $logger ?? new NullLogger();
        $this->config = $this->validateConfig($config);
        $this->topologyConfig = $this->validateTopologyConfig($topologyConfig);
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

    private function validateTopologyConfig(array $config): array
    {
        return [
            'exchanges' => [
                'main' => $config['main_exchange'] ?? 'main_registro_exchange',
                'dlx' => $config['dlx_exchange'] ?? 'dlx_exchange',
                'final_dlx' => $config['final_dlx_exchange'] ?? 'final_dlx_exchange',
            ],
            'queues' => [
                'main' => $config['main_queue'] ?? 'email_queue',
                'retry' => $config['retry_queue'] ?? 'retry_queue',
                'dead_letter' => $config['dead_letter_queue'] ?? 'dead_letter_queue',
            ],
            'routing_keys' => [
                'main' => $config['main_routing_key'] ?? 'registro.email',
                'retry' => $config['retry_routing_key'] ?? 'retry.email',
                'dead_letter' => $config['dead_letter_routing_key'] ?? 'dead.email',
            ],
            'retry_ttl' => $config['retry_ttl'] ?? self::DEFAULT_RETRY_TTL,
        ];
    }

    public function setup(): void
    {
        $channel = $this->getConnection()->channel();
        $exchanges = $this->topologyConfig['exchanges'];
        $queues = $this->topologyConfig['queues'];
        $routingKeys = $this->topologyConfig['routing_keys'];

        // Declare exchanges
        $channel->exchange_declare(
            exchange: $exchanges['main'],
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false
        );

        $channel->exchange_declare(
            exchange: $exchanges['dlx'],
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false
        );

        $channel->exchange_declare(
            exchange: $exchanges['final_dlx'],
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // Main queue with DLX
        $args = new AMQPTable([
            'x-dead-letter-exchange' => $exchanges['dlx'],
            'x-dead-letter-routing-key' => $routingKeys['retry'],
        ]);

        $channel->queue_declare(
            queue: $queues['main'],
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false,
            arguments: $args
        );

        // Delay queue
        $delayArgs = new AMQPTable([
            'x-dead-letter-exchange' => $exchanges['main'],
            'x-dead-letter-routing-key' => $routingKeys['main'],
            'x-message-ttl' => $this->topologyConfig['retry_ttl'],
        ]);

        $channel->queue_declare(
            queue: $queues['retry'],
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false,
            arguments: $delayArgs
        );

        // Final dead letter queue
        $channel->queue_declare(
            queue: $queues['dead_letter'],
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false
        );

        // Bindings
        $channel->queue_bind(
            queue: $queues['main'],
            exchange: $exchanges['main'],
            routing_key: $routingKeys['main']
        );
        $channel->queue_bind(
            queue: $queues['retry'],
            exchange: $exchanges['dlx'],
            routing_key: $routingKeys['retry']
        );
        $channel->queue_bind(
            queue: $queues['dead_letter'],
            exchange: $exchanges['final_dlx'],
            routing_key: $routingKeys['dead_letter']
        );

        $this->logger->info('Topology configured successfully', [
            'exchanges' => $exchanges,
            'queues' => $queues,
            'routing_keys' => $routingKeys,
        ]);

        echo "Topology configured successfully\n";

        $channel->close();
        $this->getConnection()->close();
    }

    public function __destruct()
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
