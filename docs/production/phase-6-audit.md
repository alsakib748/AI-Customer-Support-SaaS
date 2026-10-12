# Phase 6 — Production Readiness & Security Audit (WS0)

- **Branch:** `ops/phase-6-production-readiness`
- **Base:** `test/phase-5-automated-suite` (d0718f1). Note: `main` (0265359) is an **ancestor** of the Phase 1–5 stack — phases 1–5 are not merged to `main`, so Phase 6 branches off the Phase 5 tip to preserve that work.
- **Method:** static inspection of `backend/` (Laravel 13) and `frontend/` (Vue 3), routes/middleware, providers, config, jobs, scheduler, health system, payments, mail and deployment files. No secrets were printed. Operational items that require real infrastructure are listed in §10 and are **not** claimed as executed.
- **Baseline:** backend suite `384 passed / 1358 assertions`; frontend `npm run build` passes; scoped lint/pint clean (see `docs/testing/phase-5-test-matrix.md`).

Severity: **P0** launch blocker · **P1** must fix before launch · **P2** should fix · **P3** nice-to-have.

---

## 1. Configuration & secrets (WS1)

| Sev | Location | Finding | Remediation |
| --- | --- | --- | --- |
| P0 | `backend/.env.example:4` | `APP_DEBUG=true` — stack traces / config leakage if copied to prod. | Ship template with `APP_DEBUG=false`; enforce via config validator. |
| P1 | `backend/.env.example:2` | `APP_ENV=local`. | Production must set `APP_ENV=production`; validator fails otherwise. |
| P1 | `backend/.env.example:3` | `APP_KEY=` empty. | Require generated key; validator fails when empty. |
| P1 | `backend/.env.example` (absent) | `JWT_SECRET` missing from template although `config/jwt.php` needs it. | Add `JWT_SECRET`; document `php artisan jwt:secret`. |
| P1 | `backend/.env.example` (absent) | No `SESSION_SECURE_COOKIE`/`SESSION_ENCRYPT`; `config/session.php:172,50` default to insecure. | Add keys; require `SESSION_SECURE_COOKIE=true` + `SESSION_ENCRYPT=true` in prod. |
| P1 | `backend/config/database.php:94,111-113,128-129` | Weak fallbacks: `DB_PASSWORD` default `root`; central/tenant default `forge`/empty password. | Remove credential defaults; validator requires explicit DB config. |
| P1 | `backend/.env.example` (absent) | All Stripe/PayPal keys missing (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `PAYPAL_CLIENT_ID`, `PAYPAL_MODE`, …) and all AI provider keys missing. | Document required keys per feature toggle; validator enforces when feature enabled. |
| P1 | `backend/config/billing.php:92`, `config/payment.php:18` | `PAYPAL_MODE` defaults to `sandbox`; a production deploy left on default runs the payment provider in test mode. | Validator rejects `sandbox` when `APP_ENV=production`. |
| P1 | `backend/database/seeders/SuperAdminSeeder.php:26-34,48` | Seeds platform super-admins with hardcoded personal emails + password `11111111`. | Never seed known credentials; require password from env and force rotation; guard seeder to non-production. |
| P1 | `backend/database/seeders/RolePermissionSeeder.php:184-185,194` | Same hardcoded `superadmin@gmail.com` / `11111111`. | Same as above. |
| P2 | `backend/database/seeders/PlanProviderPriceSeeder.php:22-37` | Placeholder provider price IDs (`price_REPLACE_*`, `P-REPLACE_*`); seeder not wired into `DatabaseSeeder`; only starter/professional mapped. | Generate real prices via `billing:seed-stripe-product`; validator detects placeholders/duplicates/test prices. |
| P2 | `backend/config/cors.php:25` | Default origins are localhost when `CORS_ALLOWED_ORIGINS` unset. | Validator requires explicit production origins; never `*`. |
| P2 | `backend/config/mail.php:114` | `MAIL_FROM_ADDRESS` default `hello@example.com` (reserved). | Require real sender in prod. |
| P2 | `backend/temp_eval8.php` | Leftover debug script committed in `backend/`. | Delete. |
| P3 | `frontend/vercel.json` | No security headers for the SPA. | Add HSTS/CSP/frame headers at the CDN/reverse proxy. |

**Positive:** no committed `.env`, keys, certs or live/test secret literals in tracked files (only `backend/.env.example`). `.env` is gitignored.

---

## 2. Tenant-aware authorization & IDOR (WS3)

