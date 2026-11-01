# Changelog

## 2.0.0 — 2026-11-01

Strict PSR-14: a listener failure stops the dispatch and reaches the caller, whether or not a logger is configured. Registration checks event types and subscriptions when they are added, and the array-configuration factory is gone. See the [upgrade guide](UPGRADE.md).

### Breaking changes

- **Listener failures propagate with a logger too.** The logger records the failure, then the dispatcher rethrows the same exception and runs no further listener. In 1.x a logger turned failures into log records, and the dispatch went on.
- **`EventDispatcherFactory` is removed**, with its array configuration of listeners and subscribers. Register them on `ListenerProvider`.
- **Log record.** `exception` holds the `Throwable` instead of its class name; `file` and `line` are gone, being in the exception; the message is `Event listener failed: {message}`; `listener` names subscriber methods, first-class callables and closures (`Closure at file:line`) instead of `Closure`.
- **Order across types.** Listeners with equal priority run in registration order, whether they were registered for the event's class, a parent class or an interface. 1.x ran the class's own listeners first.
- **Registration is checked.** `addListener()`, `addSubscriber()` and `hasListenersFor()` throw `InvalidConfigurationException` for a type that is not an existing class or interface. A subscription must be `'method'`, `['method', priority]` or a list of such pairs naming public methods, and a subscriber with an invalid entry registers nothing.
- **Subscriber listeners** are returned by `getListenersForEvent()` as `[$subscriber, 'method']` callables.
- **Stopped events.** An event that is already stopped is returned without asking the provider for listeners, and a lazy provider is not asked for another listener once the event is stopped.

### Added

- [Documentation](https://solophp.github.io/Event-Dispatcher/), and runnable examples in `examples/`.
- `psr/event-dispatcher-implementation` in `provide`, for packages that require a PSR-14 implementation.
- Generic types: `dispatch()` returns the type of its event, and `addListener()` checks the listener against the event class.

### Fixed

- A logger that throws no longer replaces the listener's exception.
- With a logger, a listener that cannot be called fails with its own error instead of a `TypeError` from the failure record.
- Class aliases and differently cased class names match the event's class.

## 1.2.1 — 2026-05-12

- Listener and subscriber configuration share one parser; resolved listeners are cached per event class.

## 1.2.0 — 2026-03-06

- Optional PSR-3 logger: a failing listener is logged, and the remaining listeners still run.

## 1.1.0 — 2025-12-26

- `EventDispatcherFactory` moves to `Solo\EventDispatcher` and validates its configuration, throwing `InvalidConfigurationException`.
- `hasListenersFor()` counts listeners for parent classes and interfaces.
- `EventDispatcher` and `ListenerProvider` are final.

## 1.0.0 — 2025-08-10

First release.
