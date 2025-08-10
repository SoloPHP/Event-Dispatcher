<?php

declare(strict_types=1);

namespace Tests\Fixture;

use Psr\EventDispatcher\StoppableEventInterface;

final class StoppableTestEvent implements StoppableEventInterface
{
    private bool $stopped = false;

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
