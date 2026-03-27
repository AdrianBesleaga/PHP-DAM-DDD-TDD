# Production Architecture — Scaling to 1M+ Documents

This document outlines the architecture decisions, infrastructure services, and operational practices needed to deploy the DAM system at production scale. It bridges the gap between the current demo implementation and a real-world enterprise deployment processing **1 million+ documents**.

---

## Current State vs. Production Target

```
                        CURRENT (Demo)              PRODUCTION TARGET
                        ──────────────              ─────────────────
Documents               ~100                        1,000,000+
Concurrency             1 (single process)          1,000+ concurrent users
Storage                 Local filesystem            S3 + CloudFront CDN
Database                SQLite (single file)        PostgreSQL (read replicas)
Queue                   Synchronous events          SQS / RabbitMQ
Cache                   None                        Redis cluster
Search                  LIKE on JSON column         Elasticsearch / Meilisearch
Auth                    Mock JWT                    Auth0 / Cognito
Monitoring              app.log file                Datadog / Grafana + Prometheus
Deployment              php -S localhost            K8s / ECS + auto-scaling
```

---

## 0. Framework Strategy

### Why Slim 4 (Current)

Slim was chosen intentionally to demonstrate that the architecture is **framework-independent**:
- **Zero magic** — every line is explicit (DI wiring, middleware, routing). Interviewers see exactly how the system works, not framework abstractions.
- **PSR-compliant** — PSR-7 (HTTP), PSR-11 (Container), PSR-15 (Middleware). These are industry standards, not framework-specific APIs.
- **DDD-friendly** — Slim doesn't impose structure. We designed our own layers instead of fitting into MVC.

### Why Symfony (Production)

| Concern | Slim 4 (demo) | Symfony (production) |
|---------|---------------|---------------------|
| Routing | Manual `$app->get(...)` | `#[Route]` attributes (auto-discovery) |
| DI | PHP-DI (manual wiring) | Autowiring (automatic) |
| Async | `SimpleEventDispatcher` | Symfony Messenger (SQS, RabbitMQ) |
| CLI | None | `bin/console` (migrations, cache, debug) |
| Auth | Custom JWT middleware | LexikJWTAuthenticationBundle |
| Validation | Manual in VOs/DTOs | Symfony Validator (annotations) |
| Config | `.env` + manual | Flex recipes (auto-configuration) |

### Migration Cost (Slim → Symfony)

```
Layer              Files    Changes Needed
──────────────     ─────    ──────────────
Domain   (13)      VOs, Entities, Events, Interfaces    ZERO changes
Application (7)    Services, DTOs, Handlers             ZERO changes
Infrastructure     Controllers, Repos, Middleware       Rewrite (adapt to Symfony)
Config             container.php, routes                Rewrite (services.yaml)
──────────────────────────────────────────────────────────────
Total: 28% of codebase changes. 72% stays identical.
```

This is the whole point of Hexagonal Architecture: **the framework is a detail, not a foundation**. The Domain and Application layers — where all business logic lives — would be copy-pasted unchanged into a Symfony project.

---

## 1. Storage Layer

### 1.1 Object Storage (Amazon S3)

Binary files (images, videos, PDFs) should **never** be stored in the database. At scale:

```
┌──────────┐     upload      ┌───────────────┐     replicate    ┌──────────┐
│  Client  │ ──────────────> │   S3 Bucket   │ ──────────────> │ S3 Cross │
│  (API)   │                 │  (us-east-1)  │                 │  Region  │
└──────────┘                 └───────────────┘                 └──────────┘
                                    │
                                    │  origin
                                    v
                             ┌──────────────┐
                             │  CloudFront  │  <── CDN (edge caching)
                             │    (CDN)     │
                             └──────────────┘
                                    │
                                    │  signed URL (time-limited)
                                    v
                             ┌──────────────┐
                             │  End Users   │  <── downloads from nearest edge
                             └──────────────┘
```

