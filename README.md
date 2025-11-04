[![CI/CD Status](https://github.com/luciferfran/rabbitmq-queues/actions/workflows/ci.yml/badge.svg)](https://github.com/luciferfran/rabbitmq-queues/actions)

# RabbitMQ Queues Project

A simple project to demonstrate the use of RabbitMQ with PHP.

## Setup

1.  Clone the repository.
2.  Copy the `.env.example` file to `.env` and fill in the values.
3.  Run `composer install` to install the dependencies.
4.  Run `docker-compose up -d` to start the RabbitMQ container.

## Scripts

-   `composer analyze`: Run static analysis with PHPStan.
-   `composer format`: Format the code with PHP-CS-Fixer.