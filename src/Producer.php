<?php

declare(strict_types=1);

namespace App;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Producer
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

    public function publish(array $userData): void
    {
        $channel = $this->connection->channel();

        $channel->exchange_declare(
            exchange: 'main_registro_exchange',
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false
        );

        $message = new AMQPMessage(
            body: json_encode($userData),
            properties: [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]
        );

        $channel->basic_publish(
            msg: $message,
            exchange: 'main_registro_exchange',
            routing_key: 'registro.email'
        );

        echo 'Message sent for user ID: ' . $userData['user_id'] . "\n";

        $channel->close();
        $this->connection->close();
    }
}
