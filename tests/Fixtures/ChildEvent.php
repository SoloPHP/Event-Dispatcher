<?php

declare(strict_types=1);

namespace Solo\EventDispatcher\Tests\Fixtures;

/** Reaches MarkerInterface twice: through ParentEvent and through ExtendedMarkerInterface. */
final class ChildEvent extends ParentEvent implements ExtendedMarkerInterface
{
}
