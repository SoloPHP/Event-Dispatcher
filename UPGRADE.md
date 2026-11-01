# Upgrading from 1.x to 2.0

2.0 follows PSR-14 strictly: a listener failure stops the dispatch and reaches the caller, logger or not. Registration checks its input when it happens, and `EventDispatcherFactory` is gone. `EventDispatcher`, `ListenerProvider`, `EventSubscriberInterface` and `AbstractStoppableEvent` keep their names, constructors and methods, and PHP 8.3 remains the minimum.

## Checklist

| Look for | Do |
|---|---|
| `new EventDispatcher($provider, $logger)` | Failures now propagate: decide, listener by listener, which may interrupt the operation ([Listener failures](#listener-failures)) |
| `EventDispatcherFactory::create()`, `createProvider()` | Build a `ListenerProvider` and an `EventDispatcher` ([The factory](#the-factory)) |
| Log queries on `context.exception` as a class name, or on `file` and `line` | Read the exception instead ([Log record](#log-record)) |
| A listener for a parent class or an interface that must run after the class's own listeners | Give it a lower priority ([Order](#order)) |
| Event types built from strings, `hasListenersFor()` with unknown names | Use existing classes and interfaces: anything else throws ([Registration](#registration)) |
| Subscriptions with `null` priorities, extra elements or string keys | `'method'`, `['method', priority]` or a list of pairs ([Registration](#registration)) |
| A custom provider that works while it is iterated | Expect no iteration after the event is stopped ([Stopped events](#stopped-events)) |

## Listener failures

In 1.x, passing a logger made the dispatcher catch every listener failure, log it and go on with the next listener. PSR-14 requires the opposite, and 2.0 complies: the logger records the failure, then the dispatcher rethrows the **same exception**, unchanged, and runs no further listener. Without a logger, nothing changes.

An exception that used to end up in the log now reaches the caller of `dispatch()`: an HTTP handler, a job, a transaction. Keep it that way for listeners whose work the operation depends on, such as stock or balance updates: a failure no longer leaves data half updated behind a log line.

A listener whose failure must not interrupt the operation, such as a notification or a projection, handles its own exceptions:

```php
$listeners->addListener(OrderShipped::class, function (OrderShipped $event) use ($notifier, $logger): void {
    try {
        $notifier->orderShipped($event->orderId);
    } catch (\Throwable $exception) {
        $logger->error('Shipping notification failed', ['exception' => $exception]);
    }
});
```

When many listeners need it, a wrapper in the application keeps the choice visible where each listener is registered:

```php
$optional = static fn (callable $listener): \Closure => static function (object $event) use ($listener, $logger): void {
    try {
        $listener($event);
    } catch (\Throwable $exception) {
        $logger->error('Optional event listener failed', ['exception' => $exception, 'event' => $event::class]);
    }
};

$listeners->addListener(OrderShipped::class, $optional($notifyCustomer));
```

A `try`/`catch` around `dispatch()` cannot resume the listeners after the one that failed.

## The factory

`EventDispatcherFactory` repeated `ListenerProvider` in arrays that static analysis could not check, in a format with traps: `[$first, $second]`, two listeners for one event, was rejected. Register on the provider:

```php
// 1.x
$dispatcher = EventDispatcherFactory::create(
    listeners: [OrderPlaced::class => [[$reserveStock, 10], [$sendConfirmation]]],
    subscribers: [AuditSubscriber::class, fn () => new MailSubscriber($mailer)],
    logger: $logger,
);

// 2.0
$listeners = new ListenerProvider();
$listeners->addListener(OrderPlaced::class, $reserveStock, 10);
$listeners->addListener(OrderPlaced::class, $sendConfirmation);
$listeners->addSubscriber(new AuditSubscriber());
$listeners->addSubscriber(new MailSubscriber($mailer));

$dispatcher = new EventDispatcher($listeners, $logger);
```

`EventDispatcherFactory::createProvider()` becomes the `ListenerProvider` above.

## Log record

| | 1.x | 2.0 |
|---|---|---|
| Message | `Event listener failed: {exception} {message}` | `Event listener failed: {message}` |
| `exception` | The exception's class name | The `Throwable`, as PSR-3 recommends; read the class with `$context['exception']::class` |
| `file`, `line` | Where the exception was thrown | Removed: read them from the exception |
| `listener` | `Closure` for closures and subscriber methods | `Class::method`, `function`, `Class::__invoke`, or `Closure at /path/to/file.php:42` |
| `message`, `event` | — | Unchanged |

## Order

When priorities were equal, 1.x ran the listeners registered for the event's own class before those registered for its parent classes and interfaces. 2.0 orders by priority, then by registration, wherever in the type hierarchy a listener was registered. Where the class's own listener must run first, give it a higher priority.

## Registration

- `addListener()`, `addSubscriber()` and `hasListenersFor()` accept existing classes and interfaces only: an unknown name, an empty string or a trait throws `InvalidConfigurationException`. Class aliases and differently cased names now match.
- A subscription is `'method'`, `['method', priority]`, or a list of `['method', priority]` pairs whose priority is optional. `null` priorities, extra elements and string keys throw.
- A subscribed method must exist and be public.
- A subscriber with an invalid entry throws before any of its methods is registered.
- `getListenersForEvent()` returns subscriber methods as `[$subscriber, 'method']` callables.

Error messages are not part of the compatibility promise: catch `InvalidConfigurationException`, and leave its text alone.

## Stopped events

An event that is already stopped when dispatched is returned without asking the provider for listeners. Once a listener stops the event, the dispatcher returns without asking the provider for another listener, which matters to providers that build listeners lazily.

## Rollout

Require `solophp/event-dispatcher:^2.0`, update it, and run the tests that cover your event flows and their failure paths.
