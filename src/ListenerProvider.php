<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;
use Solo\EventDispatcher\Exception\InvalidConfigurationException;

final class ListenerProvider implements ListenerProviderInterface
{
    /** @var list<array{class-string, callable, int}> event type, listener and priority, in registration order */
    private array $listeners = [];

    /** @var array<string, list<callable>> */
    private array $resolved = [];

    /**
     * @template T of object
     * @param class-string<T> $eventClass a class or interface; the listener also receives its subtypes
     * @param callable(T): mixed $listener
     * @param int $priority higher runs first; equal priorities run in registration order
     * @throws InvalidConfigurationException if the event type is not an existing class or interface
     */
    public function addListener(string $eventClass, callable $listener, int $priority = 0): void
    {
        self::assertEventType($eventClass);

        $this->register([[$eventClass, $listener, $priority]]);
    }

    /**
     * Registers every handler the subscriber declares, or none if any declaration is invalid.
     *
     * @throws InvalidConfigurationException
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $listeners = [];
        foreach ($subscriber::getSubscribedEvents() as $eventClass => $handlers) {
            // The integer keys of a list returned by mistake are rejected as event types too.
            $eventClass = (string) $eventClass;
            self::assertEventType($eventClass);

            foreach (self::parseHandlers($subscriber, $eventClass, $handlers) as [$method, $priority]) {
                $listener = [$subscriber, $method];
                if (!is_callable($listener)) {
                    throw new InvalidConfigurationException(sprintf(
                        'Subscriber method %s::%s() for event "%s" must exist and be public.',
                        get_debug_type($subscriber),
                        $method,
                        $eventClass,
                    ));
                }

                $listeners[] = [$eventClass, $listener, $priority];
            }
        }

        $this->register($listeners);
    }

    /**
     * Whether an event of this type has listeners, counting those registered for its parents and interfaces.
     *
     * @param class-string $eventClass
     * @throws InvalidConfigurationException if the event type is not an existing class or interface
     */
    public function hasListenersFor(string $eventClass): bool
    {
        self::assertEventType($eventClass);

        return $this->resolve($eventClass) !== [];
    }

    /**
     * Listeners registered for the event's class, its parents and its interfaces,
     * by descending priority, then in registration order.
     *
     * @return list<callable>
     */
    #[\Override]
    public function getListenersForEvent(object $event): iterable
    {
        return $this->resolve($event::class);
    }

    /** @param list<array{class-string, callable, int}> $listeners */
    private function register(array $listeners): void
    {
        array_push($this->listeners, ...$listeners);
        $this->resolved = [];
    }

    /** @return list<callable> */
    private function resolve(string $eventClass): array
    {
        if (isset($this->resolved[$eventClass])) {
            return $this->resolved[$eventClass];
        }

        $matching = array_filter(
            $this->listeners,
            static fn (array $entry): bool => is_a($eventClass, $entry[0], true),
        );
        // usort() is stable, so listeners with equal priority keep their registration order.
        usort($matching, static fn (array $a, array $b): int => $b[2] <=> $a[2]);

        return $this->resolved[$eventClass] = array_column($matching, 1);
    }

    /** @throws InvalidConfigurationException */
    private static function assertEventType(string $eventClass): void
    {
        if (!class_exists($eventClass) && !interface_exists($eventClass)) {
            throw new InvalidConfigurationException(sprintf(
                'Event type "%s" must be an existing class or interface.',
                $eventClass,
            ));
        }
    }

    /**
     * @return list<array{string, int}> method and priority of each handler
     * @throws InvalidConfigurationException
     */
    private static function parseHandlers(
        EventSubscriberInterface $subscriber,
        string $eventClass,
        mixed $handlers,
    ): array {
        // 'method' and ['method', priority] are shorthands for a list with one handler.
        if (is_string($handlers)) {
            $handlers = [[$handlers]];
        } elseif (is_array($handlers) && is_string($handlers[0] ?? null)) {
            $handlers = [$handlers];
        }

        if (!is_array($handlers) || !array_is_list($handlers)) {
            throw self::invalidSubscription($subscriber, $eventClass);
        }

        $parsed = [];
        foreach ($handlers as $handler) {
            if (!is_array($handler) || !array_is_list($handler) || count($handler) < 1 || count($handler) > 2) {
                throw self::invalidSubscription($subscriber, $eventClass);
            }

            $method = $handler[0];
            $priority = count($handler) === 2 ? $handler[1] : 0;
            if (!is_string($method) || !is_int($priority)) {
                throw self::invalidSubscription($subscriber, $eventClass);
            }

            $parsed[] = [$method, $priority];
        }

        return $parsed;
    }

    private static function invalidSubscription(
        EventSubscriberInterface $subscriber,
        string $eventClass,
    ): InvalidConfigurationException {
        return new InvalidConfigurationException(sprintf(
            'Invalid subscription to "%s" in %s::getSubscribedEvents(): expected "method", '
            . '["method", priority] or a list of ["method", priority] pairs.',
            $eventClass,
            get_debug_type($subscriber),
        ));
    }
}
