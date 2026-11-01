---
layout: home

hero:
  name: Solo Event Dispatcher
  text: Plain events. Predictable listeners.
  tagline: A PSR-14 event dispatcher with priorities, subscribers and listeners matched by class, parent class or interface.
  image:
    src: /logo.svg
    alt: Solo Event Dispatcher
  actions:
    - theme: brand
      text: Get started
      link: /guide/
    - theme: alt
      text: API reference
      link: /guide/api

features:
  - icon: 📮
    title: PSR-14 throughout
    details: EventDispatcher and ListenerProvider implement PSR-14. The dispatcher accepts any PSR-14 provider, and application code depends on EventDispatcherInterface alone.
  - icon: 🔢
    title: Deterministic order
    details: Higher priorities run first. Equal priorities run in registration order, wherever in the type hierarchy a listener was registered.
  - icon: 🧬
    title: Type hierarchy
    details: A listener for a class or an interface receives every subclass and implementation, once, including anonymous classes, class aliases and names in another letter case.
  - icon: 🗂️
    title: Subscribers
    details: One object declares the methods that handle each event. A subscriber with an invalid entry throws and registers nothing.
  - icon: ✋
    title: Stoppable events
    details: Stop propagation with AbstractStoppableEvent or your own StoppableEventInterface. A stopped event never advances a lazy provider.
  - icon: 🚨
    title: Failures you can see
    details: A listener exception stops the dispatch and reaches the caller unchanged. An optional PSR-3 logger records which listener failed, for which event.
---

<style>
:root {
  --vp-home-hero-name-color: transparent;
  --vp-home-hero-name-background: linear-gradient(135deg, #f59e0b 0%, #ec4899 100%);
  --vp-home-hero-image-background-image: linear-gradient(135deg, #f59e0b30 0%, #ec489930 100%);
  --vp-home-hero-image-filter: blur(44px);
}

.VPHero .VPImage {
  max-width: 200px;
  max-height: 200px;
}
</style>

## Register and dispatch

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
$event = $dispatcher->dispatch(new OrderPlaced(42)); // the same object
```

An event is any object, and a listener is any callable that accepts it. Read the
[getting-started guide](./guide/index.md) and the [API reference](./guide/api.md)
for subscribers, stoppable events, failures and container wiring.

## Installation

PHP **8.3+** is required. Composer installs `psr/event-dispatcher` **^1.0** and
`psr/log` **^3.0** with the package.

```sh
composer require solophp/event-dispatcher
```

Continue with the [getting-started guide](./guide/index.md).
