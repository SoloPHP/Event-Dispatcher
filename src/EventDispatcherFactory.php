<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

use Psr\Log\LoggerInterface;
use Solo\EventDispatcher\Exception\InvalidConfigurationException;
use Solo\EventDispatcher\Internal\ListenerConfigParser;

/**
 * Factory for creating EventDispatcher instances.
 *
 * @phpstan-type ListenerEntry array{0: callable, 1?: int}
 * @phpstan-type ListenerConfig callable|ListenerEntry|list<ListenerEntry>
 * @phpstan-type SubscriberFactory (callable(): EventSubscriberInterface)
 * @phpstan-type SubscriberConfig EventSubscriberInterface|class-string<EventSubscriberInterface>|SubscriberFactory
 */
final class EventDispatcherFactory
{
    /**
     * @param array<class-string, ListenerConfig> $listeners
     * @param list<SubscriberConfig> $subscribers
     *
     * @throws InvalidConfigurationException
     */
    public static function createProvider(
        array $listeners = [],
        array $subscribers = []
    ): ListenerProvider {
        $provider = new ListenerProvider();

        foreach ($listeners as $eventClass => $config) {
            $parsed = ListenerConfigParser::parseListenerConfig($eventClass, $config);

            foreach ($parsed as $item) {
                $provider->addListener($eventClass, $item['callable'], $item['priority']);
            }
        }

        foreach ($subscribers as $index => $subscriber) {
            $instance = self::resolveSubscriber($subscriber, $index);
            $provider->addSubscriber($instance);
        }

        return $provider;
    }

    /**
     * @param array<class-string, ListenerConfig> $listeners
     * @param list<SubscriberConfig> $subscribers
     *
     * @throws InvalidConfigurationException
     */
    public static function create(
        array $listeners = [],
        array $subscribers = [],
        ?LoggerInterface $logger = null
    ): EventDispatcher {
        $provider = self::createProvider($listeners, $subscribers);

        return new EventDispatcher($provider, $logger);
    }

    /**
     * @throws InvalidConfigurationException
     */
    private static function resolveSubscriber(mixed $subscriber, int $index): EventSubscriberInterface
    {
        if ($subscriber instanceof EventSubscriberInterface) {
            return $subscriber;
        }

        if (is_callable($subscriber)) {
            $instance = $subscriber();

            if (!$instance instanceof EventSubscriberInterface) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Subscriber factory at index %d must return an instance of %s.',
                        $index,
                        EventSubscriberInterface::class
                    )
                );
            }

            return $instance;
        }

        if (is_string($subscriber)) {
            if (!class_exists($subscriber)) {
                throw new InvalidConfigurationException(
                    sprintf('Subscriber class "%s" at index %d does not exist.', $subscriber, $index)
                );
            }

            if (!is_subclass_of($subscriber, EventSubscriberInterface::class)) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Subscriber class "%s" at index %d must implement %s.',
                        $subscriber,
                        $index,
                        EventSubscriberInterface::class
                    )
                );
            }

            return new $subscriber();
        }

        throw new InvalidConfigurationException(
            sprintf(
                'Invalid subscriber at index %d: expected %s instance, class-string, or callable.',
                $index,
                EventSubscriberInterface::class
            )
        );
    }
}
