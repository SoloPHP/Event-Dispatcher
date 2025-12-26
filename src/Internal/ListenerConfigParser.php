<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Internal;

use Solo\EventDispatcher\Exception\InvalidConfigurationException;

/**
 * Parses listener configuration into normalized format.
 *
 * Supported formats:
 * - 'methodName' (for subscribers)
 * - callable (for factory)
 * - ['methodName', priority] or [callable, priority]
 * - [['methodName', priority], ['methodName2', priority], ...]
 *
 * @internal
 */
final class ListenerConfigParser
{
    /**
     * Parse subscriber configuration (methods).
     *
     * @return list<array{method: string, priority: int}>
     * @throws InvalidConfigurationException
     */
    public static function parseSubscriberConfig(string $eventClass, mixed $config): array
    {
        if (is_string($config)) {
            return [['method' => $config, 'priority' => 0]];
        }

        if (!is_array($config)) {
            throw new InvalidConfigurationException(
                sprintf('Invalid subscriber configuration for event "%s": expected string or array.', $eventClass)
            );
        }

        if (self::isSingleMethodConfig($config)) {
            return [[
                'method' => $config[0],
                'priority' => (int) ($config[1] ?? 0),
            ]];
        }

        return self::parseMultipleMethodConfigs($eventClass, $config);
    }

    /**
     * Parse listener configuration (callables).
     *
     * @return list<array{callable: callable, priority: int}>
     * @throws InvalidConfigurationException
     */
    public static function parseListenerConfig(string $eventClass, mixed $config): array
    {
        if (is_callable($config)) {
            return [['callable' => $config, 'priority' => 0]];
        }

        if (!is_array($config)) {
            throw new InvalidConfigurationException(
                sprintf('Invalid listener configuration for event "%s": expected callable or array.', $eventClass)
            );
        }

        if (self::isSingleCallableConfig($config)) {
            return [[
                'callable' => $config[0],
                'priority' => (int) ($config[1] ?? 0),
            ]];
        }

        return self::parseMultipleCallableConfigs($eventClass, $config);
    }

    /**
     * @param array<mixed> $config
     */
    private static function isSingleMethodConfig(array $config): bool
    {
        return isset($config[0])
            && is_string($config[0])
            && (!isset($config[1]) || is_int($config[1]));
    }

    /**
     * @param array<mixed> $config
     */
    private static function isSingleCallableConfig(array $config): bool
    {
        return isset($config[0])
            && is_callable($config[0])
            && (!isset($config[1]) || is_int($config[1]));
    }

    /**
     * @param array<mixed> $config
     * @return list<array{method: string, priority: int}>
     * @throws InvalidConfigurationException
     */
    private static function parseMultipleMethodConfigs(string $eventClass, array $config): array
    {
        $result = [];

        foreach ($config as $index => $entry) {
            if (!is_array($entry)) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid subscriber configuration for event "%s" at index %d: expected [method, priority?].',
                    $eventClass,
                    $index
                ));
            }

            if (!isset($entry[0]) || !is_string($entry[0])) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Invalid subscriber configuration for event "%s" at index %d: method name must be a string.',
                        $eventClass,
                        $index
                    )
                );
            }

            if (isset($entry[1]) && !is_int($entry[1])) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Invalid subscriber configuration for event "%s" at index %d: priority must be an integer.',
                        $eventClass,
                        $index
                    )
                );
            }

            $result[] = [
                'method' => $entry[0],
                'priority' => (int) ($entry[1] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param array<mixed> $config
     * @return list<array{callable: callable, priority: int}>
     * @throws InvalidConfigurationException
     */
    private static function parseMultipleCallableConfigs(string $eventClass, array $config): array
    {
        $result = [];

        foreach ($config as $index => $entry) {
            if (!is_array($entry)) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid listener configuration for event "%s" at index %d: expected [callable, priority?].',
                    $eventClass,
                    $index
                ));
            }

            if (!isset($entry[0]) || !is_callable($entry[0])) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Invalid listener configuration for event "%s" at index %d: first element must be callable.',
                        $eventClass,
                        $index
                    )
                );
            }

            if (isset($entry[1]) && !is_int($entry[1])) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Invalid listener configuration for event "%s" at index %d: priority must be an integer.',
                        $eventClass,
                        $index
                    )
                );
            }

            $result[] = [
                'callable' => $entry[0],
                'priority' => (int) ($entry[1] ?? 0),
            ];
        }

        return $result;
    }
}
