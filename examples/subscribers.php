<?php

declare(strict_types=1);

// Run from the repository after composer install: php examples/subscribers.php
require dirname(__DIR__) . '/vendor/autoload.php';

use Solo\EventDispatcher\EventDispatcher;
use Solo\EventDispatcher\EventSubscriberInterface;
use Solo\EventDispatcher\Exception\InvalidConfigurationException;
use Solo\EventDispatcher\ListenerProvider;

final readonly class UserRegistered
{
    public function __construct(public string $email)
    {
    }
}

final readonly class UserDeleted
{
    public function __construct(public string $email)
    {
    }
}

final class UserSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistered::class => [['createProfile', 10], ['sendWelcome']],
            UserDeleted::class => 'eraseProfile',
        ];
    }

    public function createProfile(UserRegistered $event): void
    {
        echo "  profile created for {$event->email}" . PHP_EOL;
    }

    public function sendWelcome(UserRegistered $event): void
    {
        echo "  welcome sent to {$event->email}" . PHP_EOL;
    }

    public function eraseProfile(UserDeleted $event): void
    {
        echo "  profile erased for {$event->email}" . PHP_EOL;
    }
}

// The second entry names a method that does not exist, so no method of this subscriber is registered.
final class MistypedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistered::class => 'audit',
            UserDeleted::class => 'adit',
        ];
    }

    public function audit(object $event): void
    {
        echo '  audit ' . $event::class . PHP_EOL;
    }
}

$listeners = new ListenerProvider();
$listeners->addSubscriber(new UserSubscriber());

try {
    $listeners->addSubscriber(new MistypedSubscriber());
} catch (InvalidConfigurationException $exception) {
    echo 'Rejected: ' . $exception->getMessage() . PHP_EOL;
}

$dispatcher = new EventDispatcher($listeners);

echo 'UserRegistered:' . PHP_EOL;
$dispatcher->dispatch(new UserRegistered('ann@example.com'));
echo 'UserDeleted:' . PHP_EOL;
$dispatcher->dispatch(new UserDeleted('ann@example.com'));