| Sev | Location | Finding | Remediation |
| --- | --- | --- | --- |
| P0 | `backend/app/Services/AuthService.php:662-670` + `AuthController` forgotPassword | **Password-reset token returned to the caller** ("In production, don't return this"); any unauthenticated party can reset an arbitrary account. No email is sent. | Never return the token; always generic success; send link by email; drop `exists:` user enumeration; throttle. |
| P0 | `TenantService::update` + `TenantController::update` (`routes/api.php:151`) | `PUT /tenants/{id}` has no ownership check — any authenticated user can mutate any tenant. | Apply the `updateUserRole` cross-tenant guard. |
| P0 | `TenantService::getTenantUsers` + `TenantController::getUsers` (`api.php:153`) | `GET /tenants/{id}/users` leaks another tenant's members. | Same guard. |
| P0 | `TenantService::inviteUser` + `TenantController::inviteUser` (`api.php:154`) | URL tenant id vs current-tenant mismatch — actor with `team.invite` in tenant A can invite into tenant B. | Same guard. |
| P0 | `TenantService::removeUser` + `TenantController::removeUser` (`api.php:156`) | Same mismatch for removal. | Same guard. |
| P1 | `routes/web.php:12` + `TenantAssetController::show` | Unauthenticated `GET /tenant-assets/{tenantId}/{path}` reads any tenant's public disk. | Require auth + membership; restrict paths (no traversal); prefer signed URLs. |
| P1 | `api.php:239-255` ConversationController | `hasPermissionTo('conversations.*')` commented out; no route `permission:`. | Re-add route `permission:conversations.view/create/update/delete`. |
| P1 | `api.php:258-269` MessageController | Same for messages/notes. | `permission:conversations.view/update` (+ delete). |
| P1 | `api.php:291-312` TicketController | Same for tickets. | `permission:tickets.*`. |
| P1 | `api.php:315-343` KB Category/Article | Same for knowledge base. | `permission:knowledge.*`. |
| P1 | `api.php:272-288` ChatWidgetController | Guards commented; only destroy enforced. | `permission:widgets.*`. |
| P1 | `api.php:346-370` AIConfiguration/AIUsage | No route permission. | `permission:ai.configure` (writes), `ai.view` (reads). |
| P1 | `api.php:377-381` analytics agents/tickets/ai/widget/kb | `authorizeAnalytics()` defined but commented; no route permission. | Add `permission:analytics.*`. |
| P1 | `api.php:164-182` WorkspaceController | No role/permission guard on branding/settings writes. | `permission:workspace.update`. |
| P1 | `api.php:187-198` TeamMemberController | canView/Update/Delete commented. | `permission:members.*`. |
| P1 | `api.php:411-438` Subscription/Invoice/Payment | No permission guard on billing reads/writes. | `permission:billing.view` / `billing.manage`. |
| P1 | `api.php:385-386` AnalyticsExportController index/store | Guards commented. | `permission:analytics.export`. |
| P2 | `api.php:396-401` admin analytics | Nested in tenant group, only controller guard. | Move under `super.admin`. |
| P2 | `backend/app/Policies/TenantPolicy.php` | Policy exists but unregistered (dead code). | Register or remove; route tenant mutations through it. |

Reference implementation to copy: `TenantController::updateUserRole` (`TenantController.php:294-309`) and the `customers`/`analytics.overview` route permissions.

---

## 3. Queues, scheduler & background processing (WS2)

| Sev | Location | Finding | Remediation |
| --- | --- | --- | --- |
| P1 | `backend/config/queue.php:87,93` | Duplicate `queue` key in the `redis` connection (last wins). | Remove the duplicate. |
| P1 | `backend/routes/console.php:11-20` | Both `widget:cleanup-sessions` schedules lack `withoutOverlapping()`. | Add overlap protection. |
| P1 | `config/queue.php:107,126` | `batching`/`failed` default `DB_CONNECTION` to `sqlite` while app defaults to `pgsql`. | Align to `DB_CONNECTION`. |
| P1 | `Jobs/Billing/ProcessSubscriptionRenewals.php`, `ProcessExpiredSubscriptions.php` | `$tries = 1` — transient failures lose work. | Allow retries with backoff (idempotent operations). |
| P1 | `Jobs/Billing/ProcessBillingWebhookJob.php` | No explicit `$timeout`. | Set a bounded timeout. |
| P2 | `routes/console.php` | No `onOneServer()` for horizontal scheduler scaling. | Document/opt-in `onOneServer()` (requires shared cache lock). |
| P2 | `HealthHeartbeatJob`/`ProcessCustomerMessage` | Tenancy context handled explicitly (`Tenancy::initialize`/`end` in `finally`); billing jobs rely on central context. | Confirm no tenant leakage; add regression test. |

