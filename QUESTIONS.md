## Architecture & DDD

### Q1: Why did you choose this folder structure?

**Answer:** I separated the code into three layers — Domain, Application, and Infrastructure — following Hexagonal Architecture (Ports & Adapters). The Domain layer contains all business logic with zero external dependencies. The Application layer orchestrates use cases. The Infrastructure layer provides concrete implementations. This means I can swap out the database, the framework, or the HTTP layer without touching any business logic.

---

### Q2: What is the difference between an Entity and a Value Object?

**Answer:** An **Entity** has identity — two Users with the same name are still different users because they have different IDs. A **Value Object** has no identity — it's compared by its properties. Two `Email("alice@example.com")` instances are interchangeable. Value Objects are immutable (using PHP 8.2's `readonly class`), which makes them safe to pass around without defensive copying.

---

### Q3: Why use interfaces for your repositories?

**Answer:** This is the Dependency Inversion Principle — the "D" in SOLID. My `UserService` depends on `UserRepositoryInterface`, not `InMemoryUserRepository`. This gives me three benefits:
1. **Testability** — I can mock the interface in unit tests
2. **Swappability** — Switch from in-memory to MySQL without changing any Application or Domain code  
3. **Decoupling** — The Domain defines *what* it needs; Infrastructure decides *how*

---

### Q4: Why is the Repository interface in the Domain layer, not Infrastructure?

**Answer:** Because the Domain *owns* the contract. The Domain says "I need something that can `findById` and `save`" — that's a business need. The Infrastructure *implements* that contract. If the interface lived in Infrastructure, the Domain would depend on Infrastructure, violating the Dependency Rule.

---

### Q5: What's the difference between your Application Service and Domain Entity?

**Answer:** The Entity owns **business rules** (e.g., "only active users can be suspended", "cannot publish without a description"). The Application Service owns **use case orchestration** (e.g., "look up the user, call suspend, save"). The service is intentionally thin — it coordinates but doesn't contain business logic. This keeps the domain testable in isolation.

---

### Q6: Why `final class` on everything?

**Answer:** Composition over inheritance. Marking classes as `final` prevents accidental extension and forces consumers to use composition (wrapping/decorating) instead. It also signals that the class was designed as a leaf — it's not meant to be a base class.

---

### Q7: What is an Aggregate Root?

**Answer:** An Aggregate is a cluster of objects treated as a single unit for data changes. The **Aggregate Root** is the entry point — all modifications go through it. In my code, `Asset` is an aggregate root that owns its `tags[]`. You never modify tags directly — you go through `$asset->addTag()`. This ensures the invariant "max 20 tags" is always enforced. Other aggregates reference it only by ID (`AssetId`), never by holding a direct object reference.

---

## PHP-Specific Questions

### Q8: Why `declare(strict_types=1)` on every file?

**Answer:** Without it, PHP silently coerces types: `functionExpectingInt("42")` would work. With strict types, that throws a `TypeError`. For a DDD system where type safety matters, this prevents entire categories of bugs. It's the PHP equivalent of going from Java's autoboxing gotchas to explicit type boundaries.

---

### Q9: Why use backed enums instead of string constants?

**Answer:** Backed enums (PHP 8.1) give me:
- **Type safety** — a function accepting `UserStatus` can only receive valid values
- **Exhaustiveness** — `match()` expressions warn if I forget a case
- **Domain logic on the type** — `UserStatus::Active->isAllowedToLogin()` keeps behavior close to data
- **Built-in serialization** — `->value` gives me the string for JSON/DB storage, `::from()` gives me the enum back

String constants give you none of these.

---

### Q10: What is `readonly` and when would you NOT use it?

**Answer:** PHP 8.1's `readonly` prevents a property from being modified after initialization — making the object immutable. I use it on Value Objects (immutability is a core DDD property) and on injected dependencies (a service's repository should never change). I would **not** use `readonly class` on Entities because their state changes through domain methods (`$user->rename()` mutates `$this->name`).

---

### Q11: Why `empty()` is dangerous in PHP?

**Answer:** `empty()` returns `true` for `'0'`, `0`, `false`, `null`, `''`, and `[]`. So `empty($name)` would reject the string `'0'` as a valid name. I use `!isset($data['name']) || trim($data['name']) === ''` instead — explicit about what "empty" means in my context. This is a classic PHP gotcha that catches many developers.

---

## Testing Questions

### Q12: How does Dependency Injection help with testing?

**Answer:** Because `UserService` accepts `UserRepositoryInterface` in its constructor, I can inject a mock in my tests: `$this->createMock(UserRepositoryInterface::class)`. This lets me test the service in **complete isolation** — no database, no filesystem, no network. I control exactly what the repository returns and verify exactly what methods were called. Without DI, I'd need a real database for every test.

---

### Q13: What's the difference between your Unit and Integration tests?

**Answer:**
- **Unit tests** mock the repository interface and test a single class in isolation. They run in microseconds.
- **Integration tests** use the real `InMemoryUserRepository` and test the full stack (Service → Entity → Repository) working together. They verify the layers integrate correctly.

