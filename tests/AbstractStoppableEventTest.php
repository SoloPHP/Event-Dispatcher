<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Tests;

use PHPUnit\Framework\TestCase;
use Solo\EventDispatcher\Tests\Fixtures\StoppableEvent;

final class AbstractStoppableEventTest extends TestCase
{
    public function testPropagationStopsOnlyWhenRequested(): void
    {
        $event = new StoppableEvent();
        self::assertFalse($event->isPropagationStopped());

        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }
}
