# Behavior in detail

Solo Event Dispatcher calls the listeners of an event in a predictable order, as PSR-14 describes. [Getting started](index.md) shows the setup, and the [public API](api.md) describes the contracts. This page explains how the pieces behave and how the package is tested.

## What is included

`EventDispatcher`, `ListenerProvider`, `EventSubscriberInterface`, `AbstractStoppableEvent` and `InvalidConfigurationException`: priorities with a stable order, matching by class, parent class and interface, subscribers that register all of their methods or none, stoppable events, and an optional PSR-3 record of the listener that failed. The runnable examples need no configuration:

```sh
composer install
php examples/listeners.php
php examples/subscribers.php
php examples/failures.php
composer check
```

## Design decisions

- **PSR-14 first.** The dispatcher accepts any `ListenerProviderInterface`, and application code depends on `Psr\EventDispatcher\EventDispatcherInterface` alone. Another PSR-14 implementation can replace this one in a container definition.
- **Failures propagate.** PSR-14 requires a listener's exception to stop the dispatch and reach the code that dispatched the event. The logger records which listener failed; it does not decide whether the failure matters. A listener that may fail without consequence catches its own exceptions, which keeps that decision next to the code it concerns.
- **One registration API.** Listeners and subscribers are registered through method calls that static analysis checks. There is no array configuration: in arrays, a list of callables, a callable array and a `[callable, priority]` pair cannot be told apart reliably.
- **Mistakes fail at registration.** An unknown event type or a malformed subscription throws when it is registered, at boot, instead of leaving a listener that silently never runs.
- **Order is explicit.** Priority decides first, then registration, wherever in the hierarchy a listener was registered. A listener for the concrete class does not run first by itself: give it a priority.
- **No container dependency.** A lazy listener is a closure that resolves a service when it is called. The package needs no PSR-11 container and knows no service identifiers.

## Structure

```text
src/
├── EventDispatcher.php              Dispatch loop, stop checks and the failure record
├── ListenerProvider.php             Registrations, type matching, order, cache and subscriber parsing
├── EventSubscriberInterface.php     Declaration of a subscriber's methods
├── AbstractStoppableEvent.php       Stop flag for events
└── Exception/
    └── InvalidConfigurationException.php
```

## Order

The provider keeps one list of registrations in the order they were made, each with its event type, listener and priority. For an event class, it takes the registrations whose type the class belongs to and sorts them by descending priority. PHP's sort is stable, so equal priorities keep their registration order, whatever type they were registered for:

```php
$listeners->addListener(OrderEvent::class, $audit);            // 1st, priority 0
$listeners->addListener(OrderPlaced::class, $reserve, 10);     // 2nd, priority 10
$listeners->addListener(OrderPlaced::class, $confirm);         // 3rd, priority 0

// OrderPlaced runs $reserve, $audit, $confirm.
```

Every `int` is a valid priority, `PHP_INT_MIN` and `PHP_INT_MAX` included.

## Type matching

A registration for type `T` matches an event whose class is `T` or a subtype of `T`: a subclass, an implementation, or an implementation of an interface that extends `T`. Matching uses `is_a()`, so:

- a registration matches at most once per event, even when the class reaches an interface through its parent and through another interface;
- class aliases, names in another letter case and a leading backslash match the class they name;
- an anonymous class matches through its parent class and its interfaces;
- a trait is not a type and is rejected at registration.

Event types are checked with `class_exists()` and `interface_exists()` when they are registered, which autoloads them: registering listeners loads the event classes.

## Cache and registration during a dispatch

The listeners of an event class are computed on its first dispatch and kept until the next registration, which clears them for every class. Register listeners at boot: after the first dispatch of a class, finding its listeners is one array lookup.

`getListenersForEvent()` returns an array, and the dispatcher iterates over that array. A listener registered during a dispatch therefore does not run in that dispatch, but from the next one. A listener that dispatches another event starts an independent dispatch, which runs to its end before the outer one continues.

## Stopping

The dispatcher checks a stoppable event before it asks the provider for listeners, and after each listener. An event stopped before the dispatch reaches no listener and does not query the provider. After a listener stops the event, the dispatcher returns without advancing the provider's iterator, so a lazy provider never builds a listener that will not run.

## Failures and logging

The [API reference](api.md#eventdispatcher) states the contract: the listener's exception propagates as the same object, after the log record when a logger is given. A logger that throws is ignored, so that a broken log never hides the failure it was recording.

When the application's error handler logs uncaught exceptions too, a failure is recorded twice. The dispatcher's record adds the event class and the listener, which a stack trace through a closure does not name clearly. Pass a logger when that context helps, and leave it out otherwise.

## Subscribers

`getSubscribedEvents()` is static: the declaration describes the class, and `addSubscriber()` binds it to one instance. The provider checks each method with `is_callable()` from its own scope, so private and protected methods are rejected, and a subscriber with `__call()` accepts any name. Subscriber listeners are `[$subscriber, 'method']` arrays, which keep the class and the method visible in a debugger and in the failure record.

## Testing

`composer check` runs PSR-12 on `src/` and `tests/`, PHPStan at its maximum level on both, and PHPUnit, which covers every line of `src/`. `.github/workflows/tests.yml` runs it on PHP 8.3, 8.4 and 8.5, and on PHP 8.3 with the lowest version of every dependency, then runs the examples. `.github/workflows/docs.yml` builds this site.

## Verified behavior

The test suite checks, among other things:

- order by priority, with negative priorities, `PHP_INT_MIN` and `PHP_INT_MAX`, and by registration within a priority and across types;
- matching by parent class and interface, an interface reached twice, anonymous events, class aliases and letter case;
- rejected event types: a missing class, an empty name, a trait, and subscriptions keyed by list index;
- every subscription form and malformed subscriptions of each kind, with nothing registered after a rejection;
- private, missing and empty subscriber method names;
- an already stopped event, which does not reach the provider, and a lazy provider that is not advanced after a stop;
- exceptions and errors that stop the dispatch and propagate unchanged, with and without a logger, and a logger that fails;
- the listener description for methods, static methods, invokable objects, functions, first-class callables and closures;
- registration during a dispatch, and dispatches nested in a listener.
