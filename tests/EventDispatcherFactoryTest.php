<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Solo\EventDispatcher\EventDispatcher;
use Solo\EventDispatcher\EventDispatcherFactory;
use Solo\EventDispatcher\Exception\InvalidConfigurationException;
use Solo\EventDispatcher\ListenerProvider;
use Tests\Fixture\EmptySubscriber;
use Tests\Fixture\TestEvent;
use Tests\Fixture\TestSubscriber;

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
        $provider = EventDispatcherFactory::createProvider(
            subscribers: [
                fn () => new TestSubscriber(fn () => null),
            ]
        );

        self::assertTrue($provider->hasListenersFor(TestEvent::class));
    }

    public function testCreateProviderWithSubscriberClassName(): void
    {
        $provider = EventDispatcherFactory::createProvider(
            subscribers: [EmptySubscriber::class]
        );

        self::assertInstanceOf(ListenerProvider::class, $provider);
    }

    public function testThrowsExceptionForNonExistentSubscriberClass(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not exist');

        EventDispatcherFactory::createProvider(
            subscribers: ['NonExistent\\ClassName'] // @phpstan-ignore argument.type
        );
    }

    public function testThrowsExceptionForClassNotImplementingInterface(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must implement');

        EventDispatcherFactory::createProvider(
            subscribers: [\stdClass::class] // @phpstan-ignore argument.type
        );
    }

    public function testThrowsExceptionForInvalidCallableResult(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must return an instance');

        EventDispatcherFactory::createProvider(
            subscribers: [fn () => new \stdClass()] // @phpstan-ignore argument.type
        );
    }

    public function testThrowsExceptionForInvalidListenerConfig(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        EventDispatcherFactory::createProvider(
            listeners: [TestEvent::class => 'not_callable'] // @phpstan-ignore argument.type
        );
    }

    public function testThrowsExceptionForInvalidSubscriberType(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expected');

        EventDispatcherFactory::createProvider(
            subscribers: [123] // @phpstan-ignore argument.type
        );
    }

    public function testSingleListenerWithPriority(): void
    {
        $log = [];

        $dispatcher = EventDispatcherFactory::create(
            listeners: [
                TestEvent::class => [
                    function (TestEvent $e) use (&$log): void {
                        $log[] = $e->name;
                    },
                    10,
                ],
            ]
        );

        $dispatcher->dispatch(new TestEvent('single'));

        self::assertSame(['single'], $log);
    }

    public function testSingleCallableListener(): void
    {
        $log = [];

        $dispatcher = EventDispatcherFactory::create(
            listeners: [
                TestEvent::class => function (TestEvent $e) use (&$log): void {
                    $log[] = $e->name;
                },
            ]
        );

        $dispatcher->dispatch(new TestEvent('callable'));

        self::assertSame(['callable'], $log);
    }
}