**Key decisions:**
- **Pre-signed URLs**: Never proxy binary through the API. Generate time-limited S3 pre-signed URLs (15 min TTL) and redirect clients directly to S3/CDN.
- **Multipart uploads**: Files > 100MB use S3 multipart upload. The API generates upload credentials; the client uploads directly to S3.
- **Content-addressed storage**: Hash file content (SHA-256) on upload. Use hash as S3 key. Automatic deduplication — identical files reference the same S3 object.
- **Lifecycle policies**: Move assets to S3 Glacier after 90 days of status `archived`. Delete permanently after 1 year.

### 1.2 CDN (CloudFront)

For assets accessed frequently (published images, thumbnails):
- Edge caching across 400+ global locations
- Reduces S3 egress costs by ~70%
- Custom domain: `assets.company.com`
- Origin Access Identity (OAI) — CDN is the only way to reach S3

### 1.3 Thumbnail Generation

```
Upload ──> S3 Put Event ──> Lambda (sharp/imagick) ──> S3 (thumbnails/)
                                                           │
                                    ┌──────────────────────┤
                                    │                      │
                               100x100.webp          400x400.webp
```

Generate multiple sizes on upload. Store alongside originals. Serve via CDN.

---

## 2. Database Layer

### 2.1 PostgreSQL (Primary Database)

Replace SQLite with PostgreSQL for production:

| Feature | SQLite | PostgreSQL |
|---------|--------|------------|
| Concurrent writes | ❌ Single writer lock | ✅ MVCC (multi-version) |
| Connections | ❌ File-based, 1 at a time | ✅ Connection pooling (PgBouncer) |
| JSON queries | ❌ LIKE on text | ✅ `jsonb` with GIN indexes |
| Full-text search | ❌ Not supported | ✅ `tsvector` + `tsquery` |
| Replication | ❌ Not supported | ✅ Streaming replication |

### 2.2 Read Replicas

```
                    ┌─────────────────┐
                    │   Primary (RW)  │  <── writes (INSERT, UPDATE, DELETE)
                    │   PostgreSQL    │
                    └────────┬────────┘
                             │  streaming replication
                    ┌────────┴────────┐
           ┌────────┴────┐     ┌──────┴──────┐
           │  Replica 1  │     │  Replica 2  │  <── reads (SELECT)
           │  (read-only) │     │  (read-only) │
           └─────────────┘     └─────────────┘
```

**Implementation in our architecture:**

```php
// config/container.php — Doctrine with read/write splitting
'doctrine.primary' => DI\factory(fn () => createEntityManager('primary')),
'doctrine.replica' => DI\factory(fn () => createEntityManager('replica')),

// Repository interface stays the same — Liskov Substitution:
AssetRepositoryInterface::class => DI\autowire(DoctrineAssetRepository::class)
    ->constructorParameter('em', DI\get('doctrine.primary')),
```

The Domain layer doesn't know about replicas — that's an Infrastructure concern.

### 2.3 Connection Pooling (PgBouncer)

PHP's process-per-request model creates a new DB connection per request. At 1,000 concurrent users:

```
WITHOUT PgBouncer:      1,000 requests = 1,000 DB connections  ← crashes PostgreSQL
WITH PgBouncer:         1,000 requests = 50 pooled connections  ← efficient
```

### 2.4 Database Migrations

Replace `bin/create-schema.php` (drop + recreate) with Doctrine Migrations:

```bash
# Generate migration from entity changes
vendor/bin/doctrine-migrations diff

# Apply migrations (idempotent, versioned)
vendor/bin/doctrine-migrations migrate

# Rollback
vendor/bin/doctrine-migrations migrate prev
```

Each migration is tracked in a `doctrine_migration_versions` table. CI/CD runs migrations automatically before deployment.

### 2.5 Tags: Junction Table Migration

Replace JSON `LIKE` queries with a proper junction table:

```
CURRENT (fragile):                    PRODUCTION (scalable):
assets.tags = '["hero","banner"]'     asset_tags (junction table)
WHERE tags LIKE '%"hero"%'            ┌──────────┬──────────┐
                                      │ asset_id │ tag      │
                                      ├──────────┼──────────┤
                                      │ 1        │ hero     │
                                      │ 1        │ banner   │
                                      │ 2        │ hero     │
                                      └──────────┴──────────┘
                                      SELECT a.* FROM assets a
                                      JOIN asset_tags t ON a.id = t.asset_id
                                      WHERE t.tag = 'hero'
                                          ^^ indexed, O(log n)
```

