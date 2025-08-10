# Solo Event Dispatcher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/event-dispatcher.svg?style=flat-square)](https://packagist.org/packages/solophp/event-dispatcher)
[![License](https://img.shields.io/github/license/solophp/event-dispatcher.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/solophp/event-dispatcher.svg?style=flat-square)](https://packagist.org/packages/solophp/event-dispatcher)

Minimal, PSR-14 compatible event dispatcher with priorities and stoppable propagation.

Features:
- PSR-14 compliant (`psr/event-dispatcher`)
- Listeners, subscribers, priorities, stoppable events
- Factories for quick setup

## Requirements & Install

```bash
composer require solophp/event-dispatcher
```

## Usage

```php
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Solo\EventDispatcher\{EventDispatcher, ListenerProvider, EventSubscriberInterface};

final class UserRegistered
{
    public function __construct(public string $username) {}
}

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
        // send email
        echo "Welcome, {$event->username}!" . PHP_EOL;
    }
}

$provider = new ListenerProvider(); // implements ListenerProviderInterface
$provider->addSubscriber(new WelcomeEmailSubscriber());

$dispatcher = new EventDispatcher($provider); // implements EventDispatcherInterface
$dispatcher->dispatch(new UserRegistered('john'));
```

### Usage with factory

```php
use Solo\EventDispatcher\Factory\EventDispatcherFactory;

// Build dispatcher via a single call
$dispatcher = EventDispatcherFactory::create(
    listeners: [
        // Map event class to listeners with optional priorities
        UserRegistered::class => [
            [fn (UserRegistered $e) => print "Welcome, {$e->username}!\n", 10],
        ],
    ],
    subscribers: [
        new WelcomeEmailSubscriber(),
        // or provide a factory callable if constructor args are needed:
        // fn () => new WelcomeEmailSubscriber(...)
    ],
);

$dispatcher->dispatch(new UserRegistered('john'));
```

Subscribers map format (supported variants):

```php
return [
  EventClass::class => 'methodName',
  // or with priority
  EventClass::class => ['methodName', 10],
  // or multiple handlers
  EventClass::class => [ ['firstMethod', 20], ['secondMethod', 0] ],
];
```

## Testing

```bash
# Run tests
composer test

# Run code sniffer
composer cs

# Fix code style issues
composer cs-fix
```

## License

This project is open-sourced under the [MIT license](./LICENSE).

