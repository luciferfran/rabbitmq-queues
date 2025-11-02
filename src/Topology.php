<?php

declare(strict_types=1);

namespace App;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class Topology
{
    private AMQPStreamConnection $connection;

    public function __construct(array $config)
    {
        $this->connection = new AMQPStreamConnection(
            host: $config['host'],
            port: $config['port'],
            user: $config['user'],
            password: $config['password']
        );
    }

    public function setup(): void
    {
        $channel = $this->connection->channel();

        // Declare exchanges
        $channel->exchange_declare(
            exchange: 'main_registro_exchange',
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false
        );

        $channel->exchange_declare(
            exchange: 'dlx_exchange',
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // Main queue with DLX
        $args = new AMQPTable([
            'x-dead-letter-exchange' => 'dlx_exchange',
            'x-dead-letter-routing-key' => 'retry.email',
        ]);

        $channel->queue_declare(
            queue: 'email_queue',
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false,
            arguments: $args
        );

        // Delay queue
        $delayArgs = new AMQPTable([
            'x-dead-letter-exchange' => 'main_registro_exchange',
            'x-dead-letter-routing-key' => 'registro.email',
            'x-message-ttl' => 30000,
        ]);

        $channel->queue_declare(
            queue: 'retry_queue',
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false,
            arguments: $delayArgs
        );

        // Final dead letter queue
        $channel->queue_declare(
            queue: 'dead_letter_queue',
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false
        );

        // Final DLX
        $channel->exchange_declare(
            exchange: 'final_dlx_exchange',
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // Bindings
        $channel->queue_bind(queue: 'email_queue', exchange: 'main_registro_exchange', routing_key: 'registro.email');
        $channel->queue_bind(queue: 'retry_queue', exchange: 'dlx_exchange', routing_key: 'retry.email');
        $channel->queue_bind(queue: 'dead_letter_queue', exchange: 'final_dlx_exchange', routing_key: 'dead.email');

        echo "Topology configured successfully\n";

        $channel->close();
        $this->connection->close();
    }
}
