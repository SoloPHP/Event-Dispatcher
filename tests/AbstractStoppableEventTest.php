<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Fixture\StoppableTestEvent;

final class AbstractStoppableEventTest extends TestCase
{
    public function testIsPropagationStoppedReturnsFalseByDefault(): void
    {
        $event = new StoppableTestEvent();

        self::assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagationStopsEvent(): void
    {
        $event = new StoppableTestEvent();
        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }
}
