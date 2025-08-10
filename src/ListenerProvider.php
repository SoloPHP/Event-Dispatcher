<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;

use function array_merge;
use function class_implements;
use function class_parents;

class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, array<int, list<callable>>> */
    private array $listenersByEvent = [];

    public function addListener(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listenersByEvent[$eventClass][$priority][] = $listener;
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $subscriptions = $subscriber::getSubscribedEvents();

        foreach ($subscriptions as $eventClass => $config) {
            if (is_string($config)) {
                $this->addListener($eventClass, [$subscriber, $config]);
                continue;
            }

            // Single [method, priority]
            if (
                is_array($config)
                && isset($config[0])
                && is_string($config[0])
                && (isset($config[1]) ? is_int($config[1]) : true)
            ) {
                $method = $config[0];
                $priority = (int)($config[1] ?? 0);
                $this->addListener($eventClass, [$subscriber, $method], $priority);
                continue;
            }

            // Multiple [[method, priority], ...]
            if (is_array($config)) {
                foreach ($config as $entry) {
                    if (!is_array($entry) || !isset($entry[0]) || !is_string($entry[0])) {
                        continue;
                    }
                    $method = $entry[0];
                    $priority = (int)($entry[1] ?? 0);
                    $this->addListener($eventClass, [$subscriber, $method], $priority);
                }
            }
        }
    }

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

        // Sort priorities descending
        krsort($collected);

        $ordered = [];
        foreach ($collected as $listeners) {
            foreach ($listeners as $listener) {
                $ordered[] = $listener;
            }
        }

        return $ordered;
    }
}