Both run without a database. If I added MySQL, I'd add a third level — **functional tests** — that hit the real database.

---

### Q14: Why test Value Objects at all? They're so simple.

**Answer:** Because they enforce invariants. `UserId(-1)` should throw. `Email("not-an-email")` should throw. `FileSize(200MB)` should throw. If I refactor these classes and a bug slips in, my tests catch it immediately. Value Object tests are cheap (milliseconds) and protect the foundation of the entire domain model. The cost of writing them is negligible compared to the cost of a bug in validation logic.

---

### Q15: How would you test a real database repository?

**Answer:** I'd write integration tests that:
1. Start a transaction in `setUp()`
2. Seed test data
3. Run the test
4. Rollback the transaction in `tearDown()`

This keeps tests isolated and idempotent. With Docker, I'd spin up a test database in CI. The test would interact with `MySqlUserRepository` which implements the same `UserRepositoryInterface` — proving the real adapter fulfills the contract.

---

## Design & Scaling Questions

### Q16: How do you handle ACID transactions?

**Answer:** I use Doctrine ORM, which implements the Unit of Work pattern internally. All entity changes are tracked in memory and written to the database in a single transaction on `flush()`. For explicit transaction control, I use `EntityManager::transactional()` — it auto-commits on success and auto-rollbacks on exception. The key architectural point: the Domain layer has **zero knowledge** of transactions. Transactions are an Infrastructure concern, handled by the Doctrine adapter. Switching from InMemory to Doctrine repos requires changing one line in `config/container.php`.

---

### Q17: How would you handle file uploads in a real DAM?

**Answer:** I'd separate the concerns:
1. **Domain**: The `Asset` entity stays as-is — it tracks metadata (file name, size, MIME type), not the binary content
2. **Application**: An `UploadAssetUseCase` would coordinate: validate metadata → store the file → create the entity → save to DB
3. **Infrastructure**: A `StorageInterface` (Port) with implementations like `S3Storage`, `LocalDiskStorage`. The controller sends the file stream to the storage adapter, gets back a path/URL, and stores that reference on the Asset.

The binary content never touches the Domain layer.

---

### Q18: How would you handle permissions / authorization?

**Answer:** I'd keep it out of the Domain (it's a cross-cutting concern): 
- A **middleware** or **decorator** on the Application Service that checks permissions before invoking the use case
- Or a **Policy** pattern: `AssetPolicy::canPublish(User $user, Asset $asset): bool`
- The Domain stays focused on business rules ("can this asset be published?"), not access rules ("can this user publish it?")

---

### Q19: What would you add with more time?

**Answer:**
- **CQRS** — Separate read models for list/search queries vs. write models for commands
- **Pagination** — `findAll()` returning everything doesn't scale; add cursor-based pagination
- **Specifications** — For complex query filtering (e.g., "find all published image assets in the Marketing folder tagged 'hero'")
- **UUID-based IDs** — The current `nextId()` has race conditions under concurrent access; UUIDs would solve this
- **Event bus** — Replace the simple dispatcher with an async queue (RabbitMQ, Redis Streams) for event handlers that shouldn't block the request

---

### Q20: How does this scale to microservices?

**Answer:** Each bounded context (Users, Assets, Folders) already has its own Service, Repository, and Entity. To split into microservices:
1. Each bounded context becomes its own service with its own database
2. Cross-context references use IDs, not object references (we already do this — `Asset` references `UserId` and `FolderId`, not the actual `User` or `Folder` objects)
3. Communication between services uses async events or synchronous API calls
4. The Domain layer moves unchanged into the microservice — that's the whole point of the architecture

---

## Rapid-Fire (Short Answer)

| Question | Answer |
|----------|--------|
| SOLID — which principles does this project demonstrate? | All five: **S** (middleware handles errors, controllers handle HTTP), **O** (add event handlers without modifying entities), **L** (Doctrine and InMemory repos are interchangeable), **I** (small repository interfaces), **D** (services depend on interfaces, not implementations). |
| What's the difference between `DomainException` and `InvalidArgumentException`? | `DomainException` = business rule violation (e.g., "can't suspend inactive user"). `InvalidArgumentException` = bad input data (e.g., empty name, invalid email). The error middleware maps them to 422 but they serve different semantic roles. |
| Why `DateTimeImmutable` instead of `DateTime`? | `DateTime` is mutable — someone could call `$date->modify('+1 day')` and change your entity's state without going through a domain method. `DateTimeImmutable` prevents this. |
| What pattern is `UserNotFoundException::withId(42)`? | Named Constructor (Static Factory Method) — more expressive than `new UserNotFoundException("...")`. |
| Why does `toArray()` live on the Entity? | Pragmatic shortcut. In a larger system, I'd extract it into a `Mapper` or `Presenter` in the Application layer to keep the Entity free of serialization concerns. |
| What is idempotency and where do you use it? | An operation that produces the same result no matter how many times you call it. `addTag("photo")` on an asset that already has that tag is a no-op — no exception, no duplicate, no `updatedAt` change. |

