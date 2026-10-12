# Phase 5 — Automated Test Matrix

Status: **WS3 (knowledge base + AI surface)**
Owner: Phase 5 automated testing strategy
Branch: `test/phase-5-automated-suite`

This document is the source of truth for what Phase 5 will test, how it is
prioritised, and where coverage is currently missing. It is updated as each
workstream lands.

## Priority definitions

- **P0 — release blocking.** Security boundaries, tenant isolation, money /
  entitlement integrity, and data-loss paths. A regression here can leak data
  or charge/deny customers incorrectly. CI must block merges on failure.
- **P1 — important.** Core product behaviour and regression protection for
  features customers depend on every day.
- **P2 — supporting.** Observability, admin tooling and quality-of-life paths.

## Test infrastructure (WS0)

| Concern | Decision |
| --- | --- |
| Central schema | SQLite `:memory:` via `Tests\Support\InteractsWithCentralSchema`; `tenancy.database.central_connection` is forced to `central`. |
| Tenant schema | Real `database/migrations/tenant` run once into a temp SQLite file template, then copied per tenant (`Tests\Support\InteractsWithTenancy`). |
| Tenant isolation | Each tenant gets its own SQLite file connection; `DB::purge('tenant')` on every switch. |
| Tenancy bootstrappers | Disabled (`tenancy.bootstrappers = []`) so the manual connection switch is authoritative. |
| HTTP auth | `JWTAuthMiddleware` and `TenantAware` are bypassed; RBAC (`set.permission.team`), subscription (`check.subscription`), maintenance and route `permission:` middleware stay enabled. |
| RBAC | `seedRbac()` loads the canonical `api` guard vocabulary; roles are assigned per tenant team id. |
| PostgreSQL-specific tests | Must be tagged `@group postgres` and skipped unless a PG connection is configured; they run in CI only. Never treat SQLite as sole evidence for PG locking / vector / `ILIKE` behaviour. |

## Coverage inventory (current)

| Area | Existing coverage | Priority |
| --- | --- | --- |
| Registration / onboarding | `Feature/Auth/RegistrationTest` (10 tests) | P0 |
| Tenant provisioning | `Feature/Tenant/TenantProvisioningTest` (8) | P0 |
| Tenant isolation (new) | `Feature/Tenancy/TenantIsolationTest` (6) | P0 |
| Authentication (new) | `Feature/Auth/LoginTest` (11), `SessionLifecycleTest` (7) | P0 |
| Permission matrix per route (new) | `Feature/Auth/TenantPermissionMatrixTest` (4) | P0 |
| Conversations (new) | `Feature/Support/ConversationLifecycleTest` (10) | P1 |
| Messages / notes (new) | `Feature/Support/MessageThreadTest` (8) | P1 |
| Tickets / comments (new) | `Feature/Support/TicketLifecycleTest` (10) | P1 |
| KB categories API (new) | `Feature/KnowledgeBase/CategoryApiTest` (7) | P1 |
| KB articles API (new) | `Feature/KnowledgeBase/ArticleApiTest` (8) | P1 |
| AI configuration API (new) | `Feature/Ai/AIConfigurationApiTest` (8) | P1 |
| AI usage/health/analytics/logs API (new) | `Feature/Ai/AIUsageApiTest` (7) | P1 |
| Billing / entitlement | `Feature/Billing/*` (12 files) | P0 |
| AI pipeline / quota | `Feature/Ai/*` (9 files) | P0 |
| Audit log API | `Feature/Audit/AuditLogApiTest` | P0 |
| Permission sync | `Feature/Audit/UserPermissionSyncTest` | P0 |
| Team invitations / roles | `Feature/Team/*` (2 files) | P0 |
| Customers | `Feature/Customer/CustomerManagementTest` | P1 |
| Platform notifications | `Feature/Notifications/*` (3 files) | P1 |
| System settings | `Feature/SystemSettings/*` (2 files) | P1 |
| Health / incidents / metrics | `Feature/Health/*` (5 files) | P1 |
| Event/schedule wiring | `Feature/Console/EventAndScheduleReliabilityTest` | P1 |

## Gaps and planned additions

