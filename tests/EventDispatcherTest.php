<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Solo\EventDispatcher\EventDispatcher;
use Solo\EventDispatcher\ListenerProvider;
use Solo\EventDispatcher\Tests\Fixtures\FailingListener;
use Solo\EventDispatcher\Tests\Fixtures\SpyLogger;
use Solo\EventDispatcher\Tests\Fixtures\StoppableEvent;
use Solo\EventDispatcher\Tests\Fixtures\TestEvent;

final class EventDispatcherTest extends TestCase
{
    public function testReturnsTheSameEventWhenNothingListens(): void
    {
        $event = new TestEvent();

        self::assertSame($event, (new EventDispatcher(new ListenerProvider()))->dispatch($event));
    }

    public function testCallsEachListenerWithTheEventInProviderOrder(): void
    {
        $event = new TestEvent();
        $calls = [];
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, static function (TestEvent $received) use ($event, &$calls): void {
            self::assertSame($event, $received);
            $calls[] = 'low';
        }, -10);
        $provider->addListener(TestEvent::class, static function () use (&$calls): string {
            $calls[] = 'high';

            return 'return values are ignored';
        }, 10);

        self::assertSame($event, (new EventDispatcher($provider))->dispatch($event));
        self::assertSame(['high', 'low'], $calls);
    }

    public function testStopsAfterTheListenerThatStopsPropagation(): void
    {
        $calls = [];
        $provider = new ListenerProvider();
        $provider->addListener(StoppableEvent::class, static function (StoppableEvent $event) use (&$calls): void {
            $calls[] = 'stopping';
            $event->stopPropagation();
        }, 10);
        $provider->addListener(StoppableEvent::class, static function () use (&$calls): void {
            $calls[] = 'skipped';
        });

        $event = (new EventDispatcher($provider))->dispatch(new StoppableEvent());

        self::assertTrue($event->isPropagationStopped());
        self::assertSame(['stopping'], $calls);
    }

    public function testSkipsTheProviderForAnAlreadyStoppedEvent(): void
    {
        $provider = $this->createMock(ListenerProviderInterface::class);
        $provider->expects(self::never())->method('getListenersForEvent');
        $event = new StoppableEvent();
        $event->stopPropagation();

        self::assertSame($event, (new EventDispatcher($provider))->dispatch($event));
    }

    public function testDoesNotAdvanceALazyProviderAfterPropagationStops(): void
    {
        $provider = new class implements ListenerProviderInterface {
            /** @return iterable<callable> */
            public function getListenersForEvent(object $event): iterable
            {
                yield static fn (StoppableEvent $event) => $event->stopPropagation();
                throw new \LogicException('The provider must not be asked for a listener that will not run.');
            }
        };
        $event = new StoppableEvent();

        self::assertSame($event, (new EventDispatcher($provider))->dispatch($event));
        self::assertTrue($event->isPropagationStopped());
    }

    /** @return iterable<string, array{\Throwable}> */
    public static function failures(): iterable
    {
        yield 'exception' => [new \RuntimeException('Listener failed.')];
        yield 'error' => [new \Error('Listener failed.')];
    }

    #[DataProvider('failures')]
    public function testListenerFailureStopsTheDispatchAndPropagatesUnchanged(\Throwable $failure): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, static fn () => throw $failure);
        $provider->addListener(TestEvent::class, static function (): void {
            self::fail('No listener may run after a failure.');
        });

        self::assertSame($failure, self::dispatchFailure(new EventDispatcher($provider)));
    }

    public function testAListenerThatCannotBeCalledFailsWithItsOwnError(): void
    {
        $provider = new class implements ListenerProviderInterface {
            /** @return iterable<mixed> */
            public function getListenersForEvent(object $event): iterable
            {
                yield 'missing_listener_function';
            }
        };
        $logger = new SpyLogger();

        $failure = self::dispatchFailure(new EventDispatcher($provider, $logger));

        self::assertSame(\Error::class, $failure::class);
        self::assertStringContainsString('missing_listener_function', $failure->getMessage());
        self::assertSame('missing_listener_function', $logger->records[0]['context']['listener'] ?? null);
    }

    public function testLogsAListenerFailureBeforeItPropagates(): void
    {
        $logger = new SpyLogger();
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, [new FailingListener(), 'handle']);

        $failure = self::dispatchFailure(new EventDispatcher($provider, $logger));

        self::assertCount(1, $logger->records);
        [$record] = $logger->records;
        self::assertSame(LogLevel::ERROR, $record['level']);
        self::assertSame('Event listener failed: {message}', $record['message']);
        self::assertSame([
            'exception' => $failure,
            'message' => 'Listener failed.',
            'event' => TestEvent::class,
            'listener' => FailingListener::class . '::handle',
        ], $record['context']);
    }

    public function testAFailingLoggerNeitherReplacesNorAltersTheListenerFailure(): void
    {
        $failure = new \RuntimeException('Listener failed.');
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, static fn () => throw $failure);
        $logger = new class extends AbstractLogger {
            /** @param array<mixed> $context */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                throw new \LogicException('Logger failed.');
            }
        };

        self::assertSame($failure, self::dispatchFailure(new EventDispatcher($provider, $logger)));
        self::assertNull($failure->getPrevious());
    }

    /** @return iterable<string, array{callable, string}> */
    public static function describedListeners(): iterable
    {
        $listener = new FailingListener();

        yield 'method' => [[$listener, 'handle'], FailingListener::class . '::handle'];
        yield 'invokable object' => [$listener, FailingListener::class . '::__invoke'];
        yield 'static method' => [
            [FailingListener::class, 'handleStatically'],
            FailingListener::class . '::handleStatically',
        ];
        yield 'static method string' => [
            FailingListener::class . '::handleStatically',
            FailingListener::class . '::handleStatically',
        ];
        yield 'first-class method' => [$listener->handle(...), FailingListener::class . '::handle'];
        yield 'function' => ['strlen', 'strlen'];
        yield 'first-class function' => [strlen(...), 'strlen'];
    }

    #[DataProvider('describedListeners')]
    public function testLogNamesTheFailedListener(callable $listener, string $description): void
    {
        $logger = new SpyLogger();
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, $listener);

        self::dispatchFailure(new EventDispatcher($provider, $logger));

        self::assertSame($description, $logger->records[0]['context']['listener'] ?? null);
    }

    public function testLogNamesAClosureByWhereItIsDefined(): void
    {
        $logger = new SpyLogger();
        $provider = new ListenerProvider();
        $line = __LINE__ + 1;
        $provider->addListener(TestEvent::class, static fn () => throw new \RuntimeException('Listener failed.'));

        self::dispatchFailure(new EventDispatcher($provider, $logger));

        self::assertSame(
            sprintf('Closure at %s:%d', __FILE__, $line),
            $logger->records[0]['context']['listener'] ?? null,
        );
    }

    public function testListenersAddedDuringADispatchRunFromTheNextDispatch(): void
    {
        $calls = [];
        $provider = new ListenerProvider();
        $late = static function () use (&$calls): void {
            $calls[] = 'late';
        };
        $provider->addListener(TestEvent::class, static function () use ($provider, $late, &$calls): void {
            $calls[] = 'first';
            $provider->addListener(TestEvent::class, $late);
        });
        $dispatcher = new EventDispatcher($provider);

        $dispatcher->dispatch(new TestEvent());
        self::assertSame(['first'], $calls);

        $dispatcher->dispatch(new TestEvent());
        self::assertSame(['first', 'first', 'late'], $calls);
    }

    public function testANestedDispatchRunsToCompletionInsideTheOuterOne(): void
    {
        $calls = [];
        $provider = new ListenerProvider();
        $dispatcher = new EventDispatcher($provider);
        $provider->addListener(TestEvent::class, static function (TestEvent $event) use ($dispatcher, &$calls): void {
            $calls[] = 'first:' . $event->name;
            if ($event->name === 'outer') {
                $dispatcher->dispatch(new TestEvent('inner'));
            }
        });
        $provider->addListener(TestEvent::class, static function (TestEvent $event) use (&$calls): void {
            $calls[] = 'last:' . $event->name;
        });

        $dispatcher->dispatch(new TestEvent('outer'));

        self::assertSame(['first:outer', 'first:inner', 'last:inner', 'last:outer'], $calls);
    }

    private static function dispatchFailure(EventDispatcher $dispatcher): \Throwable
    {
        try {
            $dispatcher->dispatch(new TestEvent());
        } catch (\Throwable $failure) {
            return $failure;
        }

        self::fail('The listener failure must reach the caller.');
    }
}
