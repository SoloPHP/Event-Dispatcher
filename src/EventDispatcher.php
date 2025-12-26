<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\{EventDispatcherInterface, ListenerProviderInterface, StoppableEventInterface};

final class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private readonly ListenerProviderInterface $listenerProvider)
    {
    }

    public function dispatch(object $event): object
    {
        $isStoppable = $event instanceof StoppableEventInterface;

        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            if ($isStoppable && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }
}