---

## 3. Async Processing (Message Queues)

### 3.1 Why Async?

The current `SimpleEventDispatcher` is synchronous. If a handler takes 5 seconds (e.g., thumbnail generation), the HTTP response waits 5 seconds.

```
CURRENT (synchronous):
  Request ──> publish() ──> save() ──> generate thumbnail (5s) ──> Response  [5s total]

PRODUCTION (async):
  Request ──> publish() ──> save() ──> enqueue message ──> Response  [50ms total]
                                              │
                                              v  (background worker)
                                        generate thumbnail (5s)
```

### 3.2 Amazon SQS + Workers

```
┌──────────┐    publish event    ┌──────────┐    poll    ┌──────────────────┐
│  API     │ ─────────────────> │  SQS     │ <──────── │  Worker Process  │
│  Server  │                    │  Queue   │           │  (PHP CLI)       │
└──────────┘                    └──────────┘           └──────────────────┘
                                     │                        │
                                     │   Dead Letter Queue    │
                                     └──> DLQ (failed jobs)   │
                                                              │
                                              ┌───────────────┤
                                              │               │
                                        Thumbnail Gen    Search Index
                                        Metadata Extract  Notification
                                        Virus Scan        Audit Log
```

**In our DDD architecture, this requires only one change:**

```php
// Current:
EventDispatcherInterface::class => DI\autowire(SimpleEventDispatcher::class)

// Production:
EventDispatcherInterface::class => DI\autowire(SqsEventDispatcher::class)
```

The Domain layer, Application Services, and Controllers remain **unchanged**. This is the power of the Ports & Adapters architecture.

### 3.3 Queue Topology

| Queue | Purpose | Consumers | Retry Policy |
|-------|---------|-----------|-------------|
| `dam-asset-processed` | Thumbnail gen, metadata extraction | Auto-scaling workers | 3 retries, exponential backoff |
| `dam-notifications` | Email, Slack, webhook notifications | Notification service | 5 retries, then DLQ |
| `dam-search-index` | Update Elasticsearch index | Search workers | 3 retries |
| `dam-audit` | Immutable audit log entries | Audit service | Infinite retry (critical) |

---

## 4. Caching Layer (Redis)

### 4.1 Cache Strategy

```
                    ┌─────────┐
  Request ──> API ──│  Redis  │──> HIT? ──> Return cached response
                    │ (cache) │
                    └────┬────┘
                         │ MISS
                         v
                    ┌─────────┐
                    │  PostgreSQL  │──> Query ──> Store in Redis ──> Return
                    └─────────┘
```

### 4.2 What to Cache

| Data | TTL | Invalidation | Rationale |
|------|-----|-------------|-----------|
| Asset metadata | 5 min | On update/delete | Frequently read, rarely modified |
| User profiles | 10 min | On update | Read-heavy |
| Folder tree | 15 min | On structural change | Tree traversal is expensive |
| Tag search results | 2 min | On asset tag change | LIKE queries are slow |
| Health check | 30s | Automatic | Reduces DB checks |
| JWT blocklist | Until token expiry | Never (append-only) | Token revocation |

### 4.3 Cache Invalidation Patterns

```php
// Write-through: update cache on write
public function save(Asset $asset): void
{
    $this->em->persist($asset);
    $this->em->flush();
    $this->redis->set("asset:{$asset->id()}", serialize($asset), 300);
}

// Invalidation via Domain Events (our existing pattern):
// AssetPublished event → handler invalidates cache
class CacheInvalidationHandler
{
    public function handle(AssetPublished $event): void
    {
        $this->redis->del("asset:{$event->assetId}");
        $this->redis->del("assets:list");
    }
}
```

---

## 5. Search (Elasticsearch / Meilisearch)

Replace LIKE queries with a dedicated search engine:

```
┌──────────┐    search request    ┌───────────────┐
│  Client  │ ──────────────────> │ Elasticsearch │ ──> instant results
└──────────┘                     └───────────────┘
                                        ^
                                        │  index update (async via SQS)
                                        │
┌──────────┐    domain event     ┌──────┴──────┐
│  API     │ ──────────────────> │ SQS Worker  │
└──────────┘                     └─────────────┘
```

