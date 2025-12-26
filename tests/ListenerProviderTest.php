<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Solo\EventDispatcher\ListenerProvider;
use Tests\Fixture\ChildEvent;
use Tests\Fixture\EmptySubscriber;
use Tests\Fixture\EventInterface;
use Tests\Fixture\InterfaceEvent;
use Tests\Fixture\ParentEvent;
use Tests\Fixture\TestEvent;
use Tests\Fixture\TestSubscriber;

final class ListenerProviderTest extends TestCase
{
    public function testHasListenersForReturnsFalseWhenNoListeners(): void
    {
        $provider = new ListenerProvider();

        self::assertFalse($provider->hasListenersFor(TestEvent::class));
    }

    public function testGetListenersForEventReturnsEmptyForUnknownEvent(): void
    {
        $provider = new ListenerProvider();

        $listeners = $provider->getListenersForEvent(new TestEvent('test'));

        self::assertSame([], $listeners);
    }

    public function testListenersAreCalledInPriorityOrder(): void
    {
        $order = [];
        $provider = new ListenerProvider();

        $provider->addListener(TestEvent::class, function () use (&$order): void {
            $order[] = 'low';
        }, 0);

        $provider->addListener(TestEvent::class, function () use (&$order): void {
            $order[] = 'high';
        }, 10);

        $provider->addListener(TestEvent::class, function () use (&$order): void {
            $order[] = 'medium';
        }, 5);

        foreach ($provider->getListenersForEvent(new TestEvent('test')) as $listener) {
            $listener();
        }

        self::assertSame(['high', 'medium', 'low'], $order);
    }

    public function testListenersWithSamePriorityPreserveInsertionOrder(): void
    {
        $order = [];
        $provider = new ListenerProvider();

        $provider->addListener(TestEvent::class, function () use (&$order): void {
            $order[] = 'first';
        }, 0);

        $provider->addListener(TestEvent::class, function () use (&$order): void {
            $order[] = 'second';
        }, 0);

        $provider->addListener(TestEvent::class, function () use (&$order): void {
            $order[] = 'third';
        }, 0);

        foreach ($provider->getListenersForEvent(new TestEvent('test')) as $listener) {
            $listener();
        }

        self::assertSame(['first', 'second', 'third'], $order);
    }

    public function testAddSubscriberRegistersEventListeners(): void
    {
        $log = [];
        $provider = new ListenerProvider();
        $provider->addSubscriber(new TestSubscriber(function (string $name) use (&$log): void {
            $log[] = $name;
        }));

        self::assertTrue($provider->hasListenersFor(TestEvent::class));

        foreach ($provider->getListenersForEvent(new TestEvent('hello')) as $listener) {
            $listener(new TestEvent('hello'));
        }

        self::assertSame(['hello'], $log);
    }

    public function testAddSubscriberWithEmptyEventsDoesNotRegisterListeners(): void
    {
        $provider = new ListenerProvider();
        $provider->addSubscriber(new EmptySubscriber());

        self::assertFalse($provider->hasListenersFor(TestEvent::class));
    }

    public function testGetListenersForEventIncludesParentClassListeners(): void
    {
        $order = [];
        $provider = new ListenerProvider();

        $provider->addListener(ParentEvent::class, function () use (&$order): void {
            $order[] = 'parent';
        });

        $provider->addListener(ChildEvent::class, function () use (&$order): void {
            $order[] = 'child';
        });

        foreach ($provider->getListenersForEvent(new ChildEvent()) as $listener) {
            $listener();
        }

        self::assertContains('parent', $order);
        self::assertContains('child', $order);
    }

    public function testGetListenersForEventIncludesInterfaceListeners(): void
    {
        $order = [];
        $provider = new ListenerProvider();

        $provider->addListener(EventInterface::class, function () use (&$order): void {
            $order[] = 'interface';
        });

        $provider->addListener(InterfaceEvent::class, function () use (&$order): void {
            $order[] = 'concrete';
        });

        foreach ($provider->getListenersForEvent(new InterfaceEvent()) as $listener) {
            $listener();
        }

        self::assertContains('interface', $order);
        self::assertContains('concrete', $order);
    }

    public function testParentListenersOnlyCalledForChildEvents(): void
    {
        $called = false;
        $provider = new ListenerProvider();

        $provider->addListener(ChildEvent::class, function () use (&$called): void {
            $called = true;
        });

        foreach ($provider->getListenersForEvent(new ParentEvent()) as $listener) {
            $listener();
        }

        self::assertFalse($called);
    }

    public function testHasListenersForConsidersParentClassListeners(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(ParentEvent::class, fn () => null);

        self::assertTrue($provider->hasListenersFor(ChildEvent::class));
    }

    public function testHasListenersForConsidersInterfaceListeners(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(EventInterface::class, fn () => null);

        self::assertTrue($provider->hasListenersFor(InterfaceEvent::class));
    }
}
