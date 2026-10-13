# Email delivery runbook

Phase 6, Workstream 7 (WS7).

> **Status: application-level mail flows implemented and unit-tested in-repo;
> real delivery NOT verified.** Verifying delivery needs a mail provider and a
> staging mailbox/domain, which are not available from this repository. The
> steps in section 4 are the acceptance procedure and its required evidence.

---

## 1. Mail flows

| Flow | Mailable | Trigger | Queued |
| --- | --- | --- | --- |
| Password reset | `App\Mail\PasswordResetMail` | `AuthController::forgotPassword` | yes (`ShouldQueue`) |
| Team invitation (token) | `App\Mail\TeamInvitationMail` | `Team\InvitationService` create/resend | yes |
| Added to a workspace | `App\Mail\TenantMemberAddedMail` | `TenantService::inviteUser` | yes |
| Weekly analytics report | `App\Mail\WeeklyAnalyticsReportMail` | scheduled reporting | yes |
| SMTP self-test | `App\Mail\SystemSettingsTestMail` | admin settings test action | yes |

All mailables use `Mail::to(...)->queue(...)`. For a message to actually leave
the system, a **queue worker must be running** (see
`monitoring-and-alerting.md`); `QUEUE_CONNECTION=sync` is test-only.

Behaviour guarantees:

- Password-reset flow never returns the token in the API response and never
  reveals whether an email exists (covered by `PasswordResetSecurityTest`).
- `TenantService::inviteUser` sends the notification best-effort: a mail failure
  is logged and does **not** roll back the membership that was created (covered
  by `TenantInvitationEmailTest`).

---

## 2. Required production configuration

```dotenv
MAIL_MAILER=smtp            # or ses | postmark | resend — NEVER "log"
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="no-reply@<verified-domain>"
MAIL_FROM_NAME="${APP_NAME}"
```

`php artisan app:production-check` rejects `MAIL_MAILER=log` and a missing or
example `MAIL_FROM_ADDRESS`, so a misconfigured release fails the pre-deploy gate
(see `deployment-rollback-runbook.md`). Credentials come from the host secret
manager, never from a committed `.env`.

Domain requirements (set by the mail/brand owner, not in-repo):

- SPF record authorising the sending provider.
- DKIM signing enabled and verified for the sending domain.
- DMARC policy published; start at `p=none` with reporting, then tighten.
- A verified From domain (avoids `hello@example.com` being rejected/spam-foldered).

---

## 3. Common failure modes

| Symptom | Cause | Fix |
| --- | --- | --- |
| Mails never arrive, logs show "logged" | `MAIL_MAILER=log` | set a real transport |
| Queued jobs pile up, nothing sent | no queue worker | start workers / `queue:restart` |
| Provider rejects sender | unverified From domain / missing SPF/DKIM | verify domain |
| Reset link works but mail not delivered | spam filtering | publish DMARC, verify DKIM |
| Invitation mail missing but membership exists | SMTP failure logged | inspect logs; resend |

---

## 4. Acceptance verification (NOT executed here)

On staging, with a real provider and a mailbox you control:

```bash
# 1. Transport self-test from the admin settings action, or:
php artisan tinker --execute="Mail::to('qa@<your-domain>')->queue(new App\\Mail\\SystemSettingsTestMail(...));"

# 2. Ensure the queue is actually being processed.
php artisan queue:work --once

# 3. Trigger a password reset for a real staging account and confirm the email
#    arrives with a working reset link.

# 4. Invite an existing user to a tenant (TenantService::inviteUser path) and
#    confirm TenantMemberAddedMail arrives with a working dashboard link.
```

Evidence to capture per flow:

- [ ] Message received in the mailbox (screenshot/headers with timestamp).
- [ ] `Received-SPF: pass` and `DKIM: pass` (or provider delivery report).
- [ ] Reset link completes a password change; old password no longer works.
- [ ] Invitation mail links land on the dashboard for the invited user.
- [ ] No token or credential appears in application logs.

Sign-off is recorded on the staging acceptance checklist
(`docs/production/staging-acceptance-runbook.md`).

---

## 5. Regression coverage (in-repo)

- `tests/Feature/Ops/PasswordResetSecurityTest.php` — reset mail is queued, token
  never returned, no user enumeration.
- `tests/Feature/Ops/TenantInvitationEmailTest.php` — membership persisted and
  `TenantMemberAddedMail` queued with the expected link; mailable renders.
- `tests/Feature/Team/TeamInvitationTest.php` — token invitation mail queued.
