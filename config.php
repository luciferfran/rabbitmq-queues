<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

return [
    'rabbitmq' => [
        'host' => 'localhost',
        'port' => $_ENV['RABBITMQ_PORT'],
        'user' => $_ENV['RABBITMQ_DEFAULT_USER'],
        'password' => $_ENV['RABBITMQ_DEFAULT_PASS'],
    ],
];
