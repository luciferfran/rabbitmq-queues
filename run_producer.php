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
    'user_id' => (int)($argv[1] ?? rand(1, 100)),
    'email' => $argv[2] ?? 'usuario@ejemplo.com',
    'name' => $argv[3] ?? 'Usuario Demo',
    'retries' => 0,
];

$producer->publish($userData);
