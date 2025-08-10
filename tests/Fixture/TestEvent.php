<?php

declare(strict_types=1);

namespace Tests\Fixture;

final class TestEvent
{
    public function __construct(public string $name)
    {
    }
}
