# PHP DDD-TDD — Digital Asset Management System

A **Digital Asset Management (DAM)** system built with PHP 8.3, demonstrating Domain-Driven Design, Hexagonal Architecture, SOLID principles, ACID transactions, and Test-Driven Development.

```
210 tests, 413 assertions — all passing
```

---

## Architecture

This project follows **DDD-Lite / Hexagonal Architecture** (Ports & Adapters). The core principle is the **Dependency Rule**: dependencies always point inward — outer layers know about inner layers, never the reverse.

```
+--------------------------------------------------------------+
|                    INFRASTRUCTURE LAYER                       |
|                                                               |
|  Controllers       Doctrine Repos       SimpleEventDispatcher |
|  (HTTP Adapters)   (DB Adapters)        (Event Adapter)       |
|  JsonErrorMiddleware   Monolog Logger                         |
|                                                               |
|   +------------------------------------------------------+    |
|   |                  APPLICATION LAYER                    |   |
|   |                                                       |   |
|   |  UserService    AssetService    FolderService         |   |
|   |  (Use Cases)    (Use Cases)     (Use Cases)           |   |
|   |                                                       |   |
|   |  DTOs: CreateUserDTO, UploadAssetDTO, CreateFolderDTO |   |
|   |  EventHandler: LogAssetPublishedHandler               |   |
|   |                                                       |   |
|   |   +----------------------------------------------+    |   |
|   |   |              DOMAIN LAYER (CORE)              |   |   |
|   |   |                                               |   |   |
|   |   |  Entities:  User, Asset, Folder               |   |   |
|   |   |  Value Objects:  UserId, Email, FileName,     |   |   |
|   |   |    FileSize, MimeType, AssetStatus, FolderId  |   |   |
|   |   |  Interfaces:  UserRepositoryInterface,        |   |   |
|   |   |    AssetRepositoryInterface,                  |   |   |
|   |   |    FolderRepositoryInterface,                 |   |   |
|   |   |    EventDispatcherInterface                   |   |   |
|   |   |  Events:  AssetPublished, AssetArchived,      |   |   |
|   |   |    UserSuspended                              |   |   |
|   |   |  Exceptions:  AssetNotFoundException, etc.    |   |   |
|   |   |                                               |   |   |
|   |   |  >>> ZERO external dependencies <<<           |   |   |
|   |   +----------------------------------------------+    |   |
|   +------------------------------------------------------+    |
+--------------------------------------------------------------+
```

---

## The Three Layers

### 1. Domain Layer (`src/Domain/`) — The Core

The Domain layer contains **all business rules** and has **zero dependencies** on frameworks, databases, or HTTP.

| Concept | Files | Purpose |
|---------|-------|---------|
| **Entities** | `User`, `Asset`, `Folder` | Aggregate roots with identity and rich behavior |
| **Value Objects** | `UserId`, `Email`, `FileName`, `FileSize`, `MimeType`, `AssetStatus` | Immutable, self-validating, compared by value |
| **Repository Interfaces** | `*RepositoryInterface` | Ports — define WHAT data access is needed, not HOW |
| **Event Interfaces** | `EventDispatcherInterface`, `DomainEvent` | Ports for event dispatching |
| **Domain Events** | `AssetPublished`, `AssetArchived`, `UserSuspended` | Immutable records of what happened |
| **Exceptions** | `UserNotFoundException`, `InvalidAssetTransitionException`, etc. | Business-level errors with named constructors |

### 2. Application Layer (`src/Application/`) — Use Cases

Orchestrates use cases by coordinating Domain and Infrastructure. Services are intentionally **thin** — all business rules delegate to entities.

| Concept | Files | Purpose |
|---------|-------|---------|
| **Services** | `UserService`, `AssetService`, `FolderService` | One public method per use case |
| **DTOs** | `CreateUserDTO`, `UploadAssetDTO`, `CreateFolderDTO` | Data carriers with factory validation |
| **Event Handlers** | `LogAssetPublishedHandler` | React to domain events (decoupled side effects) |

### 3. Infrastructure Layer (`src/Infrastructure/`) — Adapters

Provides concrete implementations for the Domain's interfaces.

| Concept | Files | Purpose |
|---------|-------|---------|
| **InMemory Repos** | `InMemoryUserRepository`, etc. | Fast adapters for testing |
| **Doctrine Repos** | `DoctrineUserRepository`, etc. | Production ACID adapters |
| **Doctrine Types** | `EmailType`, `AssetStatusType`, `UserStatusType` | Map Value Objects to DB columns |
| **HTTP Controllers** | `UserController`, `AssetController`, `FolderController`, `GraphQLController` | Translate HTTP/GraphQL to Application use cases |
| **Middleware** | `JwtAuthMiddleware`, `CorsMiddleware`, `SecurityHeadersMiddleware`, `JsonErrorMiddleware` | Cross-cutting concerns (auth, security, errors) |
| **Event Dispatcher** | `SimpleEventDispatcher` | Routes events to handlers |

