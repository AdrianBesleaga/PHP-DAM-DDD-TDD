# ADR-003: Domain Events with Post-Persist Dispatch

## Status
Accepted

## Context
When an asset is published, multiple side effects should occur (logging, notifications, search index updates). We needed a decoupled mechanism that:
- Doesn't pollute entity methods with side-effect logic
- Ensures side effects only run after successful persistence
- Keeps the Domain layer free of infrastructure concerns

## Decision
We implemented a three-part event system:

1. **Recording** — Entities use `EventRecordingTrait` to collect events in an in-memory array during state transitions
2. **Pulling** — After `save()`, the Application Service calls `$entity->pullDomainEvents()` which returns and clears the queue
3. **Dispatching** — Events are routed to handlers via `EventDispatcherInterface` (defined in Domain, implemented in Infrastructure)

Critical design choice: events are dispatched **AFTER** persistence succeeds.

```
Entity.publish() → records event → Service.save() → Service.dispatch(events)
```

If `save()` throws, no events are dispatched — preventing handlers from reacting to changes that were never persisted.

## Consequences

**Positive:**
- Entities remain pure — they record facts, not trigger effects
- `pullDomainEvents()` prevents double-dispatch (queue is cleared on read)
- New handlers can be added without modifying entities or services
- `peekDomainEvents()` enables test assertions without affecting dispatch

**Negative:**
- Events are synchronous — a slow handler blocks the HTTP response
- No event persistence — if dispatch fails after save, the event is lost
- Future: upgrade to async event bus (RabbitMQ, Symfony Messenger) for production
