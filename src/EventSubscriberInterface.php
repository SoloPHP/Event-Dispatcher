<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

/**
 * Interface for event subscribers.
 *
 * Subscribers can define multiple event listeners in a single class.
 *
 * Return mapping in one of forms:
 * - EventClass::class => 'methodName'
 * - EventClass::class => ['methodName', priority]
 * - EventClass::class => [['methodName', priority], ['anotherMethod', priority]]
 */
interface EventSubscriberInterface
{
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event class names and the value can be:
     *
     *  - The method name to call (priority defaults to 0)
     *  - An array composed of the method name to call and the priority
     *  - An array of arrays composed of the method names to call and respective priorities
     *
     * @return array<class-string, string|array{0: string, 1?: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array;
}
