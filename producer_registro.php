<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$logger = new Logger('producer');
$logger->pushHandler(new StreamHandler('logs/producer.log', Logger::INFO));

$config = require_once __DIR__ . '/bootstrap.php';

$producer = new App\Producer($config['rabbitmq'], $logger);

$userData = [
    'user_id' => 2,
    'email' => 'nuevo.usuario@ejemplo.com',
    'name' => 'John Doe',
    'retries' => 0,
];

$producer->publish($userData);

echo 'Mensaje enviado para el usuario ID: ' . $userData['user_id'] . "\n";
