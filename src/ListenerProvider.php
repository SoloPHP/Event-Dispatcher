<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;
use Solo\EventDispatcher\Internal\ListenerConfigParser;

final class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<class-string, array<int, list<callable>>> */
    private array $listenersByEvent = [];

    /** @var array<class-string, list<callable>> */
    private array $resolved = [];

    /**
     * @param class-string $eventClass
     */
    public function addListener(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listenersByEvent[$eventClass][$priority][] = $listener;
        $this->resolved = [];
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $eventClass => $config) {
            foreach (ListenerConfigParser::parseSubscriberConfig($eventClass, $config) as $item) {
                $this->addListener(
                    $eventClass,
                    $subscriber->{$item['target']}(...),
                    $item['priority'],
                );
            }
        }
    }

    /**
     * Considers listeners registered for parent classes and implemented interfaces.
     *
     * @param class-string $eventClass
     */
    public function hasListenersFor(string $eventClass): bool
    {
        return $this->resolve($eventClass) !== [];
    }

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        return $this->resolve($event::class);
    }

    /**
     * @param class-string $eventClass
     * @return list<callable>
     */
    private function resolve(string $eventClass): array
    {
        if (isset($this->resolved[$eventClass])) {
            return $this->resolved[$eventClass];
        }

        $parents = class_parents($eventClass) ?: [];
        $interfaces = class_implements($eventClass) ?: [];

        $collected = [];
        foreach ([$eventClass, ...$parents, ...$interfaces] as $class) {
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
            return $this->resolved[$eventClass] = [];
        }

        krsort($collected);
        return $this->resolved[$eventClass] = array_merge(...$collected);
    }
}
