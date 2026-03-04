<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$logger = new Logger('consumer');
$logger->pushHandler(new StreamHandler('logs/consumer.log', Logger::INFO));

$config = require_once __DIR__ . '/bootstrap.php';

$consumer = new App\Consumer($config['rabbitmq'], $logger);
$consumer->run();
