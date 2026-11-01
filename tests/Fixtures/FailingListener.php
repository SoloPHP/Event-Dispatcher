<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Tests\Fixtures;

final class FailingListener
{
    public function __invoke(object $event): never
    {
        throw new \RuntimeException('Listener failed.');
    }

    public function handle(object $event): never
    {
        throw new \RuntimeException('Listener failed.');
    }

    public static function handleStatically(object $event): never
    {
        throw new \RuntimeException('Listener failed.');
    }
}
