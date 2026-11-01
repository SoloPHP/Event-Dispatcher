# Public API reference

This reference describes Solo Event Dispatcher 2.0: PHP 8.3+, `psr/event-dispatcher` 1.0, `psr/log` 3.0 and the `Solo\EventDispatcher` namespace. [Getting started](index.md) walks through the setup, and [Behavior in detail](details.md) explains the rules below.

The public API consists of five types. `EventDispatcher` is `final readonly`; `ListenerProvider` and `InvalidConfigurationException` are `final`. Behavior is extended by composition through the PSR-14 interfaces, not by inheritance. `dispatch()` and `addListener()` carry PHPDoc generics, so static analysis knows that `dispatch()` returns the type of its event and checks every listener against the event class it is registered for. Exception messages are not part of the compatibility promise.

| Type | Role |
|---|---|
| `EventDispatcher` | Calls the listeners of an event; implements `Psr\EventDispatcher\EventDispatcherInterface` |
| `ListenerProvider` | Holds listeners and subscribers; implements `Psr\EventDispatcher\ListenerProviderInterface` |
| `EventSubscriberInterface` | Declares the methods of one object that handle each event |
| `AbstractStoppableEvent` | Base for events whose propagation a listener can stop |
| `Exception\InvalidConfigurationException` | Thrown for an invalid registration |

## EventDispatcher

```php
public function __construct(
    ListenerProviderInterface $listenerProvider,
    ?LoggerInterface $logger = null,
)

/**
 * @template T of object
 * @param T $event
 * @return T
 */
public function dispatch(object $event): object
```

`dispatch()` accepts any object and returns that same object:

1. If the event implements `StoppableEventInterface` and is already stopped, it is returned at once, without asking the provider for listeners.
2. Otherwise the provider's listeners are called one by one, in the order the provider gives them, each with the event as its only argument. Return values are ignored.
3. After each call, a stoppable event is checked again. Once it is stopped, the dispatcher returns without asking the provider for another listener.
4. If a listener throws, no further listener runs, and the exception reaches the caller unchanged: the same object, with the same `getPrevious()` chain. A logger, when given, records the failure first: see [Failure log record](#failure-log-record).

The dispatcher works with any PSR-14 provider, including one that yields listeners lazily from a generator. It keeps no state between dispatches: one instance serves the whole application, and a listener may dispatch other events.

## ListenerProvider

### addListener()

```php
/**
 * @template T of object
 * @param class-string<T> $eventClass
 * @param callable(T): mixed $listener
 */
public function addListener(string $eventClass, callable $listener, int $priority = 0): void
```

Registers a listener for an existing class or interface and all of its subtypes. Higher priorities run first; equal priorities run in registration order. Registering the same callable twice makes it run twice. A missing class, an empty string or a trait throws `InvalidConfigurationException`. A class alias, or a name in another letter case, matches the class it names.

The provider does not inspect the listener's parameters. A listener that cannot accept the dispatched event fails with a `TypeError` when it is called, and that failure propagates like any other; static analysis reports the mismatch earlier, through the `callable(T)` type.

### addSubscriber()

```php
public function addSubscriber(EventSubscriberInterface $subscriber): void
```

Registers one listener, `[$subscriber, 'method']`, for each method that `$subscriber::getSubscribedEvents()` names. The whole declaration is checked first: if an event type is unknown, an entry is malformed or a method is not public, `InvalidConfigurationException` is thrown and nothing is registered. Subscriber listeners are ordered together with all others, by priority, then registration.

### hasListenersFor()

```php
/** @param class-string $eventClass */
public function hasListenersFor(string $eventClass): bool
```

Whether an event of this type reaches any listener, counting the listeners registered for its parent classes and interfaces. For an interface, it counts the listeners registered for that interface and for the interfaces it extends; an implementing class may reach more. A type that is not an existing class or interface throws `InvalidConfigurationException`.

Use it to skip building an event that is expensive to construct. Dispatching an event that nobody listens to is cheap.

### getListenersForEvent()

```php
/** @return list<callable> */
public function getListenersForEvent(object $event): iterable
```

The PSR-14 method. It returns the listeners registered for the event's class, parent classes and interfaces, each once, ordered by priority, then registration. The result is an array: the dispatcher iterates over that snapshot, so a listener registered during a dispatch takes part from the next dispatch on.

## EventSubscriberInterface

```php
/** @return array<class-string, string|array{0: string, 1?: int}|list<array{0: string, 1?: int}>> */
public static function getSubscribedEvents(): array;
```

Keys are event classes or interfaces. Each value takes one of these forms:

| Form | Registers |
|---|---|
| `'method'` | `method`, priority 0 |
| `['method']` | `method`, priority 0 |
| `['method', 10]` | `method`, priority 10 |
| `[['first', 10], ['second']]` | `first` with priority 10, then `second` with priority 0 |
| `[]` | Nothing |

A priority is an `int`: `null`, strings and floats are rejected. A pair has one or two elements and no string keys, and a list holds pairs only. Each method must exist on the subscriber and be public.

## AbstractStoppableEvent

```php
abstract class AbstractStoppableEvent implements StoppableEventInterface
{
    public function stopPropagation(): void;

    public function isPropagationStopped(): bool;
}
```

`stopPropagation()` cannot be undone. The class is not `readonly`, since its flag changes, so a `readonly` event class cannot extend it. Such an event, or one with a base class of its own, implements `Psr\EventDispatcher\StoppableEventInterface` itself.

## InvalidConfigurationException

`Solo\EventDispatcher\Exception\InvalidConfigurationException` extends `InvalidArgumentException`. It is thrown when:

| Method | Cause |
|---|---|
| `addListener()`, `hasListenersFor()` | The event type is not an existing class or interface |
| `addSubscriber()` | An event type is unknown, an entry has none of the forms above, or a method does not exist or is not public |

It is never thrown during a dispatch.

## Failure log record

With a logger, each listener failure produces one `error` record before the exception propagates. The message is `Event listener failed: {message}`, and the context holds:

| Key | Value |
|---|---|
| `exception` | The `Throwable` the listener threw |
| `message` | Its message |
| `event` | The event class |
| `listener` | The listener, described as below |

| Listener | `listener` |
|---|---|
| `[$service, 'handle']` or `$service->handle(...)` | `App\Service::handle` |
| `[Service::class, 'handle']` or `'App\Service::handle'` | `App\Service::handle` |
| An invokable object | `App\Handler::__invoke` |
| `'trim'` or `trim(...)` | `trim` |
| A closure | `Closure at /app/src/Providers/EventServiceProvider.php:42` |

An anonymous class appears under the name `get_debug_type()` gives it, such as `class@anonymous`. A logger that throws is ignored: the listener's exception propagates as it was.

## Errors and supported boundaries

- Dispatch is synchronous and in-process. Queues, retries and asynchronous delivery belong to the application.
- Events are matched by type alone: there are no event names, no wildcards and no listener removal.
- The dispatcher never swallows or wraps a listener's exception.
- Listeners may change a mutable event; the dispatcher neither copies nor freezes it.
