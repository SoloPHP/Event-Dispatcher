<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

/**
 * Allows a single class to register multiple event listeners declaratively via {@see getSubscribedEvents()}.
 */
interface EventSubscriberInterface
{
    /**
     * Returns a map of event class names to handler descriptors.
     *
     * Each descriptor may be:
     *  - The method name to call (priority defaults to 0)
     *  - An array of [method, priority]
     *  - A list of [method, priority] entries
     *
     * @return array<class-string, string|array{0: string, 1?: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array;
}
