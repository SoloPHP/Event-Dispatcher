# Solo Event Dispatcher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/event-dispatcher.svg)](https://packagist.org/packages/solophp/event-dispatcher)
[![License](https://img.shields.io/github/license/solophp/event-dispatcher.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/solophp/event-dispatcher.svg)](https://packagist.org/packages/solophp/event-dispatcher)
[![Code Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)]()

Minimal, PSR-14 compatible event dispatcher with priorities, stoppable propagation, and optional error logging.

## Requirements

- PHP 8.3+

## Installation

```bash
composer require solophp/event-dispatcher
```

## Usage

### Adding Listeners Directly

```php
use Solo\EventDispatcher\{EventDispatcher, ListenerProvider};

$provider = new ListenerProvider();

$provider->addListener(
    UserRegistered::class,
    fn (UserRegistered $e) => print "Welcome, {$e->username}!\n",
    priority: 10
);

$dispatcher = new EventDispatcher($provider);
$dispatcher->dispatch(new UserRegistered('john'));
```

### Using Subscribers

```php
use Solo\EventDispatcher\EventSubscriberInterface;

final class WelcomeEmailSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistered::class => ['onUserRegistered', 10],
        ];
    }

    public function onUserRegistered(UserRegistered $event): void
    {
        echo "Welcome, {$event->username}!" . PHP_EOL;
    }
}

$provider = new ListenerProvider();
$provider->addSubscriber(new WelcomeEmailSubscriber());

$dispatcher = new EventDispatcher($provider);
$dispatcher->dispatch(new UserRegistered('john'));
```

### Subscriber Configuration Formats

```php
return [
    EventClass::class => 'methodName',
    EventClass::class => ['methodName', 10],                         // with priority
    EventClass::class => [['firstMethod', 20], ['secondMethod', 0]], // multiple handlers
];
```

### Using the Factory

```php
use Solo\EventDispatcher\EventDispatcherFactory;

$dispatcher = EventDispatcherFactory::create(
    listeners: [
        UserRegistered::class => fn (UserRegistered $e) => print "Welcome!\n",
    ],
    subscribers: [
        new WelcomeEmailSubscriber(),
        WelcomeEmailSubscriber::class,           // or class-string
        fn () => new WelcomeEmailSubscriber(),   // or factory callable
    ],
);
```

### Error Logging

By default, if a listener throws an exception, it bubbles up to the caller. Pass a PSR-3 `LoggerInterface` to catch errors, log them, and continue executing the remaining listeners:

```php
use Solo\EventDispatcher\{EventDispatcher, ListenerProvider};
use Psr\Log\LoggerInterface;

$dispatcher = new EventDispatcher($provider, $logger);

// or via factory
$dispatcher = EventDispatcherFactory::create(
    listeners: [...],
    subscribers: [...],
    logger: $logger,
);
```

When a listener fails, the following context is logged at `error` level:

| Key          | Description                          |
|--------------|--------------------------------------|
| `exception`  | Exception class name                 |
| `message`    | Exception message                    |
| `event`      | Event class name                     |
| `listener`   | Listener description (e.g. `Closure`, `MyClass::onEvent`) |
| `file`       | File where the exception was thrown   |
| `line`       | Line number                          |

### Stoppable Events

```php
use Solo\EventDispatcher\AbstractStoppableEvent;

final class OrderPlaced extends AbstractStoppableEvent
{
    public function __construct(public int $orderId) {}
}

$provider->addListener(OrderPlaced::class, function (OrderPlaced $e) {
    if ($e->orderId < 0) {
        $e->stopPropagation(); // subsequent listeners won't be called
    }
}, priority: 100);

$provider->addListener(OrderPlaced::class, function (OrderPlaced $e) {
    // this won't run if propagation was stopped
});
```

### Checking for Listeners

```php
// checks for listeners including parent classes and interfaces
if ($provider->hasListenersFor(UserRegistered::class)) {
    $dispatcher->dispatch(new UserRegistered('john'));
}
```

## Testing

```bash
composer test        # cs-check + analyze + phpunit
composer cs-check    # PHPCS (PSR-12)
composer cs-fix      # PHPCBF auto-fix
composer analyze     # PHPStan (level 8)
```

## License

MIT