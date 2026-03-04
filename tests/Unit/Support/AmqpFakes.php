<?php

declare(strict_types=1);

namespace Tests\Unit;

class TestChannel
{
    public function exchange_declare(...$args) {}
    public function basic_publish(...$args) {}
    public function close(...$args) {}
    public function basic_ack(...$args) {}
    public function basic_nack(...$args) {}
}
