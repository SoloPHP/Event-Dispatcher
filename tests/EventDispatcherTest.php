<?php

declare(strict_types=1);

namespace Tests;

function failing_listener(Fixture\TestEvent $e): void
{
    throw new \RuntimeException('string fail');
}

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
            $e->stopPropagation();
        }, 10);
        $provider->addListener(StoppableTestEvent::class, function (StoppableTestEvent $e) use (&$order): void {
            $order[] = 2;
        }, 0);

        $dispatcher = new EventDispatcher($provider);
        $dispatcher->dispatch(new StoppableTestEvent());

        self::assertSame([1], $order);
    }

    public function testPreStoppedEventSkipsAllListeners(): void
    {
        $called = false;

        $provider = new ListenerProvider();
        $provider->addListener(StoppableTestEvent::class, function () use (&$called): void {
            $called = true;
        });

        $event = new StoppableTestEvent();
        $event->stopPropagation();

        $dispatcher = new EventDispatcher($provider);
        $dispatcher->dispatch($event);

        self::assertFalse($called);
    }

    public function testDispatchReturnsEvent(): void
    {
        $provider = new ListenerProvider();
        $dispatcher = new EventDispatcher($provider);

        $event = new TestEvent('test');
        $result = $dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    public function testListenerExceptionBubblesUpWithoutLogger(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, function (): void {
            throw new \RuntimeException('boom');
        });

        $dispatcher = new EventDispatcher($provider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $dispatcher->dispatch(new TestEvent('x'));
    }

    public function testListenerExceptionWithLoggerContinuesExecution(): void
    {
        $called = false;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, function (): void {
            throw new \RuntimeException('boom');
        }, 10);
        $provider->addListener(TestEvent::class, function () use (&$called): void {
            $called = true;
        }, 0);

        $dispatcher = new EventDispatcher($provider, $logger);
        $event = $dispatcher->dispatch(new TestEvent('x'));

        self::assertTrue($called);
        self::assertInstanceOf(TestEvent::class, $event);
    }

    public function testListenerExceptionIsLoggedWithLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Event listener failed: {exception} {message}',
                self::callback(function (array $context): bool {
                    return $context['exception'] === \RuntimeException::class
                        && $context['message'] === 'boom'
                        && $context['event'] === TestEvent::class
                        && $context['listener'] === 'Closure';
                })
            );

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, function (): void {
            throw new \RuntimeException('boom');
        });

        $dispatcher = new EventDispatcher($provider, $logger);
        $dispatcher->dispatch(new TestEvent('x'));
    }

    public function testDescribeListenerWithArrayCallable(): void
    {
        $handler = new class {
            public function handle(TestEvent $e): void
            {
                throw new \RuntimeException('array fail');
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                self::anything(),
                self::callback(function (array $context) use ($handler): bool {
                    return $context['listener'] === $handler::class . '::handle';
                })
            );

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, [$handler, 'handle']);

        $dispatcher = new EventDispatcher($provider, $logger);
        $dispatcher->dispatch(new TestEvent('x'));
    }

    public function testDescribeListenerWithStringCallable(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    return $context['listener'] === 'Tests\failing_listener';
                })
            );

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'Tests\failing_listener');

        $dispatcher = new EventDispatcher($provider, $logger);
        $dispatcher->dispatch(new TestEvent('x'));
    }

    public function testDescribeListenerWithInvokableObject(): void
    {
        $invokable = new class {
            public function __invoke(TestEvent $e): void
            {
                throw new \RuntimeException('invokable fail');
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                self::anything(),
                self::callback(function (array $context) use ($invokable): bool {
                    return $context['listener'] === $invokable::class . '::__invoke';
                })
            );

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, $invokable);

        $dispatcher = new EventDispatcher($provider, $logger);
        $dispatcher->dispatch(new TestEvent('x'));
    }
}