# Phase 2 — Billing & Payments Trustworthiness: Release Report

Branch: `fix/phase-2-billing-trust`
Base: `8aa0865` (Phase 1 frozen)
Scope decision: **Stripe only.** PayPal paths are left intact but were not extended.

## Summary

Phase 2 makes billing trustworthy by making the **payment provider the source of truth**, enforcing **database-level idempotency**, and **never trusting client-supplied success signals**. Payment, Subscription, Invoice and Entitlements responsibilities were kept separate across eight reviewable commits.

## Commits

| Commit | Stage | Change |
| --- | --- | --- |
| `f10b480` | 2 | Block unverified paid subscription activation |
| `0279ec8` | 2 | Verify checkout amount, currency, and tenant before activation |
| `5007f33` | 2 | Require provider-authoritative plan changes |
| `bd7648f` | 3 | Harden Stripe webhook idempotency and amount reconciliation |
| `3e55e90` | 4 | Sync Stripe subscription price, periods, and cancellation state |
| `dcc15b7` | 5 | Enforce entitlement with past-due grace and paid-through access |
| `15d172a` | 6 | Generate tenant-scoped invoice PDFs on download |
| `274c43b` | 7 | Make renewal and trial reminders idempotent |

## What changed by stage

### Stage 2 — Payment integrity & checkout
- Paid plans can no longer be activated directly; `SubscriptionService::createSubscription` requires an explicit `$allowPaid` flag (`f10b480`).
- Checkout verification binds the provider session to the tenant (`metadata.tenant_id`), validates status, **amount** (vs `plan->getPriceForCycle`) and **currency**, and returns a hard error on mismatch (`0279ec8`).
- Upgrades/downgrades must go through the provider: provider-managed subscriptions use `changeSubscription` (with proration on upgrade); paid→free downgrades require cancellation; locally-managed paid plans require checkout (`5007f33`).

### Stage 3 — Secure, idempotent webhooks
- `WebhookController` performs an atomic `received → processing` claim; only the claiming worker dispatches the job. Duplicate deliveries are logged without exposing the raw payload (`bd7648f`).
- Invoice creation is idempotent (keyed on provider invoice id); payment failures use `updateOrCreate`; amount mismatches are recorded on invoice metadata instead of silently trusted.
- Migration `2026_10_12_000001` adds unique indexes on provider payment/invoice ids and webhook-event bookkeeping columns (`attempts`, `last_error`, `received_at`).

### Stage 4 — Subscription sync
- `SyncSubscriptionFromProviderJob` now syncs price/plan, billing periods, trial dates, and cancellation state, and only writes changed attributes (`3e55e90`).

### Stage 5 — Central entitlement enforcement
- `Subscription::grantsAccess()` + `pastDueWithinGrace()` and `SubscriptionService::getEntitlingSubscription()` honour the `billing.grace_period.past_due_days` window and paid-through access for subscriptions cancelled at period end (`dcc15b7`).
- `CheckSubscription` middleware is now applied to the authenticated tenant route group; `BillingLimitService` and `CustomerService` enforce entitlements through the same path. Customer creation is now limited by `customers.max`.

### Stage 6 — Invoice PDF & payment history
- `InvoiceService::renderPdf()` renders a Blade invoice to PDF via `dompdf/dompdf` (no external service). `InvoiceController::download` streams `application/pdf`, still preferring a provider-hosted URL when present, and remains tenant-scoped (`15d172a`).
- Payment history endpoints (`PaymentController::index` / `statistics`) were already tenant-scoped; verified by tests.

### Stage 7 — Reminder idempotency
- New central `billing_reminders` table (migration `2026_10_12_000002`) with a unique claim key. Renewal and trial jobs claim before sending and release on delivery failure, so repeat scheduler runs never double-send (`274c43b`). Renewal reminders also fall back to `current_period_ends_at` when `next_billing_at` is null.

## Verification

- Full backend suite: **157 tests / 494 assertions passing**.
- New coverage: `PaymentIntegrityTest`, `CheckoutVerificationTest`, `PlanChangeTest`, `WebhookHardeningTest`, `SubscriptionSyncTest`, `EntitlementGraceTest`, `InvoicePdfTest`, `ReminderIdempotencyTest`.
- `php -l` clean on all touched PHP files.
- `php artisan migrate:status` shows the two new migrations **Pending** (not yet applied to the local dev database — apply on deploy).

## Known limitations / deliberately deferred

- **PayPal**: not extended (per scope decision). Existing PayPal code remains functional but the new guarantees (checkout verification, sync) are Stripe-first.
- `PaymentService::processPayment()` / `createPayment()` simulate completion and are **not referenced by any controller or job**. They were left as-is to avoid scope creep; recommend removing or wiring to a real gateway before any future use (F8/F18).
- Invoice-number generation remains application-level with a unique constraint as a backstop (F14).

## Deploy notes

1. `composer install` (adds `dompdf/dompdf`).
2. `php artisan migrate --force` to apply the two Phase 2 migrations.
3. Ensure the queue worker and scheduler run `SendRenewalReminders` / `SendTrialEndingReminder`; idempotency now makes re-runs safe.