---

## Domain Models

### Asset — Core DAM Entity

The central aggregate root with a full lifecycle state machine:

```
                   publish()              archive()
   +----------+ ------------> +------------+ ------------> +----------+
   |          |               |            |               |          |
   |  DRAFT   |               | PUBLISHED  |               | ARCHIVED |
   |          |               |            |               |          |
   +----------+ <------------ +------------+               +----------+
        ^            (X)                                        |
        |         cannot go                                     |
        |         backwards                                     |
        +---------------------- restoreToDraft() ---------------+
```

**Business rules enforced by the entity:**
- Cannot publish without a description (metadata completeness)
- Cannot skip states (Draft -> Archived is forbidden)
- Max 20 tags per asset, normalized to lowercase
- 100 MB max file size (enforced by `FileSize` Value Object)
- Only allowlisted MIME types accepted
- All operations are idempotent

### Folder — Hierarchy Entity

Organizes assets in a parent-child tree with a self-nesting guard (a folder cannot be its own parent).

### User — Identity Entity

State transitions (`Active` -> `Suspended`) with business rule enforcement. Records `UserSuspended` domain events.

---

## Enterprise Features

### ACID Transactions (Doctrine ORM + SQLite)

Doctrine repositories implement the same interfaces as in-memory repos. Switch with one line in `config/container.php`:

```
config/container.php (the ONLY file that changes):

    BEFORE:  UserRepositoryInterface => InMemoryUserRepository
    AFTER:   UserRepositoryInterface => DoctrineUserRepository

    Domain layer changes needed: ZERO
    Application layer changes needed: ZERO
```

Doctrine provides ACID via its built-in Unit of Work:

```
  EntityManager
       |
       +-- persist($asset)     <-- tracks the change
       +-- persist($folder)    <-- tracks the change
       +-- flush()             <-- writes ALL changes in ONE transaction
       |                           (Atomicity + Consistency)
       +-- On failure: automatic rollback
```

### Domain Events

Events are recorded during entity state transitions and dispatched after successful persistence:

```
  Asset.publish()
       |
       +-- 1. Validate business rules
       +-- 2. Change status to Published
       +-- 3. Record AssetPublished event (in-memory queue)
       |
  AssetService.publishAsset()
       |
       +-- 4. repository.save(asset)           <-- persist first
       +-- 5. dispatcher.dispatch(events)      <-- dispatch after
       |
  SimpleEventDispatcher
       |
       +-- 6. Route to LogAssetPublishedHandler
       +-- 7. Handler logs the event via Monolog
```

Key design: events are dispatched AFTER persistence succeeds, not before. This prevents side effects from running on failed transactions.

### DI Container (PHP-DI)

All wiring in one file (`config/container.php`):

```
  Interface                       -->  Implementation
  -------------------------------------------------
  UserRepositoryInterface         -->  InMemoryUserRepository
  AssetRepositoryInterface        -->  InMemoryAssetRepository
  FolderRepositoryInterface       -->  InMemoryFolderRepository
  EventDispatcherInterface        -->  SimpleEventDispatcher
  LoggerInterface (PSR-3)         -->  Monolog Logger
```

### Centralized Error Middleware

Maps domain exceptions to HTTP status codes in one declarative map:

```
  Exception                         -->  HTTP Status
  -------------------------------------------------
  UserNotFoundException             -->  404 Not Found
  AssetNotFoundException            -->  404 Not Found
  FolderNotFoundException           -->  404 Not Found
  DuplicateEmailException           -->  409 Conflict
  InvalidAssetTransitionException   -->  422 Unprocessable
  DomainException                   -->  422 Unprocessable
  InvalidArgumentException          -->  422 Unprocessable
  Everything else                   -->  500 Internal Error
```

This replaced ~67 lines of repetitive try/catch across 3 controllers.

### PSR-3 Logging (Monolog)

- File handler: `var/logs/app.log` (DEBUG level)
- Stdout handler: console output (INFO level)
- Injected via DI into middleware, event handlers, and services

---

## SOLID Principles Map

