<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Solo\EventDispatcher\{EventDispatcher, ListenerProvider};
use Tests\Fixture\{TestEvent, TestSubscriber, StoppableTestEvent};

final class EventDispatcherTest extends TestCase
{
    public function testDispatchInvokesSubscriberMethod(): void
    {
        $log = [];
        $provider = new ListenerProvider();
        $provider->addSubscriber(new TestSubscriber(function (string $name) use (&$log): void {
            $log[] = $name;
        }));

        $dispatcher = new EventDispatcher($provider);
        $dispatcher->dispatch(new TestEvent('hello'));

        self::assertSame(['hello'], $log);
    }

    public function testPropagationStopsWhenStoppableEvent(): void
    {
        $order = [];

        $provider = new ListenerProvider();
        $provider->addListener(StoppableTestEvent::class, function (StoppableTestEvent $e) use (&$order): void {
            $order[] = 1;
            $e->stop();
        }, 10);
        $provider->addListener(StoppableTestEvent::class, function (StoppableTestEvent $e) use (&$order): void {
            $order[] = 2;
        }, 0);

        $dispatcher = new EventDispatcher($provider);
        $dispatcher->dispatch(new StoppableTestEvent());

        self::assertSame([1], $order);
    }
}
