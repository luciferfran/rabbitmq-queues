<?php

declare(strict_types=1);

$config = require_once __DIR__ . '/bootstrap.php';

$producer = new App\Producer($config['rabbitmq']);

// Example user data
$userData = [
    'user_id' => rand(1, 100),
    'email' => 'nuevo.usuario@ejemplo.com',
    'name' => 'John Doe',
    'retries' => 0,
];

$producer->publish($userData);
