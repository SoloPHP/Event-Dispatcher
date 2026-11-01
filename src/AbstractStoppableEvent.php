<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base for events that a listener can keep from reaching the listeners after it.
 */
abstract class AbstractStoppableEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    #[\Override]
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
