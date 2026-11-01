<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;

final readonly class EventDispatcher implements EventDispatcherInterface
{
    /** @param LoggerInterface|null $logger records each listener failure, which then propagates all the same */
    public function __construct(
        private ListenerProviderInterface $listenerProvider,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Calls the event's listeners in order and returns the same event.
     * A listener failure stops the dispatch and reaches the caller unchanged.
     *
     * @template T of object
     * @param T $event
     * @return T
     */
    #[\Override]
    public function dispatch(object $event): object
    {
        if (self::isStopped($event)) {
            return $event;
        }

        /** @var callable $listener PSR-14 promises callables in a docblock form that tools cannot read */
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            try {
                $listener($event);
            } catch (\Throwable $failure) {
                $this->logFailure($failure, $event, $listener);
                throw $failure;
            }

            // Checked after each call, so a lazy provider is never asked for a listener that will not run.
            if (self::isStopped($event)) {
                break;
            }
        }

        return $event;
    }

    /** @phpstan-impure a listener may stop the event between two calls */
    private static function isStopped(object $event): bool
    {
        return $event instanceof StoppableEventInterface && $event->isPropagationStopped();
    }

    /** @param mixed $listener a provider may yield something that is not callable at all */
    private function logFailure(\Throwable $failure, object $event, mixed $listener): void
    {
        try {
            $this->logger?->error('Event listener failed: {message}', [
                'exception' => $failure,
                'message' => $failure->getMessage(),
                'event' => get_debug_type($event),
                'listener' => self::describe($listener),
            ]);
        } catch (\Throwable) {
            // The listener's failure is the one to propagate: a failing logger must not replace or alter it.
        }
    }

    private static function describe(mixed $listener): string
    {
        if (!is_callable($listener)) {
            return is_string($listener) ? $listener : get_debug_type($listener);
        }

        $function = new \ReflectionFunction(\Closure::fromCallable($listener));
        if ($function->isAnonymous()) {
            return sprintf('Closure at %s:%d', $function->getFileName(), $function->getStartLine());
        }

        // A method keeps its class: the object's for an instance method, the called class for a static one.
        $object = $function->getClosureThis();
        $class = $object === null ? $function->getClosureCalledClass()?->getName() : get_debug_type($object);

        return ($class === null ? '' : $class . '::') . $function->getName();
    }
}
