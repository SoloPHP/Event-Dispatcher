<?php

declare(strict_types=1);

namespace Tests\Fixture;

use Solo\EventDispatcher\EventSubscriberInterface;

final class TestSubscriber implements EventSubscriberInterface
{
    public function __construct(private \Closure $record)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [TestEvent::class => 'on'];
    }

    public function on(TestEvent $e): void
    {
        ($this->record)($e->name);
    }
}
