<?php

declare(strict_types=1);

namespace App;

use Exception;
use InvalidArgumentException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Consumer
{
    private const MAX_RETRIES = 3;

    private ?AMQPStreamConnection $connection = null;
    private LoggerInterface $logger;
    private array $config;
    private array $queueConfig;

    public function __construct(array $config, LoggerInterface $logger = null, array $queueConfig = [])
    {
        $this->logger = $logger ?? new NullLogger();
        $this->config = $this->validateConfig($config);
        $this->queueConfig = $this->validateQueueConfig($queueConfig);
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
            'host' => filter_var($config['host'], FILTER_VALIDATE_IP) ? $config['host'] : $config['host'],
            'port' => filter_var($config['port'], FILTER_VALIDATE_INT) ? $config['port'] : (int)$config['port'],
            'user' => $config['user'],
            'password' => $config['password'],
        ];
    }

    private function validateQueueConfig(array $config): array
    {
        return [
            'queue' => $config['queue'] ?? 'email_queue',
            'retry_exchange' => $config['retry_exchange'] ?? 'dlx_exchange',
            'retry_routing_key' => $config['retry_routing_key'] ?? 'retry.email',
            'dead_letter_exchange' => $config['dead_letter_exchange'] ?? 'final_dlx_exchange',
            'dead_letter_routing_key' => $config['dead_letter_routing_key'] ?? 'dead.email',
        ];
    }

    public function run(): void
    {
        $channel = $this->getConnection()->channel();

        $channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

        $channel->basic_consume(
            queue: $this->queueConfig['queue'],
            consumer_tag: '',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: [$this, 'processMessage']
        );

        $this->logger->info('Waiting for messages. To exit press CTRL+C');
        echo "Waiting for messages. To exit press CTRL+C\n";

        while (count($channel->callbacks)) {
            try {
                $channel->wait();
            } catch (Exception $e) {
                $this->logger->error('Error waiting for messages: ' . $e->getMessage());
                break;
            }
        }

        $channel->close();
        $this->getConnection()->close();
    }

    public function processMessage(AMQPMessage $message): void
    {
        $channel = $message->getChannel();

        try {
            $data = json_decode($message->body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON in message: ' . json_last_error_msg());
            }

            $this->validateMessageData($data);

            $this->logger->info(sprintf(
                '[%s] Processing message for user ID: %d (Attempt %d of %d)',
                date('H:i:s'),
                $data['user_id'],
                ($data['retries'] ?? 0) + 1,
                self::MAX_RETRIES
            ));

            echo sprintf(
                "[%s] Processing message for user ID: %d (Attempt %d of %d)\n",
                date('H:i:s'),
                $data['user_id'],
                ($data['retries'] ?? 0) + 1,
                self::MAX_RETRIES
            );

            $shouldFail = ($data['user_id'] % 2 == 0);

            if ($shouldFail) {
                $this->handleFailure($channel, $message, $data);
            } else {
                $this->handleSuccess($channel, $message, $data);
            }
        } catch (Exception $e) {
            $this->logger->error('Error processing message: ' . $e->getMessage());
            echo 'Error processing message: ' . $e->getMessage() . "\n";
            $channel->basic_nack(delivery_tag: (string)$message->getDeliveryTag(), requeue: false);
        }
    }

    private function validateMessageData(array $data): void
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

        if (!isset($data['retries']) || !filter_var($data['retries'], FILTER_VALIDATE_INT)) {
            $data['retries'] = 0;
        }
    }

    private function handleFailure($channel, AMQPMessage $message, array $data): void
    {
        echo "Error processing message...\n";
        $this->logger->warning('Message processing failed', ['user_id' => $data['user_id'], 'retries' => $data['retries']]);

        if ($data['retries'] < self::MAX_RETRIES - 1) {
            $data['retries']++;

            $retryMessage = new AMQPMessage(
                body: json_encode($data),
                properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            echo sprintf(
                "Retrying later (attempt %d of %d)...\n",
                $data['retries'] + 1,
                self::MAX_RETRIES
            );

            $this->logger->info('Retrying message', [
                'user_id' => $data['user_id'],
                'attempt' => $data['retries'] + 1,
                'max_attempts' => self::MAX_RETRIES,
            ]);

            $channel->basic_publish(
                msg: $retryMessage,
                exchange: $this->queueConfig['retry_exchange'],
                routing_key: $this->queueConfig['retry_routing_key']
            );

            $channel->basic_ack(delivery_tag: (string)$message->getDeliveryTag());
        } else {
            echo "Unrecoverable error. Moving to dead letter queue.\n";
            $this->logger->error('Maximum retries reached, moving to dead letter queue', ['user_id' => $data['user_id']]);

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
                exchange: $this->queueConfig['dead_letter_exchange'],
                routing_key: $this->queueConfig['dead_letter_routing_key']
            );

            $channel->basic_ack(delivery_tag: (string)$message->getDeliveryTag());
        }
    }

    private function handleSuccess($channel, AMQPMessage $message, array $data): void
    {
        $this->logger->info('Email sent successfully', ['user_id' => $data['user_id'], 'email' => $data['email']]);

        echo sprintf("Email sent successfully to %s\n", $data['email']);

        $channel->basic_ack(delivery_tag: (string)$message->getDeliveryTag());
    }

    public function __destruct()
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
