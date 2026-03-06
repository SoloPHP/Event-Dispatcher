<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\{EventDispatcherInterface, ListenerProviderInterface, StoppableEventInterface};
use Psr\Log\LoggerInterface;

final readonly class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private ListenerProviderInterface $listenerProvider,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function dispatch(object $event): object
    {
        $isStoppable = $event instanceof StoppableEventInterface;

        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            if ($isStoppable && $event->isPropagationStopped()) {
                break;
            }

            if ($this->logger !== null) {
                try {
                    $listener($event);
                } catch (\Throwable $e) {
                    $this->logger->error('Event listener failed: {exception} {message}', [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                        'event' => $event::class,
                        'listener' => self::describeListener($listener),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            } else {
                $listener($event);
            }
        }

        return $event;
    }

    private static function describeListener(callable $listener): string
    {
        return match (true) {
            is_array($listener) => (is_object($listener[0]) ? $listener[0]::class : $listener[0]) . '::' . $listener[1],
            $listener instanceof \Closure => 'Closure',
            is_object($listener) => $listener::class . '::__invoke',
            is_string($listener) => $listener,
            default => 'unknown',
        };
    }
}
