# Production Secrets & Configuration

- **Scope:** Phase 6 WS1 — how configuration and secrets are supplied, validated
  and rotated in production.
- **Applies to:** `backend/` (Laravel 13) and the deployed frontend (`frontend/`).

> Operational items in this document that require real infrastructure (secret
> store provisioning, key rotation in a live environment) are **runbook steps**,
> not assertions that they have been performed. This repository cannot execute
> them; see §5 for status.

---

## 1. Configuration sources

| Environment | Configuration source |
| --- | --- |
| Local development | `backend/.env` (git-ignored) |
| CI | `.github/workflows/ci.yml` + generated `.env` with throwaway secrets |
| Production | Deployment **secret store** injected as environment variables (see §2) |

Rules:

1. **Never commit secrets.** `backend/.env` is git-ignored; only `backend/.env.example`
   (a template with placeholders) is tracked.
2. The production `.env` (if one exists on the host) must be owned by the deploy
   user with `chmod 600` and must never be readable by the web process group
   beyond what is required.
3. Configuration is cached at deploy time (`php artisan config:cache`). After
   changing any environment variable the cache **must** be rebuilt.

---

## 2. Required production environment variables

Copy `backend/.env.example` as a starting point and set the following. Values
in **bold** are mandatory; the rest are required depending on enabled features.

| Variable | Production value | Notes |
| --- | --- | --- |
| `APP_ENV` | **`production`** | Validator fails otherwise. |
| `APP_DEBUG` | **`false`** | Validator fails if `true`. |
| `APP_URL` | **`https://<api-host>`** | Must be HTTPS. |
| `APP_KEY` | **generated** | `php artisan key:generate` (stored in secret store). |
| `APP_PREVIOUS_KEYS` | optional | Only during key rotation (§4). |
| `JWT_SECRET` | **generated** | `php artisan jwt:secret`. |
| `FRONTEND_URL` | **`https://<app-host>`** | Used for redirects/links. |
| `CORS_ALLOWED_ORIGINS` | **explicit origins** | Never `*`. |
| `DB_CONNECTION` | `pgsql` | Managed Postgres, not sqlite. |
| `DB_HOST` / `DB_PORT` | **set** | |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | **set** | No weak defaults (`root`/`forge` removed). |
| `DB_SSLMODE` | `require` | Encrypt in transit. |
| `SESSION_SECURE_COOKIE` | `true` | |
| `SESSION_ENCRYPT` | `true` | |
| `MAIL_MAILER` | `smtp` \| `ses` \| `postmark` \| `resend` | Never `log`/`array`. |
| `MAIL_FROM_ADDRESS` | real address | Not `@example.com`. |
| `QUEUE_CONNECTION` | `redis` or `database` | Match the worker config. |
| `CACHE_STORE` | `redis` or `database` | Never `array`. |
| `FILESYSTEM_DISK` | `s3` (recommended) | Local disk is ephemeral on most hosts. |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stack` / `warning`+ | Avoid `debug` in production. |

### Feature-gated variables

| Feature | Variables | Enforced when |
| --- | --- | --- |
| Stripe | `STRIPE_ENABLED`, `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` | `STRIPE_ENABLED=true` — validator rejects `sk_test_*` and missing webhook secret in production. |
| PayPal | `PAYPAL_ENABLED`, `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID`, `PAYPAL_MODE=live` | `PAYPAL_ENABLED=true` — validator rejects `sandbox`. |
| AI | `AI_ENABLED`, `AI_PROVIDER`, matching `*_API_KEY` | `AI_ENABLED=true` — validator requires a key for the selected provider. |
| AI health probe | `HEALTH_AI_PROBE=false` | Keep disabled unless live provider probing is intended. |

> `STRIPE_ENABLED` defaults to `true` in `config/billing.php`. If Stripe is not
> used in a given environment it **must be set to `false` explicitly**, otherwise
> the pre-deploy check will fail on the missing Stripe secrets.

---

## 3. Pre-deploy validation

Run before every production deploy (also safe to wire into the release script):

```bash
php artisan config:clear
php artisan config:cache
php artisan app:production-check
```

- Exit code `0` = no blocking errors. Warnings may still be printed.
- Exit code `1` = at least one **error** (unsafe configuration); the deploy must
  be aborted.
- `--allow-non-production` downgrades the `APP_ENV` mismatch to a warning so the
  check can be dry-run in staging/CI. Wildcard CORS and debug mode remain hard
  errors.

The check validates: debug/key/JWT, HTTPS URL, primary database (rejects sqlite),
cache/filesystem, mail transport + sender, secure/encrypted sessions, CORS,
Stripe/PayPal when enabled, AI provider keys when enabled, and placeholder /
duplicate provider **plan price** mappings.

The validator lives in `app/Support/Ops/ProductionConfigValidator.php`
(pure, unit-tested); the command in
`app/Console/Commands/Ops/ProductionCheckCommand.php`.

---

## 4. Secret rotation

| Secret | Rotation procedure |
| --- | --- |
| `APP_KEY` | 1) Add the current key to `APP_PREVIOUS_KEYS`. 2) Generate/install the new `APP_KEY`. 3) `config:cache`, restart workers. 4) After the re-encryption window, clear `APP_PREVIOUS_KEYS`. **Rotating `APP_KEY` invalidates encrypted columns/session payloads** — coordinate with a maintenance window. |
| `JWT_SECRET` | Generate a new secret and restart; **all existing JWT access/refresh tokens become invalid** (users must re-authenticate). Prefer a short maintenance window. |
| `DB_PASSWORD` | Rotate in the database, update the secret, `config:cache`, restart PHP-FPM + queue workers. |
| Stripe/PayPal/AI keys | Rotate in the provider dashboard, update the secret, `config:cache`, restart workers. Update the webhook signing secret in both the provider and the secret store atomically. |
| `MAIL_*` credentials | Rotate with the mail provider, update the secret, restart workers. |

General rules: secrets are stored only in the secret store; rotate on a schedule
and immediately after any suspected exposure; never log secret values; verify
with `app:production-check` and a smoke test after each rotation.

---

## 5. Execution status (evidence required)

The following require real infrastructure and are **not verified by this
repository**. Record evidence when performed in the target environment:

- [ ] Secret store chosen and provisioned (e.g. AWS Secrets Manager, Vault, Doppler) — **not executed**.
- [ ] All §2 variables present in the production secret store — **not executed**.
- [ ] `php artisan app:production-check` returns exit `0` against production config — **not executed** (requires production env).
- [ ] `APP_KEY` and `JWT_SECRET` generated and stored — **not executed**.
- [ ] Rotation drill performed in staging — **not executed**.

<!-- Verified in-repo: template hardening, credential-default removal, validator + command, unit/feature tests, debug script removal. -->
