<?php

declare(strict_types=1);

$config = require_once __DIR__ . '/bootstrap.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

// Configuración de la conexión
$connection = new AMQPStreamConnection(
    host: $config['rabbitmq']['host'],
    port: $config['rabbitmq']['port'],
    user: $config['rabbitmq']['user'],
    password: $config['rabbitmq']['password']
);

$channel = $connection->channel();

// Declarar exchanges
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

// Configurar la cola principal con DLX
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

// Configurar la cola de retraso
$delayArgs = new AMQPTable([
    'x-dead-letter-exchange' => 'main_registro_exchange',
    'x-dead-letter-routing-key' => 'registro.email',
    'x-message-ttl' => 30000,   // 30 segundos de retraso
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

// Declarar cola muerta final (para mensajes que fallan definitivamente)
$channel->queue_declare(
    queue: 'dead_letter_queue',
    passive: false,
    durable: true,
    exclusive: false,
    auto_delete: false,
    nowait: false
);

// Declarar exchange para mensajes muertos finales
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

echo "Topología configurada exitosamente\n";

// Cerrar conexiones
$channel->close();
$connection->close();