| Principle | Where It's Demonstrated |
|-----------|------------------------|
| **S** - Single Responsibility | Each entity does one thing. Middleware handles errors, controllers handle HTTP, services orchestrate. |
| **O** - Open/Closed | Add new event handlers without modifying existing code. Add new repos without changing services. |
| **L** - Liskov Substitution | `DoctrineUserRepository` and `InMemoryUserRepository` are interchangeable — both implement `UserRepositoryInterface`. |
| **I** - Interface Segregation | Small, focused interfaces: `UserRepositoryInterface` has only the methods the domain needs. |
| **D** - Dependency Inversion | Services depend on `*Interface`, never on `InMemory*` or `Doctrine*` directly. |

---

## Framework Strategy

**Slim 4** was chosen intentionally — not because it's the best production framework, but because it's the best framework to **demonstrate architecture**:

- **Zero magic**: every DI binding, middleware registration, and route is explicit — interviewers see exactly how the system works
- **PSR-compliant**: PSR-7, PSR-11, PSR-15 — industry standards, not vendor-specific APIs
- **Framework-independent architecture**: Domain and Application layers have zero framework imports

**For production**, migrate to **Symfony** — same Domain/Application layers, rewrite only Infrastructure:

```
Domain (13 files)      → ZERO changes   (72% of codebase preserved)
Application (7 files)  → ZERO changes
Infrastructure         → Rewrite for Symfony
Config                 → Rewrite (services.yaml)
```

See `PRODUCTION.md` → Section 0 for full comparison.

---

## Production Readiness

This demo runs on SQLite + Slim. See `PRODUCTION.md` for the enterprise scaling plan:

| Concern | Demo | Production |
|---------|------|------------|
| Database | SQLite | PostgreSQL + read replicas |
| Storage | Local | S3 + CloudFront CDN |
| Events | Synchronous | SQS + async workers |
| Cache | None | Redis cluster |
| Search | LIKE on JSON | Elasticsearch |
| Framework | Slim 4 | Symfony |
| Monitoring | File logs | Grafana + Prometheus + OpenTelemetry |

All production changes require **zero Domain layer modifications** — that's Hexagonal Architecture.

---

## Testing Strategy

### Test Pyramid

| Layer | Type | What It Tests | Mocking |
|-------|------|---------------|---------|
| Domain | Unit | Value Object invariants, entity behavior, state machine, events | None needed |
| Application | Unit | Service use cases, event dispatching | `createMock()` on interfaces |
| Integration | Integration | Full workflow with real repos + real dispatcher | No mocks (uses NullLogger) |

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run only unit tests
./vendor/bin/phpunit --testsuite Unit

# Run only integration tests
./vendor/bin/phpunit --testsuite Integration
```

---

## Project Structure

```
PHP-DDD-TDD/
|-- composer.json
|-- phpunit.xml
|-- config/
|   |-- container.php                  # DI Container wiring
|   +-- doctrine.php                   # EntityManager factory
|-- bin/
|   +-- create-schema.php             # SQLite schema generator
|-- public/
|   +-- index.php                      # Composition Root
|-- src/
|   |-- Domain/                        # >>> THE CORE <<<
|   |   |-- Entity/
|   |   |   |-- User.php
|   |   |   |-- Asset.php
|   |   |   +-- Folder.php
|   |   |-- ValueObject/
|   |   |   |-- UserId.php, Email.php, UserStatus.php
|   |   |   |-- AssetId.php, FolderId.php
|   |   |   |-- FileName.php, FileSize.php, MimeType.php
|   |   |   +-- AssetStatus.php
|   |   |-- Repository/
|   |   |   |-- UserRepositoryInterface.php
|   |   |   |-- AssetRepositoryInterface.php
|   |   |   +-- FolderRepositoryInterface.php
|   |   |-- Event/
|   |   |   |-- DomainEvent.php
|   |   |   |-- EventDispatcherInterface.php
|   |   |   |-- EventRecordingTrait.php
|   |   |   |-- AssetPublished.php
|   |   |   |-- AssetArchived.php
|   |   |   +-- UserSuspended.php
|   |   +-- Exception/
|   |       |-- UserNotFoundException.php
|   |       |-- AssetNotFoundException.php
|   |       |-- FolderNotFoundException.php
|   |       |-- DuplicateEmailException.php
|   |       +-- InvalidAssetTransitionException.php
|   |-- Application/
|   |   |-- DTO/
|   |   |   |-- CreateUserDTO.php, UpdateUserDTO.php
|   |   |   |-- UploadAssetDTO.php
|   |   |   +-- CreateFolderDTO.php
|   |   |-- Service/
|   |   |   |-- UserService.php
|   |   |   |-- AssetService.php
|   |   |   +-- FolderService.php
|   |   +-- EventHandler/
|   |       +-- LogAssetPublishedHandler.php
|   +-- Infrastructure/
|       |-- Persistence/
|       |   |-- InMemoryUserRepository.php
|       |   |-- InMemoryAssetRepository.php
|       |   |-- InMemoryFolderRepository.php
|       |   |-- DoctrineUserRepository.php
|       |   |-- DoctrineAssetRepository.php
|       |   +-- DoctrineFolderRepository.php
|       |-- Doctrine/Type/
|       |   |-- EmailType.php
|       |   |-- AssetStatusType.php
|       |   +-- UserStatusType.php
|       |-- Event/
|       |   +-- SimpleEventDispatcher.php
|       +-- Http/
|           |-- Middleware/
|           |   +-- JsonErrorMiddleware.php
|           |-- UserController.php
|           |-- AssetController.php
|           +-- FolderController.php
+-- tests/
    |-- Unit/
    |   |-- Domain/
    |   |   |-- Entity/   (UserTest, AssetTest, FolderTest)
    |   |   |-- ValueObject/ (FileNameTest, FileSizeTest, MimeTypeTest, etc.)
    |   |   +-- Event/    (DomainEventTest)
    |   +-- Application/
    |       |-- Service/  (UserServiceTest, AssetServiceTest, FolderServiceTest)
    |       +-- DTO/      (DTOTest)
    +-- Integration/
        |-- UserWorkflowTest.php
        +-- AssetWorkflowTest.php
