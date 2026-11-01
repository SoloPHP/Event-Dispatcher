# Getting started

Solo Event Dispatcher implements PSR-14: a `ListenerProvider` holds the
listeners, and an `EventDispatcher` calls them. An event is any object, a
listener is any callable that accepts it, and listeners are matched by the
event's class, parent classes and interfaces.

## Install

PHP 8.3+ is required. Composer installs `psr/event-dispatcher` ^1.0 and
`psr/log` ^3.0 with the package.

```sh
composer require solophp/event-dispatcher
```

## Define an event

An event is an ordinary object that carries what its listeners need. It needs
no base class and no interface:

```php
final readonly class OrderPlaced
{
    public function __construct(
        public int $orderId,
        public string $email,
    ) {
    }
}
```

## Register listeners

```php
use Solo\EventDispatcher\ListenerProvider;

$listeners = new ListenerProvider();
$listeners->addListener(OrderPlaced::class, static function (OrderPlaced $event) use ($stock): void {
    $stock->reserve($event->orderId);
}, priority: 10);
$listeners->addListener(OrderPlaced::class, [$mailer, 'sendConfirmation']);
$listeners->addListener(OrderPlaced::class, $audit->record(...), priority: -10);
```

A listener may be a closure, a function name, an invokable object,
`[$object, 'method']`, `'Class::staticMethod'` or a first-class callable. Its
return value is ignored. The event type must be an existing class or interface:
anything else throws `InvalidConfigurationException` at once, not on the first
dispatch.

`addListener()` is generic over the event class, so PHPStan and Psalm check that
each listener accepts the event it is registered for.

## Dispatch

```php
use Solo\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher($listeners);
$dispatcher->dispatch(new OrderPlaced(42, 'ann@example.com'));
```

`dispatch()` calls the listeners in order and returns the same event object, so
a mutable event can carry results back to the code that dispatched it.

## Priorities and order

Higher priorities run first. The default is 0, and negative priorities are
allowed. Listeners with equal priority run in the order they were registered.
Above, the stock is reserved, then the confirmation is sent, then the audit
record is written.

## Listen to a family of events

A listener registered for an interface or a parent class receives every event
of that type:

```php
interface OrderEvent
{
}

final readonly class OrderCancelled implements OrderEvent
{
    public function __construct(public int $orderId)
    {
    }
}

$listeners->addListener(OrderEvent::class, static function (OrderEvent $event) use ($logger): void {
    $logger->info('Order event {type}', ['type' => $event::class]);
});
```

A listener runs once per dispatch, even when the event reaches its type along
two paths. Priorities apply across the hierarchy: a listener for the interface
and a listener for the class are ordered by priority, then by registration.

## Group handlers in a subscriber

```php
use Solo\EventDispatcher\EventSubscriberInterface;

final class OrderSubscriber implements EventSubscriberInterface
{
    public function __construct(private Mailer $mailer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlaced::class => 'onPlaced',
            OrderCancelled::class => [['refund', 10], ['notify']],
        ];
    }

    public function onPlaced(OrderPlaced $event): void
    {
        $this->mailer->sendConfirmation($event);
    }

    public function refund(OrderCancelled $event): void
    {
        // ...
    }

    public function notify(OrderCancelled $event): void
    {
        // ...
    }
}

$listeners->addSubscriber(new OrderSubscriber($mailer));
```

Each event maps to a method name, a `['method', priority]` pair or a list of
pairs. The methods must be public. A subscriber with an invalid entry throws
`InvalidConfigurationException` and registers none of its methods.

## Stop propagation

```php
use Solo\EventDispatcher\AbstractStoppableEvent;

final class CheckoutStarted extends AbstractStoppableEvent
{
    public ?string $rejection = null;

    /** @param list<string> $items */
    public function __construct(public readonly array $items)
    {
    }
}

$listeners->addListener(CheckoutStarted::class, static function (CheckoutStarted $event): void {
    if ($event->items === []) {
        $event->rejection = 'The cart is empty.';
        $event->stopPropagation();
    }
}, priority: 100);
```

After `stopPropagation()`, no later listener runs. An event with a base class
of its own can implement `Psr\EventDispatcher\StoppableEventInterface` instead.

## Handle failures

An exception thrown by a listener stops the dispatch and reaches the caller of
`dispatch()` unchanged; the remaining listeners do not run. Give the dispatcher
a PSR-3 logger to record which listener failed:

```php
$dispatcher = new EventDispatcher($listeners, $logger);
```

A listener whose failure must not stop the others, such as a notification,
catches its own exceptions:

```php
$listeners->addListener(OrderPlaced::class, static function (OrderPlaced $event) use ($mailer, $logger): void {
    try {
        $mailer->sendConfirmation($event);
    } catch (MailerException $exception) {
        $logger->warning('Order confirmation not sent', ['exception' => $exception]);
    }
});
```

## Wire it into an application

Create one `ListenerProvider` and one `EventDispatcher` as shared services, and
inject `Psr\EventDispatcher\EventDispatcherInterface` where events are
dispatched. A listener can resolve its service from the container when the
event arrives, so that building the dispatcher constructs no listener services:

```php
$listeners->addListener(OrderPlaced::class, static function (OrderPlaced $event) use ($container): void {
    $container->get(StockReservation::class)->reserve($event->orderId);
});
```

Resolving at dispatch time also breaks construction cycles: the service a
listener calls may itself depend on the dispatcher. Listeners added to the
shared provider later, by a test for instance, take part in the next dispatch.

In the source checkout, `examples/listeners.php`, `examples/subscribers.php`
and `examples/failures.php` run after `composer install`.

## Further reading

- [Public API and contracts](api.md)
- [Behavior in detail](details.md)
- [Upgrading from 1.x](https://github.com/SoloPHP/Event-Dispatcher/blob/main/UPGRADE.md)
