<?php

declare(strict_types=1);

// Run from the repository after composer install: php examples/failures.php
require dirname(__DIR__) . '/vendor/autoload.php';

use Psr\Log\AbstractLogger;
use Solo\EventDispatcher\EventDispatcher;
use Solo\EventDispatcher\ListenerProvider;

final readonly class OrderShipped
{
    public function __construct(public int $orderId)
    {
    }
}

// Prints each record; an application passes its own PSR-3 logger.
final class EchoLogger extends AbstractLogger
{
    /** @param array<mixed> $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $line = str_replace('{message}', (string) ($context['message'] ?? ''), (string) $message);
        if (isset($context['listener'])) {
            $line .= ' in ' . $context['listener'];
        }

        echo "  [{$level}] {$line}" . PHP_EOL;
    }
}

$logger = new EchoLogger();

// A listener whose failure must not stop the others catches its own exceptions.
$optional = static fn (callable $listener): \Closure => static function (object $event) use ($listener, $logger): void {
    try {
        $listener($event);
    } catch (\Throwable $exception) {
        $logger->warning('Optional listener failed: {message}', ['message' => $exception->getMessage()]);
    }
};

$listeners = new ListenerProvider();
$listeners->addListener(OrderShipped::class, $optional(static function (): void {
    throw new RuntimeException('SMS gateway timeout');
}), priority: 20);
$listeners->addListener(OrderShipped::class, static function (OrderShipped $event): void {
    echo "  stock released for order {$event->orderId}" . PHP_EOL;
}, priority: 10);
$listeners->addListener(OrderShipped::class, static function (OrderShipped $event): void {
    throw new LogicException("Order {$event->orderId} has no invoice");
});
$listeners->addListener(OrderShipped::class, static function (): void {
    echo '  never reached' . PHP_EOL;
}, priority: -10);

$dispatcher = new EventDispatcher($listeners, $logger);

try {
    $dispatcher->dispatch(new OrderShipped(42));
} catch (LogicException $exception) {
    echo 'Caller caught: ' . $exception->getMessage() . PHP_EOL;
}
