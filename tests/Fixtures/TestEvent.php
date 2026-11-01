<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Tests\Fixtures;

final class TestEvent
{
    public function __construct(public string $name = '')
    {
    }
}
