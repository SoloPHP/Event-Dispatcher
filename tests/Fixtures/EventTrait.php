<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Tests\Fixtures;

/** A trait is neither a class nor an interface, so it cannot be an event type. */
trait EventTrait // @phpstan-ignore trait.unused
{
}
