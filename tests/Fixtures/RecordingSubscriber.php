<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Tests\Fixtures;

use Solo\EventDispatcher\EventSubscriberInterface;

final class RecordingSubscriber implements EventSubscriberInterface
{
    /** @var array<mixed> what getSubscribedEvents() returns, valid or not; tests set it first */
    public static array $subscriptions = [];

    /** @var list<string> */
    public array $calls = [];

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return self::$subscriptions; // @phpstan-ignore return.type
    }

    public function first(object $event): void
    {
        $this->calls[] = 'first';
    }

    public function second(object $event): void
    {
        $this->calls[] = 'second';
    }

    public function third(object $event): void
    {
        $this->calls[] = 'third';
    }

    private function hidden(object $event): void // @phpstan-ignore method.unused
    {
        $this->calls[] = 'hidden';
    }
}
