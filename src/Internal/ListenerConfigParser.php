<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Internal;

use Solo\EventDispatcher\Exception\InvalidConfigurationException;

/**
 * Parses listener configuration into a normalized list of {target, priority} entries.
 *
 * Supported input forms (leaf is a method-name string for subscribers, any callable for listeners):
 * - leaf
 * - [leaf, priority]
 * - [[leaf, priority], [leaf, priority], ...]
 *
 * @internal
 */
final class ListenerConfigParser
{
    /**
     * @return list<array{target: string, priority: int}>
     * @throws InvalidConfigurationException
     */
    public static function parseSubscriberConfig(string $eventClass, mixed $config): array
    {
        /** @var list<array{target: string, priority: int}> */
        return self::parse(
            eventClass: $eventClass,
            config: $config,
            isLeaf: 'is_string',
            kind: 'subscriber',
            topExpected: 'string or array',
            entryExpected: '[method, priority?]',
            leafError: 'method name must be a string',
        );
    }

    /**
     * @return list<array{target: callable, priority: int}>
     * @throws InvalidConfigurationException
     */
    public static function parseListenerConfig(string $eventClass, mixed $config): array
    {
        /** @var list<array{target: callable, priority: int}> */
        return self::parse(
            eventClass: $eventClass,
            config: $config,
            isLeaf: 'is_callable',
            kind: 'listener',
            topExpected: 'callable or array',
            entryExpected: '[callable, priority?]',
            leafError: 'first element must be callable',
        );
    }

    /**
     * @param callable(mixed): bool $isLeaf
     * @return list<array{target: mixed, priority: int}>
     * @throws InvalidConfigurationException
     */
    private static function parse(
        string $eventClass,
        mixed $config,
        callable $isLeaf,
        string $kind,
        string $topExpected,
        string $entryExpected,
        string $leafError,
    ): array {
        if ($isLeaf($config)) {
            return [['target' => $config, 'priority' => 0]];
        }

        if (!is_array($config)) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid %s configuration for event "%s": expected %s.',
                $kind,
                $eventClass,
                $topExpected,
            ));
        }

        if (isset($config[0]) && $isLeaf($config[0]) && (!isset($config[1]) || is_int($config[1]))) {
            return [['target' => $config[0], 'priority' => $config[1] ?? 0]];
        }

        $result = [];
        foreach ($config as $index => $entry) {
            if (!is_array($entry)) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid %s configuration for event "%s" at index %d: expected %s.',
                    $kind,
                    $eventClass,
                    $index,
                    $entryExpected,
                ));
            }

            if (!isset($entry[0]) || !$isLeaf($entry[0])) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid %s configuration for event "%s" at index %d: %s.',
                    $kind,
                    $eventClass,
                    $index,
                    $leafError,
                ));
            }

            if (isset($entry[1]) && !is_int($entry[1])) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid %s configuration for event "%s" at index %d: priority must be an integer.',
                    $kind,
                    $eventClass,
                    $index,
                ));
            }

            $result[] = ['target' => $entry[0], 'priority' => $entry[1] ?? 0];
        }

        return $result;
    }
}
