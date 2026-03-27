# ADR-001: Hexagonal Architecture (Ports & Adapters)

## Status
Accepted

## Context
We needed an architecture for a Digital Asset Management system that:
- Keeps business logic isolated from frameworks, databases, and HTTP
- Allows easy testing without infrastructure dependencies
- Enables swapping implementations (e.g., InMemory → Doctrine) without code changes
- Supports multiple entry points (REST + GraphQL) sharing the same domain

MVC was considered but rejected because it couples business logic to the HTTP layer.

## Decision
We chose **Hexagonal Architecture** with three layers:

```
Infrastructure → Application → Domain (core)
```

- **Domain**: Entities, Value Objects, Repository Interfaces (Ports), Events
- **Application**: Services (use cases), DTOs, Event Handlers
- **Infrastructure**: Doctrine repos (Adapters), HTTP Controllers, Middleware

The Dependency Rule: dependencies always point inward. The Domain layer has zero external dependencies.

## Consequences

**Positive:**
- REST and GraphQL controllers reuse the same Application Services — zero duplication
- Unit tests run in milliseconds with no database
- Swapping InMemory repos to Doctrine repos required changing 3 lines in the DI container

**Negative:**
- More files and directories than a simple MVC app
- New developers need to understand the layer boundaries
- Simple CRUD operations require touching 3+ files
