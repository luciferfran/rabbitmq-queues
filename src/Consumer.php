<?php

declare(strict_types=1);

namespace App;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Consumer
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

    public function run(): void
    {
        $channel = $this->connection->channel();

        $channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

        $channel->basic_consume(
            queue: 'email_queue',
            consumer_tag: '',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: [$this, 'processMessage']
        );

        echo "Waiting for messages. To exit press CTRL+C\n";

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $this->connection->close();
    }

    public function processMessage(AMQPMessage $message): void
    {
        $channel = $message->getChannel();
        $data = json_decode($message->body, true);

        echo sprintf(
            format: "[%s] Processing message for user ID: %d (Attempt %d of 3)\n",
            values: date(format: 'H:i:s'),
            $data['user_id'],
            $data['retries'] + 1
        );

        $shouldFail = ($data['user_id'] % 2 == 0);

        if ($shouldFail) {
            echo "Error processing message...\n";

            if ($data['retries'] < 2) {
                $data['retries']++;

                $retryMessage = new AMQPMessage(
                    body: json_encode($data),
                    properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                );
                echo sprintf(
                    format: "Retrying later (attempt %d of 3)...\n",
                    values: $data['retries']
                );

                $channel->basic_publish(
                    msg: $retryMessage,
                    exchange: 'dlx_exchange',
                    routing_key: 'retry.email'
                );

                $channel->basic_ack(delivery_tag: (string)$message->getDeliveryTag());
            } else {
                echo "Unrecoverable error. Moving to dead letter queue.\n";

                $deadMessage = new AMQPMessage(
                    body: json_encode([
                        'original_message' => $data,
                        'error' => 'Maximum number of retries reached',
                        'last_retry' => date('Y-m-d H:i:s'),
                    ]),
                    properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                );

                $channel->basic_publish(
                    msg: $deadMessage,
                    exchange: 'final_dlx_exchange',
                    routing_key: 'dead.email'
                );

                $channel->basic_ack(delivery_tag: (string)$message->getDeliveryTag());
            }
        } else {
            echo sprintf(
                format: "Email sent successfully to %s\n",
                values: $data['email']
            );

            $channel->basic_ack(delivery_tag: (string)$message->getDeliveryTag());
        }
    }
}
