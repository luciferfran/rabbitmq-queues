<?php

declare(strict_types=1);

$config = require_once __DIR__ . '/bootstrap.php';

$consumer = new App\Consumer(config: $config['rabbitmq']);
$consumer->run();