---

## New Enterprise Feature Questions

### Q21: Explain your Domain Events implementation.

**Answer:** Entities use an `EventRecordingTrait` that collects events in an in-memory array during state transitions. When `Asset::publish()` runs, it records an `AssetPublished` event. After the service persists the entity, it calls `$asset->pullDomainEvents()` — which returns and **clears** the queue (preventing double-dispatch). Events are then routed to handlers via `EventDispatcherInterface`. The key design: events are dispatched AFTER persistence succeeds, so handlers never react to changes that failed to save.

---

### Q22: Why use a centralized error middleware instead of try/catch in controllers?

**Answer:** Single Responsibility Principle. Before the middleware, every controller method had the same try/catch boilerplate — 67 lines of repetitive code across 3 controllers. The middleware maps exception types to HTTP status codes in a declarative constant map. Adding a new exception type is one line. Controllers are now pure happy-path code — they don't know or care about error handling.

---

### Q23: How does your DI Container demonstrate Dependency Inversion?

**Answer:** `config/container.php` is the only file in the project that knows about concrete implementations. Every service and controller depends on interfaces. To switch from in-memory to Doctrine, I change 3 lines in the container config — zero changes in Domain or Application code. The container IS the Composition Root, the single place where the dependency graph is wired.

---

### Q24: What's the difference between `pullDomainEvents()` and `peekDomainEvents()`?

**Answer:** `pullDomainEvents()` returns events and **clears the queue** — this is what you use in production to ensure each event is dispatched exactly once. `peekDomainEvents()` returns events **without clearing** — this is for testing and debugging, when you want to inspect what happened without affecting dispatch semantics. This distinction prevents a common bug: accidentally dispatching events twice when checking them before saving.

---

### Q25: How does PHP handle threads and concurrency? Is it blocking or non-blocking?

**Answer:** PHP uses a **shared-nothing, process-per-request** model. Each HTTP request gets its own process, runs synchronously from top to bottom, blocks on every I/O call (database, file, network), and dies when done. There's no event loop, no threads, no `async/await`.

This gives PHP two major advantages over threaded/async models:
1. **No race conditions** — each request is completely isolated, there's no shared memory between processes.
2. **No memory leaks** — the process dies after each request, so everything is garbage-collected automatically.

Concurrency is handled at the **web server level** by Nginx + PHP-FPM (FastCGI Process Manager), which maintains a pool of worker processes (typically 10-50). Nginx routes incoming requests to available workers in parallel.

For our DAM system, this is ideal — each API call is a short-lived operation: read from database, apply business rules, write back, respond. This is exactly what PHP-FPM is optimized for.

If I needed real-time features (WebSockets, long-polling), I'd look at **Swoole** or **ReactPHP** — they provide an event loop similar to Node.js. PHP 8.1 also introduced **Fibers** for cooperative multitasking, which frameworks like Laravel Octane use internally to keep the application in memory between requests.

---

### Q26: This system handles 100 assets. How would you scale it to 1 million?

**Answer:** Five changes, zero domain code modifications:

1. **Database**: Replace SQLite with PostgreSQL + read replicas. Connection pooling via PgBouncer. Migrate tags from JSON column to a junction table with indexes.
2. **Storage**: Move binaries to S3 with CloudFront CDN. Use pre-signed URLs — never proxy files through the API. Content-addressed storage (SHA-256) for deduplication.
3. **Async processing**: Replace `SimpleEventDispatcher` with `SqsEventDispatcher` — one line in the DI container. Thumbnail generation, search indexing, and notifications run as background workers consuming from SQS.
4. **Caching**: Redis for frequently-read data (asset metadata, folder trees). Cache invalidation via our existing Domain Events pattern — when `AssetPublished` fires, a handler invalidates the cache.
5. **Search**: Replace LIKE queries with Elasticsearch. Async indexing via SQS workers. Supports faceted search, fuzzy matching, and sub-50ms queries across millions of documents.

The architecture was designed for this: every infrastructure dependency is behind an interface. The Domain layer doesn't know whether it's talking to SQLite or PostgreSQL, local disk or S3, synchronous or async dispatch.

---

### Q27: How do you ensure observability in production?

**Answer:** I follow Google's Four Golden Signals — Latency, Traffic, Errors, Saturation:

1. **Structured logging**: JSON logs with correlation IDs (`X-Request-ID`). Every log line includes request context — when a request fails at 3 AM, I can trace the entire flow with one ID.
2. **Distributed tracing**: OpenTelemetry SDK instruments every request across API → Database → S3 → SQS. Export to Jaeger or Datadog for flame graphs showing exactly where time is spent.
3. **Metrics + dashboards**: Prometheus exports PHP-FPM metrics (active workers, request duration, queue depth). Grafana dashboards for real-time visibility.
4. **Alerting**: PagerDuty for P1 (API down), Slack for P3 (slow queries). Alert on symptoms (error rate > 1%), not causes — let engineers investigate root causes.

See `docs/PRODUCTION.md` for the full production architecture.