| WS | Workflow | Gap | Priority |
| --- | --- | --- | --- |
| WS1 | Login, refresh, logout, token invalidation, inactive-user lockout | No feature test on the auth endpoints (only registration). | P0 |
| WS1 | Cross-tenant membership on authenticated endpoints | Covered for customers; extend to messages/tickets. | P0 |
| WS1 | Permission matrix (owner/admin/agent/viewer) per route | Only `customers` route permission protected is asserted. | P0 |
| WS2 | Conversations CRUD + status transitions + assignment | No feature coverage. | P1 |
| WS2 | Messages create/list/read + unread counts | No feature coverage. | P1 |
| WS2 | Tickets + comments + activities lifecycle | No feature coverage. | P1 |
| WS3 | Knowledge base CRUD (categories/articles) API | Indexer covered; API CRUD not. | P1 |
| WS3 | AI configuration CRUD + provider gating | Partial (enforcement only). | P1 |
| WS3 | Usage/token accounting per tenant | Covered in billing/AI; verify API surface. | P1 |
| WS4 | Stripe webhook signature + idempotency | Covered. | P0 |
| WS4 | Subscription lifecycle API (cancel/resume/change) | Partial (plan change + jobs). | P0 |
| WS5 | Public widget session bootstrap + message ingestion | No coverage. | P0 |
| WS5 | Widget rate limiting / tenant resolution from embed key | No coverage. | P0 |
| WS6 | Analytics aggregation endpoints | No coverage. | P1 |
| WS6 | Export jobs (CSV/PDF) tenant scoping | Partial (customer CSV, invoice PDF). | P1 |
| WS6 | Audit side-effect assertions (no write on denial) | Partial. | P0 |
| WS7 | Frontend unit/E2E (auth, widget, team accept) | No frontend test framework exists. | P1 |
| WS7 | CI gate scoping pint to changed files; eslint baseline | Repo-wide pint fails (pre-existing, 485 files). | P1 |

## Baseline (rolls up per workstream)

- Backend: `vendor/bin/phpunit` → 233 passed / 763 assertions (exit 0) before WS0;
  **239 passed / 779 assertions** after WS0 (harness + isolation tests);
  **261 passed / 873 assertions** after WS1 (auth + permission matrix);
  **289 passed / 972 assertions** after WS2 (conversations / messages / tickets);
  **319 passed / 1103 assertions** after WS3 (knowledge base / AI surface).
- Pint: repo-wide `--test` fails on 485 pre-existing files → CI scopes pint to
  changed files only.
- Frontend: `npx eslint src` → 3 pre-existing problems
  (`services/streamingService.js:215`, `stores/billing.js:1496`,
  `stores/systemSettings.js:90`); no test runner configured.
- `npm run build` passes.

## Known defects captured by tags/tests (not fixed — documented)

- `KnowledgeBaseArticleService::getArticlesForAI()` is called with the raw
  `$request->query` InputBag instead of `$request->query('query')`, so
  `GET /knowledge-base/articles/search/ai` returns 500 for any request. A test
  records this; production logic is untouched.
- `searchForAI()` validates inline but catches the `ValidationException` in a
  broad `catch (\Exception)`, so a missing query also surfaces as 500 (not 422).
- Knowledge base and AI routes have **no RBAC middleware** and their
  in-controller `hasPermissionTo` guards are commented out, so any authenticated
  tenant member can call them. No negative-RBAC assertion is possible yet.

## Test-harness quirks

- `MessageCollection` returns its own `meta` array **and** Laravel appends the
  paginator meta under the same key, so `meta.total` is `[total, total]`. Assert
  on `data` counts for message index responses instead of `meta.total`.
- Ticket numbers are prefixed `TKT-{year}-{seq}` (not `TICK-`).
- Freshly created resources (`wasRecentlyCreated`) return **HTTP 201** even
  though the controllers do not set it; transitions/updates/reads return 200.
- Eloquent `create()` does not hydrate DB column defaults back onto the returned
  model (e.g. category `status`), so assert those defaults from the database.

## Conventions for new tests

- One workstream per commit; tests live under `tests/Feature/<Area>`.
- Security tests must assert the **absence** of unauthorised side effects, not
  just the status code.
- Provider integrations (AI, Stripe, PayPal) use deterministic fakes; never call
  live services or production databases.
- Do not modify production business logic to make a test pass; document the real
  defect and apply the smallest justified fix.
