<?php

declare(strict_types=1);

// Run from the repository after composer install: php examples/listeners.php
require dirname(__DIR__) . '/vendor/autoload.php';

use Solo\EventDispatcher\AbstractStoppableEvent;
use Solo\EventDispatcher\EventDispatcher;
use Solo\EventDispatcher\ListenerProvider;

interface OrderEvent
{
}

final readonly class OrderPlaced implements OrderEvent
{
    public function __construct(public int $orderId)
    {
    }
}

final readonly class OrderCancelled implements OrderEvent
{
    public function __construct(public int $orderId)
    {
    }
}

final class CheckoutStarted extends AbstractStoppableEvent
{
    public ?string $rejection = null;

    /** @param list<string> $items */
    public function __construct(public readonly array $items)
    {
    }
}

$listeners = new ListenerProvider();

// Equal priorities run in registration order, whatever type they were registered for.
$listeners->addListener(OrderEvent::class, static function (OrderEvent $event): void {
    echo '  audit ' . $event::class . PHP_EOL;
});
$listeners->addListener(OrderPlaced::class, static function (OrderPlaced $event): void {
    echo "  reserve stock for order {$event->orderId}" . PHP_EOL;
}, priority: 10);
$listeners->addListener(OrderPlaced::class, static function (OrderPlaced $event): void {
    echo "  send the confirmation of order {$event->orderId}" . PHP_EOL;
});

// A listener with a high priority can stop the ones after it.
$listeners->addListener(CheckoutStarted::class, static function (CheckoutStarted $event): void {
    if ($event->items === []) {
        $event->rejection = 'the cart is empty';
        $event->stopPropagation();
    }
}, priority: 100);
$listeners->addListener(CheckoutStarted::class, static function (CheckoutStarted $event): void {
    echo '  charge for ' . count($event->items) . ' item(s)' . PHP_EOL;
});

$dispatcher = new EventDispatcher($listeners);

echo 'OrderPlaced:' . PHP_EOL;
$dispatcher->dispatch(new OrderPlaced(42));
echo 'OrderCancelled:' . PHP_EOL;
$dispatcher->dispatch(new OrderCancelled(42));

foreach ([[], ['book', 'lamp']] as $items) {
    $checkout = $dispatcher->dispatch(new CheckoutStarted($items));
    echo 'Checkout: ' . ($checkout->rejection ?? 'accepted') . PHP_EOL;
}

echo 'Listeners for OrderEvent: ' . var_export($listeners->hasListenersFor(OrderEvent::class), true) . PHP_EOL;
