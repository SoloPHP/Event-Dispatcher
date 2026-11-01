# Solo Event Dispatcher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/event-dispatcher.svg)](https://packagist.org/packages/solophp/event-dispatcher)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

PSR-14 event dispatcher for PHP. Listeners run by priority, then in registration order; a listener for a class or an interface receives its subtypes; subscribers group the handlers of one object. A listener failure reaches the caller unchanged, and an optional PSR-3 logger records which listener failed.

## Installation

```bash
composer require solophp/event-dispatcher
```

Requires PHP 8.3+.

## Example

```php
use Solo\EventDispatcher\EventDispatcher;
use Solo\EventDispatcher\ListenerProvider;

final readonly class OrderPlaced
{
    public function __construct(public int $orderId)
    {
    }
}

$listeners = new ListenerProvider();
$listeners->addListener(OrderPlaced::class, static function (OrderPlaced $event): void {
    echo "Reserving stock for order {$event->orderId}\n";
}, priority: 10);
$listeners->addListener(OrderPlaced::class, static function (OrderPlaced $event): void {
    echo "Sending the confirmation of order {$event->orderId}\n";
});

$dispatcher = new EventDispatcher($listeners);
$dispatcher->dispatch(new OrderPlaced(42)); // returns the same event
```

An event is any object, and a listener is any callable that accepts it. Inject `Psr\EventDispatcher\EventDispatcherInterface` where events are dispatched; a listener can resolve its service from the container when the event arrives, so that building the dispatcher constructs nothing.

## Documentation

**[Full Documentation](https://solophp.github.io/Event-Dispatcher/)**

- [Getting Started](https://solophp.github.io/Event-Dispatcher/guide/)
- [Priorities and Order](https://solophp.github.io/Event-Dispatcher/guide/#priorities-and-order)
- [Event Hierarchies](https://solophp.github.io/Event-Dispatcher/guide/#listen-to-a-family-of-events)
- [Subscribers](https://solophp.github.io/Event-Dispatcher/guide/#group-handlers-in-a-subscriber)
- [Stopping Propagation](https://solophp.github.io/Event-Dispatcher/guide/#stop-propagation)
- [Failures and Logging](https://solophp.github.io/Event-Dispatcher/guide/#handle-failures)
- [Container Wiring](https://solophp.github.io/Event-Dispatcher/guide/#wire-it-into-an-application)
- [API Reference](https://solophp.github.io/Event-Dispatcher/guide/api)
- [Behavior in Detail](https://solophp.github.io/Event-Dispatcher/guide/details)
- [Upgrading from 1.x](UPGRADE.md)
- [Changelog](CHANGELOG.md)

## License

MIT. See [LICENSE](LICENSE).