**Positive:** retries/backoff/tenancy handling on AI jobs (`ProcessCustomerMessage`, `ReindexKnowledgeBaseJob`), webhook job idempotency (`PaymentWebhookEvent::claimForProcessing`), no duplicate scheduler/providers/listeners found (event discovery disabled).

---

## 4. Web/application security surface (WS3)

| Sev | Location | Finding | Remediation |
| --- | --- | --- | --- |
| P1 | `routes/api.php:114,116-118` | **No rate limit** on register/login/forgot-password/reset-password; no login lockout. | Add named limiters + `throttle:` middleware. |
| P1 | `app/Http/Middleware/WidgetRateLimit.php:60-64` | Public widget limiter keyed on attacker-supplied `session_token` (rotatable) → bypass. | Key on IP + widget key, not caller-supplied token alone. |
| P1 | `bootstrap/app.php` | No security headers (HSTS/CSP/X-Frame-Options/X-Content-Type-Options). | Add a hardening middleware for API responses; configure headers at the proxy for the SPA. |
| P1 | `bootstrap/app.php` | No trusted proxies / `TrustHosts` — behind a load balancer, client IP and HTTPS detection break; host-header injection. | Configure trusted proxies/hosts per deployment. |
| P2 | `behind any reverse proxy` | No HTTPS enforcement / `URL::forceScheme`. | Enforce at proxy + `URL::forceScheme('https')` in prod. |
| P2 | `SubscriptionController::verify:617-758` | Activates from client `session_id` without checking `payment_status === 'paid'`. | Require `payment_status ∈ {paid,no_payment_required}` or rely solely on webhooks. |
| P2 | many controllers | Raw `$e->getMessage()` returned to clients (e.g. `AuthController`, `SubscriptionController`, `TenantAware:134`). | Return generic messages; log details server-side. |
| P2 | `app/Health/Checks/AiProviderHealthCheck.php:123,77-80` | Gemini API key embedded in probe URL and stored in result metadata (exposed on system-health). | Send key via header; redact URLs. |
| P2 | `SubscriptionController.php:566-569`, `PayPalGateway.php:315-319` | Logs full checkout payload / provider response body. | Redact/limit logs. |
| P2 | `frontend/src/stores/auth.js:12,107`, `services/streamingService.js:18` | JWT in `localStorage` and in URL query strings. | Short-lived stream tickets / cookie transport; document as residual risk. |

**Positive:** Stripe (`Webhook::constructEvent`) and PayPal (`verify-webhook-signature`) signature verification present; `PaymentWebhookEvent` provides replay/idempotency; public error shape is generic under `APP_DEBUG=false`; no committed secrets.

### Rate limiters
Defined: `widget.rate.limit` (WidgetRateLimit), `ai.rate.limit` (AIRateLimit), generic `throttle` on the on-demand health check. `billing.rate.limit` alias registered but unused. Auth/public endpoints unthrottled (see above).

---

## 4b. Webhook security detail (WS3)

- Stripe: `StripeGateway.php:275-296` verifies signature; controller rejects before processing (`WebhookController.php:29-32`).
- PayPal: `PayPalGateway.php:187-207` server-side verification, requires `verification_status === 'SUCCESS'`.
- Idempotency: `PaymentWebhookEvent::firstOrCreate` on `(provider, provider_event_id)` + `claimForProcessing()`; `ProcessBillingWebhookJob` skips processed events.
- Gap: `SubscriptionController::verify` client-session activation does not assert `payment_status` (P2, above).

---

## 5. Monitoring & health dashboard (WS4)

The health system is substantially built (checks, snapshot, staleness, incidents). Findings:

| Sev | Location | Finding | Remediation |
| --- | --- | --- | --- |
| P2 | `app/Health/Checks/AiProviderHealthCheck.php` | Live AI provider probe runs on every `health:check` (every minute). Cost/rate-limit risk. | Default probe off in production (`HEALTH_AI_PROBE=false`); keep `Unknown` when disabled (already correct). |
| P2 | `AiProviderHealthCheck.php:77-80,123` | API key leaked into metadata / URL (also §4). | Redact. |
| P3 | `SnapshotService` | Snapshot cached 15s; `?fresh=true` bypasses. Freshness/staleness handled correctly (stale → Unknown, never stale-healthy). | Keep; verify in tests. |

