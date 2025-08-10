<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class AbstractStoppableEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
