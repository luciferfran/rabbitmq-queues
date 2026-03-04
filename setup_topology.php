<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$logger = new Logger('topology');
$logger->pushHandler(new StreamHandler('logs/topology.log', Logger::INFO));

$config = require_once __DIR__ . '/bootstrap.php';

$topology = new App\Topology($config['rabbitmq'], $logger);
$topology->setup();