**Positive:** no check hardcodes `Healthy`; `Disabled`/`Unknown` used deliberately; staleness converts to `Unknown`; incidents use consecutive confirmations + cooldown and dispatch `PlatformIncidentOpened/Resolved`.

---

## 6. Backups & disaster recovery (WS5)

| Sev | Location | Finding | Remediation |
| --- | --- | --- | --- |
| P1 | repo | No backup/restore scripts or runbook for central + tenant databases or file storage. | Add scripts + runbook (`docs/production/backup-restore-runbook.md`); define RPO/RTO. |
| P2 | repo | No evidence of a restore drill; health has no backup-freshness signal. | Document drill; consider a backup-freshness health signal. |

Infra-dependent (see §10): real backup and restore cannot be executed here.

---

## 7. Dependencies, migrations, deployment & rollback (WS6)

| Sev | Location | Finding | Remediation |
| --- | --- | --- | --- |
| P1 | repo | No deployment/rollback runbook; no immutable-release or migration-rehearsal procedure. | Add `docs/production/deployment-rollback-runbook.md`. |
| P2 | repo | Dependency audits not recorded in-repo. | Run `composer validate/audit`, `npm audit`; record results + accepted exceptions. |
| P2 | repo | Central + tenant migrations must be rehearsed against staging with rollback path. | Document rehearsal procedure; use expand-and-contract for risky changes. |

CI exists (`.github/workflows/ci.yml`) with a PG-tagged job scaffold; frontend has no test runner.

---

## 8. Email delivery (WS7)

| Sev | Location | Finding | Remediation |
| --- | --- | --- | --- |
| P1 | `AuthService.php:665` | Password-reset mail is commented out — reset emails never send; token is returned instead (P0 in §2). | Implement mail send; verify with real provider in staging. |
| P2 | `AuthService` invite/TODO | Invitation email TODO (`TenantService.php:366`). | Implement and verify. |
| P2 | `.env.example:60` | `MAIL_MAILER=log` default — never delivers. | Production must use a supported transport; validator enforces. |

Real delivery requires a provider + staging mailbox (see §10); in-repo evidence limited to `Mail::fake()`.

---

## 9. Scheduler / queue inventory (reference)

Scheduled commands (`routes/console.php`): `widget:cleanup-sessions` (daily + weekly, no overlap guard), `billing:process` (renew/expire, reminders, trial-reminders, reset-usage), `billing:sync-storage`, `billing:reconcile`, `health:check`, `health:heartbeat`, `health:prune`, `notifications:prune`. No duplicate registrations detected; commands exist.

Queue: default `database`, fallback `database→deferred`; failed driver `database-uuids`; jobs carry explicit `$tries`/`$timeout` except the noted gaps.

---

## 10. Operational tasks that cannot be executed from the repository (explicitly NOT done)

These are recorded as deployment/runbook tasks, not completed work:

1. **Staging deployment** of the candidate release (no infra in repo; no host access).
2. **Real email delivery** through a production/staging provider (needs provider + mailbox/domain).
3. **Backup restore drill** into an isolated environment (needs live PG + backup artifacts).
4. **Deployment rollback drill** (needs a real prior release + staging).
5. **Alert delivery verification** to the configured channel (needs channel credentials).
6. **Production secret rotation / secret-store setup** (needs the secret manager).

Each is captured with exact commands and evidence requirements in the WS5–WS7 runbooks.

---

## 11. Remediation plan (mapped to workstreams)

- **WS1** — `backend/.env.example` hardening; `php artisan app:production-check` validator (env, debug, keys, price mappings, payment mode, mail transport, CORS); remove `temp_eval8.php`; secret-management doc; tests.
- **WS2** — fix redis duplicate key, failed/batching connection, widget schedule overlap; retry/backoff + timeout for billing jobs; duplicate-registration test.
- **WS3** — remove reset-token leak + mail send; cross-tenant guards on tenant mutations; TenantAsset auth; route `permission:` middleware restoration; auth rate limits; webhook `payment_status` assertion; security-headers middleware; sanitize error messages; IDOR regression tests.
- **WS4** — redact AI key from health metadata; default AI probe off in prod template; health freshness tests.
- **WS5** — backup/restore scripts + runbook; RPO/RTO; drill checklist.
- **WS6** — dependency audit record; migration/deploy/rollback runbooks; expand-and-contract guidance.
- **WS7** — mail provider config + invitation/reset mail; staging acceptance runbook + sign-off checklist.