```

---

## API Endpoints

### Users
```
GET    /api/users              List all users
GET    /api/users/{id}         Get user by ID
POST   /api/users              Create a new user
PUT    /api/users/{id}         Update a user
POST   /api/users/{id}/suspend     Suspend a user
POST   /api/users/{id}/reactivate  Reactivate a user
DELETE /api/users/{id}         Delete a user
```

### Assets
```
GET    /api/assets              List all assets
GET    /api/assets/{id}         Get asset by ID
POST   /api/assets              Upload a new asset
POST   /api/assets/{id}/publish     Publish (Draft -> Published)
POST   /api/assets/{id}/archive     Archive (Published -> Archived)
POST   /api/assets/{id}/restore     Restore (Archived -> Draft)
POST   /api/assets/{id}/move        Move to folder
DELETE /api/assets/{id}         Delete an asset
```

### Folders
```
GET    /api/folders              List root folders
GET    /api/folders/{id}         Get folder by ID
GET    /api/folders/{id}/subfolders  List subfolders
POST   /api/folders              Create a new folder
PUT    /api/folders/{id}         Rename a folder
DELETE /api/folders/{id}         Delete a folder
```

### GraphQL
```
POST   /graphql                  GraphQL endpoint
```

Example queries:
```graphql
{ users { id name email status } }
{ asset(id: 1) { id fileName status tags } }
{ folders { id name isRoot } }
```

### System
```
GET    /                         API index + route listing
GET    /api/health               Health check (no auth required)
```

---

## Security

```
Middleware Stack (execution order):

  Request ──> JsonErrorMiddleware ──> SecurityHeaders ──> CORS ──> JWT Auth ──> Router ──> Controller
                 catches errors         adds headers      CORS      validates
                                                        preflight    token
```

| Layer | Mechanism | What it prevents |
|-------|-----------|------------------|
| **Value Objects** | `Email`, `FileName`, `MimeType` validation | Bad input at boundary |
| **Doctrine ORM** | Parameterized queries (auto) | SQL injection |
| **LIKE escaping** | `%` and `_` wildcard escaping | SQL wildcard injection |
| **JWT Middleware** | Bearer token validation | Unauthorized access |
| **Security Headers** | HSTS, CSP, X-Frame-Options, etc. | Clickjacking, MIME sniffing |
| **CORS Middleware** | Origin allowlisting + OPTIONS preflight | Cross-origin abuse |
| **Non-root Docker** | `appuser` in Dockerfile | Container escape |
| **Env secrets** | `.env` gitignored, `.env.example` committed | Secret leakage |

---

## Quick Start

### Option 1: Docker (recommended)
```bash
make up
curl http://localhost:8080/api/health

# Generate a test JWT token
make token USER_ID=1

# Use the token
curl -H "Authorization: Bearer <token>" http://localhost:8080/api/users
```

### Option 2: Local PHP
```bash
# Install dependencies
composer install

# Copy env file
cp .env.example .env

# Run tests
make quality

# Start the dev server
composer serve
# -> http://localhost:8080

# Generate a test JWT token
php bin/generate-token.php --user-id=1
```

### Makefile Commands
```bash
make help        # Show all available commands
make quality     # Run tests + PHPStan + CS-Fixer
make up          # Docker Compose up
make down        # Docker Compose down
make token       # Generate test JWT token
make ci          # Simulate full CI pipeline locally
```
