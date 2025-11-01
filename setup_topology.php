<?php

declare(strict_types=1);

$config = require_once __DIR__ . '/bootstrap.php';

$topology = new App\Topology($config['rabbitmq']);
$topology->setup();
