# ADR-004: Centralized Error Middleware

## Status
Accepted

## Context
Before this decision, every controller method had repetitive try/catch blocks:

```php
try {
    $user = $this->userService->getUserById($id);
    return $this->jsonResponse($response, $user->toArray());
} catch (UserNotFoundException $e) {
    return $this->errorResponse($response, $e->getMessage(), 404);
} catch (\InvalidArgumentException $e) {
    return $this->errorResponse($response, $e->getMessage(), 422);
} catch (\Throwable $e) {
    return $this->errorResponse($response, 'Internal error', 500);
}
```

This was ~67 lines of duplicated error handling across 3 controllers.

## Decision
We moved all error handling to a single `JsonErrorMiddleware` that wraps the entire application pipeline. It uses a declarative exception-to-HTTP-status map:

```php
private const EXCEPTION_STATUS_MAP = [
    UserNotFoundException::class => 404,
    DuplicateEmailException::class => 409,
    InvalidAssetTransitionException::class => 422,
    // ...
];
```

Controllers now contain only happy-path code — they throw domain exceptions and the middleware converts them to appropriate HTTP responses.

## Consequences

**Positive:**
- Controllers reduced to pure happy-path logic (Single Responsibility)
- Adding a new exception type requires one line in the status map
- Consistent error response format across all endpoints
- Debug mode toggles stack traces (off in production)

**Negative:**
- All exceptions must be catchable by type — no context-specific error handling per route
- Custom response bodies per exception type would require extension