Features enabled by Elasticsearch:
- Full-text search across file names, descriptions, tags
- Faceted search (filter by MIME type, status, date range)
- Fuzzy matching ("prodcut" finds "product")
- Relevance scoring
- Sub-50ms search across millions of documents

---

## 6. Observability

### 6.1 Structured Logging

Replace text logs with JSON structured logging:

```json
{
  "timestamp": "2026-03-27T23:51:00Z",
  "level": "INFO",
  "message": "Asset published",
  "context": {
    "asset_id": 42,
    "user_id": 7,
    "request_id": "req_abc123",
    "duration_ms": 45
  }
}
```

**Correlation ID**: Every request gets a unique `X-Request-ID` header. All logs, queue messages, and downstream calls include this ID. When something fails, trace the entire request across services.

### 6.2 Monitoring Stack

```
┌──────────────────────────────────────────────────────────┐
│                     Grafana Dashboard                     │
│  ┌─────────────┐  ┌──────────────┐  ┌─────────────────┐ │
│  │ Request Rate │  │ Error Rate   │  │ P99 Latency     │ │
│  │  1,200/sec   │  │  0.02%       │  │  145ms          │ │
│  └─────────────┘  └──────────────┘  └─────────────────┘ │
│  ┌─────────────┐  ┌──────────────┐  ┌─────────────────┐ │
│  │ Queue Depth  │  │ DB Conns     │  │ Cache Hit Rate  │ │
│  │  23 pending  │  │  42/50       │  │  94.2%          │ │
│  └─────────────┘  └──────────────┘  └─────────────────┘ │
└──────────────────────────────────────────────────────────┘
               ^                ^                ^
               │                │                │
         Prometheus        Loki (logs)       Redis metrics
```

### 6.3 The Four Golden Signals (Google SRE)

| Signal | Metric | Alert Threshold |
|--------|--------|----------------|
| **Latency** | P50, P95, P99 response time | P99 > 500ms |
| **Traffic** | Requests per second | N/A (informational) |
| **Errors** | 5xx rate as % of total | > 1% for 5 min |
| **Saturation** | CPU, memory, DB connections, queue depth | > 80% for 10 min |

### 6.4 Distributed Tracing (OpenTelemetry)

```
Request (trace_id: abc123)
├── API Gateway         [    2ms ]
├── JWT Validation      [    1ms ]
├── Controller          [   45ms ]
│   ├── AssetService    [   40ms ]
│   │   ├── DB Query    [   12ms ]  ← PostgreSQL
│   │   ├── S3 Upload   [   25ms ]  ← AWS S3
│   │   └── SQS Publish [    3ms ]  ← Queue
│   └── Response Build  [    5ms ]
└── Total               [   48ms ]
```

Instrument with OpenTelemetry PHP SDK. Export to Jaeger, Datadog, or AWS X-Ray.

### 6.5 Alerting

| Alert | Condition | Channel | Severity |
|-------|-----------|---------|----------|
| API down | Health check fails 3x | PagerDuty | P1 (Critical) |
| High error rate | 5xx > 1% for 5 min | Slack + PagerDuty | P2 (High) |
| Queue backlog | SQS depth > 10,000 | Slack | P3 (Medium) |
| Slow queries | DB query > 1s | Slack | P3 (Medium) |
| Disk usage | > 85% | Slack | P3 (Medium) |
| Certificate expiry | < 14 days | Email | P4 (Low) |

---

## 7. Security at Scale

### 7.1 Multi-Tenant Isolation

```
Tenant A ──> API ──> middleware: extract tenant from JWT
                          │
                          ├──> DB: WHERE tenant_id = 'A' (row-level security)
                          ├──> S3: s3://bucket/tenants/A/assets/...
                          └──> Redis: key prefix tenant:A:asset:42
```

Every data access is scoped by `tenant_id`. PostgreSQL Row-Level Security (RLS) enforces this at the database level — even direct SQL queries can't cross tenant boundaries.

### 7.2 Secret Management

