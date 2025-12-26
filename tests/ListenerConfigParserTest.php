<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Solo\EventDispatcher\Exception\InvalidConfigurationException;
use Solo\EventDispatcher\Internal\ListenerConfigParser;

final class ListenerConfigParserTest extends TestCase
{
    public function testParseSubscriberConfigWithStringMethod(): void
    {
        $result = ListenerConfigParser::parseSubscriberConfig('Event', 'onEvent');

        self::assertSame([['method' => 'onEvent', 'priority' => 0]], $result);
    }

    public function testParseSubscriberConfigWithMethodAndPriority(): void
    {
        $result = ListenerConfigParser::parseSubscriberConfig('Event', ['onEvent', 10]);

        self::assertSame([['method' => 'onEvent', 'priority' => 10]], $result);
    }

    public function testParseSubscriberConfigWithMethodOnly(): void
    {
        $result = ListenerConfigParser::parseSubscriberConfig('Event', ['onEvent']);

        self::assertSame([['method' => 'onEvent', 'priority' => 0]], $result);
    }

    public function testParseSubscriberConfigWithMultipleMethods(): void
    {
        $result = ListenerConfigParser::parseSubscriberConfig('Event', [
            ['first', 10],
            ['second', 5],
        ]);

        self::assertSame([
            ['method' => 'first', 'priority' => 10],
            ['method' => 'second', 'priority' => 5],
        ], $result);
    }

    public function testParseSubscriberConfigThrowsForInvalidType(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expected string or array');

        ListenerConfigParser::parseSubscriberConfig('Event', 123);
    }

    public function testParseSubscriberConfigThrowsForInvalidEntryType(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expected [method, priority?]');

        ListenerConfigParser::parseSubscriberConfig('Event', [
            123,
        ]);
    }

    public function testParseSubscriberConfigThrowsForNonStringMethod(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('method name must be a string');

        ListenerConfigParser::parseSubscriberConfig('Event', [
            [123, 10],
        ]);
    }

    public function testParseSubscriberConfigThrowsForNonIntPriority(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('priority must be an integer');

        ListenerConfigParser::parseSubscriberConfig('Event', [
            ['method', 'not_int'],
        ]);
    }

    public function testParseListenerConfigWithCallable(): void
    {
        $callable = fn () => null;
        $result = ListenerConfigParser::parseListenerConfig('Event', $callable);

        self::assertCount(1, $result);
        self::assertSame($callable, $result[0]['callable']);
        self::assertSame(0, $result[0]['priority']);
    }

    public function testParseListenerConfigWithCallableAndPriority(): void
    {
        $callable = fn () => null;
        $result = ListenerConfigParser::parseListenerConfig('Event', [$callable, 10]);

        self::assertCount(1, $result);
        self::assertSame($callable, $result[0]['callable']);
        self::assertSame(10, $result[0]['priority']);
    }

    public function testParseListenerConfigWithMultipleCallables(): void
    {
        $callable1 = fn () => 'first';
        $callable2 = fn () => 'second';

        $result = ListenerConfigParser::parseListenerConfig('Event', [
            [$callable1, 10],
            [$callable2, 5],
        ]);

        self::assertCount(2, $result);
        self::assertSame($callable1, $result[0]['callable']);
        self::assertSame(10, $result[0]['priority']);
        self::assertSame($callable2, $result[1]['callable']);
        self::assertSame(5, $result[1]['priority']);
    }

    public function testParseListenerConfigThrowsForInvalidType(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expected callable or array');

        ListenerConfigParser::parseListenerConfig('Event', 'not_callable');
    }

    public function testParseListenerConfigThrowsForNonCallableEntry(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('first element must be callable');

        ListenerConfigParser::parseListenerConfig('Event', [
            ['not_callable', 10],
        ]);
    }

    public function testParseListenerConfigThrowsForNonIntPriority(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('priority must be an integer');

        ListenerConfigParser::parseListenerConfig('Event', [
            [fn () => null, 'not_int'],
        ]);
    }

    public function testParseListenerConfigThrowsForNonArrayEntryInMultiple(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expected [callable, priority?]');

        ListenerConfigParser::parseListenerConfig('Event', [
            [fn () => null, 10],
            'not_an_array',
        ]);
    }
}
