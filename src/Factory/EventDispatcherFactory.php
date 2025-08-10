<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Factory;

use Solo\EventDispatcher\{EventDispatcher, EventSubscriberInterface, ListenerProvider};

final class EventDispatcherFactory
{
    /**
     * @param array<string, callable|array{0:callable,1?:int}|list<array{0:callable,1?:int}>> $listeners
     * @param list<EventSubscriberInterface|string|callable():EventSubscriberInterface> $subscribers
     */
    public static function createProvider(array $listeners = [], array $subscribers = []): ListenerProvider
    {
        $provider = new ListenerProvider();

        foreach ($listeners as $eventClass => $config) {
            if (is_callable($config)) {
                $provider->addListener($eventClass, $config);
                continue;
            }

            if (is_array($config)) {
                // Single [callable, priority]
                if (isset($config[0]) && is_callable($config[0]) && (isset($config[1]) ? is_int($config[1]) : true)) {
                    $provider->addListener($eventClass, $config[0], (int)($config[1] ?? 0));
                    continue;
                }

                // Multiple [[callable, priority], ...]
                foreach ($config as $entry) {
                    if (!is_array($entry) || !isset($entry[0]) || !is_callable($entry[0])) {
                        continue;
                    }
                    $provider->addListener($eventClass, $entry[0], (int)($entry[1] ?? 0));
                }
            }
        }

        foreach ($subscribers as $subscriber) {
            if (is_callable($subscriber)) {
                $instance = $subscriber();
                if ($instance instanceof EventSubscriberInterface) {
                    $provider->addSubscriber($instance);
                }
                continue;
            }

            if (is_string($subscriber)) {
                /** @var EventSubscriberInterface $instance */
                $instance = new $subscriber();
                $provider->addSubscriber($instance);
                continue;
            }

            if ($subscriber instanceof EventSubscriberInterface) {
                $provider->addSubscriber($subscriber);
            }
        }

        return $provider;
    }

    /**
     * @param array<string, callable|array{0:callable,1?:int}|list<array{0:callable,1?:int}>> $listeners
     * @param list<EventSubscriberInterface|string|callable():EventSubscriberInterface> $subscribers
     */
    public static function create(array $listeners = [], array $subscribers = []): EventDispatcher
    {
        $provider = self::createProvider($listeners, $subscribers);
        return new EventDispatcher($provider);
    }
}
