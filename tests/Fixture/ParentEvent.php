<?php

declare(strict_types=1);

namespace Tests\Fixture;

class ParentEvent
{
    public function __construct(public string $value = '')
    {
    }
}
