<?php

declare(strict_types=1);

namespace Solo\EventDispatcher;

/**
 * Similar to Symfony's EventSubscriberInterface, simplified.
 * Return mapping in one of forms:
 * - EventClass::class => 'onEvent'
 * - EventClass::class => ['onEvent', priority]
 * - EventClass::class => [['onEvent', priority], ['another', priority]]
 */
interface EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0:string,1:int}|list<array{0:string,1:int}>>
     */
    public static function getSubscribedEvents(): array;
}