```
CURRENT:              .env file             ← local dev only
PRODUCTION:           AWS Secrets Manager   ← encrypted, audited, rotatable
                      │
                      ├── JWT_SECRET (auto-rotated every 30 days)
                      ├── DB_PASSWORD
                      ├── S3_ACCESS_KEY
                      └── REDIS_AUTH_TOKEN
```

### 7.3 Virus Scanning

```
Upload ──> S3 ──> Lambda (ClamAV) ──> clean? ──> mark as safe
                                  └──> infected? ──> quarantine + alert
```

All uploaded files are scanned before being made available.

---

## 8. Deployment Architecture

### 8.1 High Availability

```
                           ┌─────────────────┐
                           │   CloudFront    │
                           │   (CDN/WAF)     │
                           └────────┬────────┘
                                    │
                           ┌────────┴────────┐
                           │  ALB (Load      │
                           │  Balancer)      │
                           └────────┬────────┘
                                    │
               ┌────────────────────┼────────────────────┐
               │                    │                    │
        ┌──────┴──────┐     ┌──────┴──────┐     ┌──────┴──────┐
        │  ECS Task   │     │  ECS Task   │     │  ECS Task   │
        │  (PHP-FPM)  │     │  (PHP-FPM)  │     │  (PHP-FPM)  │
        │  + Nginx    │     │  + Nginx    │     │  + Nginx    │
        └──────┬──────┘     └──────┬──────┘     └──────┬──────┘
               │                    │                    │
               └────────────────────┼────────────────────┘
                                    │
               ┌────────────────────┼────────────────────┐
               │                    │                    │
        ┌──────┴──────┐     ┌──────┴──────┐     ┌──────┴──────┐
        │ PostgreSQL  │     │   Redis     │     │    SQS      │
        │ (RDS)       │     │ (ElastiCache│     │  (Queues)   │
        │ Multi-AZ    │     │  Cluster)   │     │             │
        └─────────────┘     └─────────────┘     └─────────────┘
```

### 8.2 Auto-Scaling Rules

| Component | Scale Trigger | Min | Max |
|-----------|--------------|-----|-----|
| ECS Tasks (API) | CPU > 70% for 3 min | 3 | 50 |
| ECS Tasks (Workers) | SQS queue depth > 100 | 2 | 20 |
| RDS Read Replicas | Read IOPS > 80% | 1 | 5 |
| ElastiCache | Memory > 75% | 2 nodes | 6 nodes |

### 8.3 Blue-Green Deployment

```
Current (Blue):     v3.1.0  ──> 100% traffic
New (Green):        v3.2.0  ──>   0% traffic

Step 1: Deploy Green alongside Blue
Step 2: Run smoke tests against Green
Step 3: Shift 10% traffic to Green (canary)
Step 4: Monitor error rate for 15 min
Step 5: If OK → shift 100% to Green
Step 6: If NOT OK → rollback to Blue (instant, zero downtime)
```

### 8.4 CI/CD Pipeline (Production)

```
┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐
│   Push   │──>│  Quality │──>│  Docker  │──>│  Deploy  │──>│  Verify  │
│  to main │   │  Gates   │   │  Build   │   │  (ECS)   │   │  (smoke) │
└──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘
                    │                              │
              PHPUnit (210)                   DB Migrations
              PHPStan (L8)                    Health check
              CS-Fixer                        Canary %
              Security audit
```

---

## 9. Performance Benchmarks (Target)

| Endpoint | Current | Target | How |
|----------|---------|--------|-----|
| `GET /api/assets` | 5ms | <50ms at 10K assets | Redis cache + pagination |
| `GET /api/assets/{id}` | 2ms | <10ms | Redis cache |
| `POST /api/assets` | 10ms | <100ms | Async thumbnail gen |
| Search by tag | 15ms | <50ms at 1M assets | Elasticsearch |
| File upload (100MB) | N/A | <5s | S3 multipart direct upload |

### 9.1 Pagination (Cursor-Based)

At 1M documents, `OFFSET 999000 LIMIT 20` scans 999,000 rows. Use cursor-based pagination:

```
GET /api/assets?cursor=eyJpZCI6MTAwMH0=&limit=20

Response:
{
  "data": [...],
  "pagination": {
    "next_cursor": "eyJpZCI6MTAyMH0=",
    "has_more": true
  }
}
```

