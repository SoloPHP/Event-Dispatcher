<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\{EventDispatcherInterface, ListenerProviderInterface, StoppableEventInterface};

class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private readonly ListenerProviderInterface $listenerProvider)
    {
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);

            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }

        return $event;
    }
}
