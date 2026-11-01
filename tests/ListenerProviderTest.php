<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solo\EventDispatcher\EventDispatcher;
use Solo\EventDispatcher\Exception\InvalidConfigurationException;
use Solo\EventDispatcher\ListenerProvider;
use Solo\EventDispatcher\Tests\Fixtures\ChildEvent;
use Solo\EventDispatcher\Tests\Fixtures\EventTrait;
use Solo\EventDispatcher\Tests\Fixtures\ExtendedMarkerInterface;
use Solo\EventDispatcher\Tests\Fixtures\MarkerInterface;
use Solo\EventDispatcher\Tests\Fixtures\ParentEvent;
use Solo\EventDispatcher\Tests\Fixtures\RecordingSubscriber;
use Solo\EventDispatcher\Tests\Fixtures\TestEvent;

final class ListenerProviderTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingSubscriber::$subscriptions = [];
    }

    public function testHasNoListenersForAnEventNobodyListensTo(): void
    {
        $provider = new ListenerProvider();

        self::assertSame([], $provider->getListenersForEvent(new TestEvent()));
        self::assertFalse($provider->hasListenersFor(TestEvent::class));
    }

    public function testOrdersListenersByDescendingPriority(): void
    {
        $provider = new ListenerProvider();
        [$lowest, $low, $default, $high, $highest] = self::listeners(5);
        $provider->addListener(TestEvent::class, $default);
        $provider->addListener(TestEvent::class, $lowest, PHP_INT_MIN);
        $provider->addListener(TestEvent::class, $highest, PHP_INT_MAX);
        $provider->addListener(TestEvent::class, $low, -10);
        $provider->addListener(TestEvent::class, $high, 10);

        self::assertSame(
            [$highest, $high, $default, $low, $lowest],
            $provider->getListenersForEvent(new TestEvent()),
        );
    }

    public function testKeepsRegistrationOrderWithinAPriority(): void
    {
        $provider = new ListenerProvider();
        [$first, $second, $third] = self::listeners(3);
        $provider->addListener(TestEvent::class, $first);
        $provider->addListener(TestEvent::class, $second);
        $provider->addListener(TestEvent::class, $third);

        self::assertSame([$first, $second, $third], $provider->getListenersForEvent(new TestEvent()));
    }

    public function testIncludesListenersForParentClassesAndInterfacesOnce(): void
    {
        $provider = new ListenerProvider();
        [$child, $parent, $marker, $extendedMarker] = self::listeners(4);
        $provider->addListener(ChildEvent::class, $child, 4);
        $provider->addListener(ParentEvent::class, $parent, 3);
        $provider->addListener(MarkerInterface::class, $marker, 2);
        $provider->addListener(ExtendedMarkerInterface::class, $extendedMarker, 1);

        self::assertSame(
            [$child, $parent, $marker, $extendedMarker],
            $provider->getListenersForEvent(new ChildEvent()),
        );
        self::assertSame([$parent, $marker], $provider->getListenersForEvent(new ParentEvent()));
    }

    public function testRegistrationOrderBreaksTiesAcrossEventTypes(): void
    {
        $provider = new ListenerProvider();
        [$parent, $child] = self::listeners(2);
        $provider->addListener(ParentEvent::class, $parent);
        $provider->addListener(ChildEvent::class, $child);

        self::assertSame([$parent, $child], $provider->getListenersForEvent(new ChildEvent()));
    }

    public function testMatchesAnAnonymousEventClassByItsInterfaces(): void
    {
        $provider = new ListenerProvider();
        [$listener] = self::listeners(1);
        $provider->addListener(MarkerInterface::class, $listener);
        $event = new class implements ExtendedMarkerInterface {
        };

        self::assertSame([$listener], $provider->getListenersForEvent($event));
    }

    public function testHasListenersForCountsParentsAndInterfaces(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(MarkerInterface::class, static fn () => null);

        self::assertTrue($provider->hasListenersFor(ChildEvent::class));
        self::assertTrue($provider->hasListenersFor(ExtendedMarkerInterface::class));
        self::assertFalse($provider->hasListenersFor(TestEvent::class));
    }

    public function testNewListenersReachEventTypesResolvedBefore(): void
    {
        $provider = new ListenerProvider();
        [$first, $second] = self::listeners(2);
        self::assertSame([], $provider->getListenersForEvent(new ChildEvent()));

        $provider->addListener(ParentEvent::class, $first);
        self::assertSame([$first], $provider->getListenersForEvent(new ChildEvent()));

        $provider->addListener(ChildEvent::class, $second, 1);
        self::assertSame([$second, $first], $provider->getListenersForEvent(new ChildEvent()));
    }

    public function testAcceptsClassAliasesAndOtherSpellingsOfTheEventClass(): void
    {
        $alias = __NAMESPACE__ . '\\TestEventAlias';
        if (!class_exists($alias, false)) {
            class_alias(TestEvent::class, $alias);
        }
        $provider = new ListenerProvider();
        [$byAlias, $byLowercaseName] = self::listeners(2);
        $provider->addListener($alias, $byAlias); // @phpstan-ignore argument.type
        $provider->addListener('\\' . strtolower(TestEvent::class), $byLowercaseName);

        self::assertSame([$byAlias, $byLowercaseName], $provider->getListenersForEvent(new TestEvent()));
        self::assertTrue($provider->hasListenersFor($alias)); // @phpstan-ignore argument.type
    }

    /** @return iterable<string, array{string}> */
    public static function unknownEventTypes(): iterable
    {
        yield 'missing class' => ['Missing\\Event'];
        yield 'empty name' => [''];
        yield 'trait' => [EventTrait::class];
    }

    #[DataProvider('unknownEventTypes')]
    public function testRejectsAListenerForAnUnknownEventType(string $eventClass): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(sprintf('Event type "%s" must be an existing class or interface.', $eventClass));

        (new ListenerProvider())->addListener($eventClass, static fn () => null); // @phpstan-ignore argument.type
    }

    #[DataProvider('unknownEventTypes')]
    public function testHasListenersForRejectsAnUnknownEventType(string $eventClass): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new ListenerProvider())->hasListenersFor($eventClass); // @phpstan-ignore argument.type
    }

    /** @return iterable<string, array{mixed, list<string>}> */
    public static function subscriptions(): iterable
    {
        yield 'method name' => ['first', ['first']];
        yield 'method in a list' => [['first'], ['first']];
        yield 'method with a priority' => [['first', 10], ['first']];
        yield 'methods by priority' => [[['first', -10], ['second', 10], ['third']], ['second', 'third', 'first']];
        yield 'equal priorities in declaration order' => [[['third'], ['first']], ['third', 'first']];
        yield 'no methods' => [[], []];
    }

    /** @param list<string> $expectedCalls */
    #[DataProvider('subscriptions')]
    public function testRegistersTheSubscribedMethods(mixed $subscription, array $expectedCalls): void
    {
        RecordingSubscriber::$subscriptions = [TestEvent::class => $subscription];
        $subscriber = new RecordingSubscriber();
        $provider = new ListenerProvider();
        $provider->addSubscriber($subscriber);

        (new EventDispatcher($provider))->dispatch(new TestEvent());

        self::assertSame($expectedCalls, $subscriber->calls);
    }

    public function testSubscribesToSeveralEventTypesAlongsideOtherListeners(): void
    {
        RecordingSubscriber::$subscriptions = [
            TestEvent::class => ['first', 10],
            MarkerInterface::class => 'second',
        ];
        $subscriber = new RecordingSubscriber();
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, static function () use ($subscriber): void {
            $subscriber->calls[] = 'closure';
        }, 20);
        $provider->addSubscriber($subscriber);
        $dispatcher = new EventDispatcher($provider);

        $dispatcher->dispatch(new TestEvent());
        $dispatcher->dispatch(new ChildEvent());

        self::assertSame(['closure', 'first', 'second'], $subscriber->calls);
    }

    /** @return iterable<string, array{mixed}> */
    public static function malformedSubscriptions(): iterable
    {
        yield 'number' => [123];
        yield 'null' => [null];
        yield 'string priority' => [['first', '10']];
        yield 'float priority' => [['first', 1.5]];
        yield 'null priority' => [['first', null]];
        yield 'extra element' => [['first', 10, 'extra']];
        yield 'named keys' => [['method' => 'first']];
        yield 'sparse pair' => [[0 => 'first', 2 => 10]];
        yield 'named list' => [['handler' => ['first', 10]]];
        yield 'empty pair' => [[[]]];
        yield 'method that is not a string' => [[[123, 10]]];
        yield 'bare method among pairs' => [[['first', 10], 'second']];
    }

    #[DataProvider('malformedSubscriptions')]
    public function testRejectsAMalformedSubscriptionWithoutRegisteringAnything(mixed $subscription): void
    {
        self::assertSubscriberRejected(
            [ParentEvent::class => 'first', TestEvent::class => $subscription],
            sprintf(
                'Invalid subscription to "%s" in %s::getSubscribedEvents()',
                TestEvent::class,
                RecordingSubscriber::class,
            ),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function unusableMethods(): iterable
    {
        yield 'private method' => ['hidden'];
        yield 'missing method' => ['missing'];
        yield 'empty name' => [''];
    }

    #[DataProvider('unusableMethods')]
    public function testRejectsAMethodThatIsNotPublicWithoutRegisteringAnything(string $method): void
    {
        self::assertSubscriberRejected(
            [ParentEvent::class => 'first', TestEvent::class => [['second'], [$method]]],
            sprintf('%s::%s() for event "%s"', RecordingSubscriber::class, $method, TestEvent::class),
        );
    }

    /** @return iterable<string, array{array<mixed>, string}> */
    public static function subscriptionsToUnknownTypes(): iterable
    {
        yield 'missing class' => [['Missing\\Event' => 'first'], 'Missing\\Event'];
        yield 'list instead of a map' => [['first'], '0'];
    }

    /** @param array<mixed> $subscriptions */
    #[DataProvider('subscriptionsToUnknownTypes')]
    public function testRejectsASubscriptionToAnUnknownEventType(array $subscriptions, string $eventClass): void
    {
        self::assertSubscriberRejected(
            [ParentEvent::class => 'first', ...$subscriptions],
            sprintf('Event type "%s" must be an existing class or interface.', $eventClass),
        );
    }

    /**
     * Adds a subscriber declaring $subscriptions and checks that it is rejected
     * without registering any method, including those declared before the invalid entry.
     *
     * @param array<mixed> $subscriptions
     */
    private static function assertSubscriberRejected(array $subscriptions, string $message): void
    {
        RecordingSubscriber::$subscriptions = $subscriptions;
        $provider = new ListenerProvider();

        try {
            $provider->addSubscriber(new RecordingSubscriber());
            self::fail('The subscriber must be rejected.');
        } catch (InvalidConfigurationException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }

        self::assertFalse($provider->hasListenersFor(ParentEvent::class));
        self::assertFalse($provider->hasListenersFor(TestEvent::class));
    }

    /** @return list<\Closure> distinct listeners that compare by identity */
    private static function listeners(int $count): array
    {
        return array_map(static fn (int $id): \Closure => static fn (): int => $id, range(1, $count));
    }
}