The cursor is a base64-encoded `WHERE id > :last_id` — O(1) regardless of page depth.

---

## 10. Cost Estimation (Monthly)

| Service | Spec | Cost |
|---------|------|------|
| ECS (3 tasks, 2 vCPU, 4GB) | API servers | ~$150 |
| RDS PostgreSQL (db.r6g.large, Multi-AZ) | Primary database | ~$350 |
| RDS Read Replica (db.r6g.large) | Read scaling | ~$175 |
| ElastiCache Redis (cache.r6g.large, 2 nodes) | Cache cluster | ~$250 |
| S3 (1TB storage, 10M requests) | Asset storage | ~$30 |
| CloudFront (5TB transfer) | CDN | ~$425 |
| SQS (10M messages) | Event queues | ~$5 |
| Elasticsearch (2 nodes, m5.large) | Search | ~$300 |
| ALB | Load balancer | ~$25 |
| CloudWatch / Datadog | Monitoring | ~$100 |
| **Total** | | **~$1,810/mo** |

---

## 11. Migration Path (Phased)

### Phase 1: Database Migration (Week 1-2)
- [ ] Replace SQLite with PostgreSQL
- [ ] Implement Doctrine Migrations
- [ ] Move tags to junction table (`asset_tags`)
- [ ] Replace `nextId()` with UUIDs (v7, time-sortable)
- [ ] Add PgBouncer for connection pooling

### Phase 2: Storage + CDN (Week 3-4)
- [ ] Integrate S3 for binary storage
- [ ] Implement pre-signed URL generation
- [ ] Set up CloudFront distribution
- [ ] Add multipart upload support
- [ ] Implement content-addressed deduplication

### Phase 3: Async Processing (Week 5-6)
- [ ] Replace `SimpleEventDispatcher` with `SqsEventDispatcher`
- [ ] Build worker process (PHP CLI + SQS consumer)
- [ ] Add thumbnail generation pipeline
- [ ] Implement dead letter queue handling
- [ ] Add virus scanning (ClamAV Lambda)

### Phase 4: Caching + Search (Week 7-8)
- [ ] Integrate Redis for read caching
- [ ] Implement cache invalidation handlers
- [ ] Set up Elasticsearch cluster
- [ ] Build search indexing pipeline
- [ ] Add cursor-based pagination

### Phase 5: Observability (Week 9-10)
- [ ] Structured JSON logging (Monolog JSON formatter)
- [ ] Add correlation ID middleware (`X-Request-ID`)
- [ ] Integrate OpenTelemetry for distributed tracing
- [ ] Set up Grafana + Prometheus dashboards
- [ ] Configure alerting (PagerDuty, Slack)

### Phase 6: Multi-Tenancy + Security (Week 11-12)
- [ ] Add `tenant_id` to all entities
- [ ] Implement PostgreSQL Row-Level Security
- [ ] Set up AWS Secrets Manager
- [ ] Add rate limiting (Redis sliding window)
- [ ] Implement audit logging (immutable trail)

---

## 12. Architecture Decision Records

These production changes would require new ADRs:

| ADR | Decision |
|-----|----------|
| ADR-008 | PostgreSQL over MySQL (JSONB, RLS, CTE support) |
| ADR-009 | SQS over RabbitMQ (managed, no ops overhead) |
| ADR-010 | Cursor-based pagination over offset-based |
| ADR-011 | Content-addressed storage (SHA-256 deduplication) |
| ADR-012 | Redis cache invalidation via Domain Events |
| ADR-013 | OpenTelemetry over vendor-specific APM |

---

## Key Interview Talking Point

> "The current implementation processes requests synchronously with SQLite — but the architecture was designed for scale from day one. Replacing `InMemoryRepository` with `DoctrineRepository` was 3 lines in the DI container. Adding GraphQL to REST was zero domain changes. Replacing `SimpleEventDispatcher` with `SqsEventDispatcher` would be exactly the same — one line in the container. That's the power of Hexagonal Architecture: the Domain doesn't care about infrastructure, so infrastructure can evolve independently."
