<?php

declare(strict_types=1);

$config = require_once __DIR__ . '/bootstrap.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Configuración de la conexión
$connection = new AMQPStreamConnection(
    host: $config['rabbitmq']['host'],
    port: $config['rabbitmq']['port'],
    user: $config['rabbitmq']['user'],
    password: $config['rabbitmq']['password']
);

$channel = $connection->channel();

// Declarar el exchange
$channel->exchange_declare(
    exchange: 'main_registro_exchange',
    type: 'topic',
    passive: false,
    durable: true,
    auto_delete: false
);

// Datos de ejemplo del usuario
$userData = [
    'user_id' => 2,  // ID par que provocará un fallo
    'email' => 'nuevo.usuario@ejemplo.com',
    'name' => 'John Doe',
    'retries' => 0, // Contador de reintentos
];

// Crear el mensaje
$message = new AMQPMessage(
    body: json_encode($userData),
    properties: [
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        'content_type' => 'application/json',
    ]
);

// Publicar el mensaje
$channel->basic_publish(
    msg: $message,
    exchange: 'main_registro_exchange',
    routing_key: 'registro.email'
);

echo 'Mensaje enviado para el usuario ID: ' . $userData['user_id'] . "\n";

// Cerrar la conexión
$channel->close();
$connection->close();
