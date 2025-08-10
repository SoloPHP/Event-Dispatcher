<?php

declare(strict_types=1);

namespace Tests\Factory;

use PHPUnit\Framework\TestCase;
use Solo\EventDispatcher\Factory\EventDispatcherFactory;
use Tests\Fixture\{TestEvent, TestSubscriber};

final class EventDispatcherFactoryTest extends TestCase
{
    public function testCreateWithSubscribersAndListeners(): void
    {
        $log = [];

        $dispatcher = EventDispatcherFactory::create(
            listeners: [
                TestEvent::class => [
                    [
                        function (TestEvent $e) use (&$log): void {
                            $log[] = 'l1:' . $e->name;
                        },
                        5,
                    ],
                    [
                        function (TestEvent $e) use (&$log): void {
                            $log[] = 'l2:' . $e->name;
                        },
                        0,
                    ],
                ],
            ],
            subscribers: [
                new TestSubscriber(function (string $name) use (&$log): void {
                    $log[] = 's:' . $name;
                }),
            ]
        );

        $dispatcher->dispatch(new TestEvent('x'));

        self::assertSame(['l1:x', 'l2:x', 's:x'], $log);
    }

    public function testCreateProviderWithSubscriberFactoryCallable(): void
    {
        $provider = EventDispatcherFactory::createProvider([], [
            fn () => new TestSubscriber(fn (string $n) => null),
        ]);
        $r = new \ReflectionClass($provider);
        $p = $r->getProperty('listenersByEvent');
        $p->setAccessible(true);
        $map = $p->getValue($provider);
        self::assertArrayHasKey(TestEvent::class, $map);
    }
}
