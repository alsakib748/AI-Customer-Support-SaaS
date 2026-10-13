# Frontend lint baseline

Recorded 2026-10-13 (Phase 6, WS6/WS7).

Tool: ESLint 8.57 with `eslint-plugin-vue` (as pinned in `frontend/package.json`),
Node 22.17.0. Command:

```bash
cd frontend
npx eslint . --ext .vue,.js,.jsx,.cjs,.mjs --ignore-path .gitignore
```

## Summary

- **25 errors** and **25 warnings** across **19 pre-existing files**.
- None of these files were changed by Phase 6 except
  `src/views/conversations/ConversationShow-Main.vue` (see "Fixed" below).
- `npm run build` passes; the build is the only full-app automated frontend gate
  (there is no frontend test runner — tracked as a gaps item).

## Fixed during Phase 6

`src/views/conversations/ConversationShow-Main.vue:131` used `toast.success(...)`
while the `useToast` import, the `toast` instance and `app.use(ToastService)`
(`src/main.js`) are all commented out. This was not a cosmetic lint error — it
threw `ReferenceError: toast is not defined` at runtime when a conversation was
closed. The orphaned call was commented out to match the rest of the file and the
app's disabled toast service. `npx eslint` on the changed file passes.

(Original baseline was 26 errors; this one real defect was fixed, leaving 25.)

## Remaining errors

| File:line | Rule |
| --- | --- |
| public/widget/chat.js:7:11 | no-unused-vars |
| src/components/analytics/DateRangePicker.vue:3:10 | no-unused-vars |
| src/components/analytics/ExportDialog.vue:7:7 | no-unused-vars |
| src/components/widgets/WidgetStatistics.vue:5:7 | no-unused-vars |
| src/views/ai/AIAnalytics.vue:3:15 | no-unused-vars |
| src/views/analytics/ConversationAnalytics.vue:3:20 | no-unused-vars |
| src/views/billing/BillingOverview.vue:93:17 | no-empty |
| src/views/billing/InvoiceShow.vue:2:10 | no-unused-vars |
| src/views/billing/InvoiceShow.vue:10:7 | no-unused-vars |
| src/views/billing/Invoices.vue:9:7 | no-unused-vars |
| src/views/billing/Invoices.vue:10:7 | no-unused-vars |
| src/views/customers/CustomerList.vue:57:7 | no-unused-vars |
| src/views/knowledge-base/KnowledgeBase.vue:177:7 | no-unused-vars |
| src/views/pages/auth/Register.vue:107:28 | no-prototype-builtins |
| src/views/pages/setup/CreateWorkspace.vue:2:10 | no-unused-vars |
| src/views/settings/AISettings.vue:36:7 | no-unused-vars |
| src/views/settings/AISettings.vue:168:7 | no-unused-vars |
| src/views/settings/ChatWidgets.vue:28:7 | no-unused-vars |
| src/views/settings/ChatWidgets.vue:62:7 | no-unused-vars |
| src/views/tickets/Tickets.vue:71:7 | no-unused-vars |
| widget/src/App.vue:139:7 | no-unused-vars |
| widget/src/App.vue:380:25 | no-case-declarations |
| widget/src/App.vue:390:25 | no-case-declarations |
| widget/src/App.vue:402:25 | no-case-declarations |
| widget/src/vite.config.js:5:8 | no-unused-vars |

By rule: `no-unused-vars` ×20, `no-case-declarations` ×3, `no-empty` ×1,
`no-prototype-builtins` ×1.

## Severity assessment

- `no-unused-vars` (20) and `no-empty` (1) — dead code; no runtime impact, safe
  to clean up opportunistically.
- `no-case-declarations` (3, `widget/src/App.vue`) — lexical declarations inside
  `case` blocks can cause temporal-dead-zone/redeclaration bugs; worth fixing
  deliberately.
- `no-prototype-builtins` (`Register.vue:107`) — a direct `hasOwnProperty` call;
  low risk but worth hardening to `Object.prototype.hasOwnProperty.call(...)`.

## Control (prevent new errors)

- CI (`.github/workflows/ci.yml`, frontend job) lints **only the files changed in
  the push/PR**, so a new error in any touched file fails the build. The
  pre-existing baseline is not linted on unrelated changes.
- Rule for contributors: the baseline in this document is a **ratchet**. It must
  never grow. When you touch one of the files above, fix that file's errors.
- Optional hardening (not done): add a CI step that fails when the total error
  count exceeds this baseline.

## Remediation

| Field | Value |
| --- | --- |
| Owner | Frontend Platform maintainer (named in the cleanup change ticket) |
| Deadline | 2026-11-13 (30 days) |
| Scope | remove dead imports/vars, fix `no-case-declarations` and `no-prototype-builtins`, resolve the remaining warning set |
| Note | the fixed `no-undef` bug is evidence the baseline is not purely cosmetic; treat the remaining items as real cleanup, not noise |
