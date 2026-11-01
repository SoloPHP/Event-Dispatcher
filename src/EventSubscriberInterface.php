<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

/**
 * Declares the methods that handle each event, so that one object registers several listeners at once.
 */
interface EventSubscriberInterface
{
    /**
     * Maps event classes or interfaces to the public methods that handle them:
     *
     *  - 'method'                                priority 0
     *  - ['method', priority]
     *  - [['method', priority], ['another']]     several methods
     *
     * @return array<class-string, string|array{0: string, 1?: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array;
}
