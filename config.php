<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$requiredEnvVars = ['RABBITMQ_PORT', 'RABBITMQ_DEFAULT_USER', 'RABBITMQ_DEFAULT_PASS'];

foreach ($requiredEnvVars as $var) {
    if (empty($_ENV[$var])) {
        throw new RuntimeException("Missing required environment variable: {$var}");
    }
}

return [
    'rabbitmq' => [
        'host' => $_ENV['RABBITMQ_HOST'] ?? 'localhost',
        'port' => (int)$_ENV['RABBITMQ_PORT'],
        'user' => $_ENV['RABBITMQ_DEFAULT_USER'],
        'password' => $_ENV['RABBITMQ_DEFAULT_PASS'],
    ],
];
