<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;
use Solo\EventDispatcher\Internal\ListenerConfigParser;

final class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<class-string, array<int, list<callable>>> */
    private array $listenersByEvent = [];

    /**
     * @param class-string $eventClass
     */
    public function addListener(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listenersByEvent[$eventClass][$priority][] = $listener;
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $subscriptions = $subscriber::getSubscribedEvents();

        foreach ($subscriptions as $eventClass => $config) {
            $parsed = ListenerConfigParser::parseSubscriberConfig($eventClass, $config);

            foreach ($parsed as $item) {
                $this->addListener(
                    $eventClass,
                    $subscriber->{$item['method']}(...),
                    $item['priority']
                );
            }
        }
    }

    /**
     * Check if there are any listeners registered for a specific event class.
     * Considers listeners for parent classes and interfaces.
     *
     * @param class-string $eventClass
     */
    public function hasListenersFor(string $eventClass): bool
    {
        $classes = [$eventClass];
        $parents = class_parents($eventClass) ?: [];
        $interfaces = class_implements($eventClass) ?: [];
        $classes = array_merge($classes, array_values($parents), array_values($interfaces));

        foreach ($classes as $class) {
            if (isset($this->listenersByEvent[$class]) && $this->listenersByEvent[$class] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventClass = $event::class;

        $classes = [$eventClass];
        $parents = class_parents($event) ?: [];
        $interfaces = class_implements($event) ?: [];
        $classes = array_merge($classes, array_values($parents), array_values($interfaces));

        $collected = [];
        foreach ($classes as $class) {
            if (!isset($this->listenersByEvent[$class])) {
                continue;
            }
            foreach ($this->listenersByEvent[$class] as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    $collected[$priority][] = $listener;
                }
            }
        }

        if ($collected === []) {
            return [];
        }

        krsort($collected);

        return array_merge(...$collected);
    }
}
