[![CI/CD Status](https://github.com/luciferfran/rabbitmq-queues/actions/workflows/ci.yml/badge.svg)](https://github.com/luciferfran/rabbitmq-queues/actions)

# RabbitMQ Queues Project

A simple project to demonstrate the use of RabbitMQ with PHP.

## Setup

1. Clone the repository.
2. Copy the `.env.example` file to `.env` and fill in the values.
3. Run `composer install` to install the dependencies.
4. Run `docker-compose up -d` to start the RabbitMQ container.
5. Run `php setup_topology.php` to create the queues and exchanges.

## Scripts

- `composer analyze` - Run static analysis with PHPStan.
- `composer analyze:strict` - Run PHPStan with maximum strictness.
- `composer format` - Format the code with PHP-CS-Fixer.
- `composer format:dry-run` - Check code formatting without modifying files.
- `composer commit` - Create a commit with commitlint validation.

## Usage

### Setup Topology

```bash
php setup_topology.php
```

### Run Producer

```bash
php run_producer.php [user_id] [email] [name]
```

Examples:
```bash
php run_producer.php
php run_producer.php 1 john@example.com "John Doe"
```

### Run Consumer

```bash
php run_consumer.php
```

## Architecture

- **Producer**: Publishes messages to the main exchange.
- **Consumer**: Processes messages with retry logic and dead letter queue.
- **Topology**: Sets up exchanges, queues, and bindings.

### Message Flow

1. Producer sends message to `main_registro_exchange`
2. Message goes to `email_queue`
3. Consumer processes the message:
   - Success: Message is acknowledged
   - Failure: Message is retried up to 3 times via `retry_queue`
   - After max retries: Message goes to `dead_letter_queue`

## Requirements

- PHP 8.4+
- RabbitMQ
- Composer
