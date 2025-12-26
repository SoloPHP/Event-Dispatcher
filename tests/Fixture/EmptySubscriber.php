<?php

declare(strict_types=1);

namespace Tests\Fixture;

use Solo\EventDispatcher\EventSubscriberInterface;

final class EmptySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
