# Dependency audit record

Phase 6, Workstream 6 (WS6).

Audit performed locally on 2026-10-13 with:

| Tool | Version |
| --- | --- |
| PHP | 8.3.30 |
| Composer | 2.8.8 |
| Node | 22.17.0 |
| npm | 10.9.2 |

CI runs the backend test suite on PHP 8.3 and the frontend build on Node 20
(`.github/workflows/ci.yml`).

---

## 1. Backend (Composer)

Command:

```bash
composer validate --strict   # "composer.json is valid"
composer audit --format=plain
```

### Before

`composer audit` reported **3 advisories affecting 2 packages**:

| Package | Severity | Advisory | Affected |
| --- | --- | --- | --- |
| laravel/framework | low | CVE-2026-102279 — XSS in Debug Page Information (GHSA-jh5r-qr3c-85q8) | >=13.0.0,<13.30.0 |
| league/commonmark | medium | PKSA-m2dq-1fhr-29b1 — DisallowedRawHtml bypass (GHSA-97jj-33gv-5xf9) | >=1.3.0,<=2.10.1 |
| league/commonmark | high | PKSA-m4t9-vsgq-8khn — quadratic-time DoS in GFM table scan (GHSA-3q6v-r5mr-hxv8) | >=2.0.0,<=2.10.1 |

Installed at the time: `laravel/framework v13.27.0`, `league/commonmark 2.10.0`.

### Remediation

```bash
composer update laravel/framework league/commonmark --with-dependencies
```

Result: `laravel/framework v13.27.0 → v13.35.0`, `league/commonmark 2.10.0 →
2.10.3`, plus 35 transitive patch/minor updates (Symfony 7.4.20, Guzzle 8.2.0,
Monolog 3.12.1, Carbon 3.14.2, `brick/math 0.18.0 → 1.0.0`, and others).

### After

```bash
composer audit --format=plain   # "No security vulnerability advisories found."
```

Validation: full backend suite **438 passed / 1484 assertions**.

Accepted exceptions: none.

---

## 2. Frontend (npm)

Command:

```bash
npm audit --json
npm audit fix          # non-breaking only
npm run build
```

### Before

**25 vulnerabilities** — 1 low, 3 moderate, 21 high, 0 critical. Direct
dependencies affected included `axios`, `dompurify`, `vite`, `vue` and
`unplugin-vue-components`.

### Remediation

`npm audit fix` (no `--force`) applied all fixes available within existing
semver ranges; `package.json` was unchanged (the fixed versions already
satisfied the declared ranges).

### After

**9 vulnerabilities remain** — 3 moderate, 6 high, 0 critical. All require
semver-major upgrades and are recorded as accepted exceptions below.

| Package | Severity | Fixed in | Notes |
| --- | --- | --- | --- |
| vite | high | 8.3.4 (major) | dev server/build tool only |
| esbuild | moderate | via vite 8.3.4 | transitive of vite (dev server) |
| unplugin-vue-components | high | 32.1.0 (major) | build-time only |
| braces, chokidar, fast-glob, micromatch | high | via unplugin-vue-components 32.1.0 | transitive build-time deps |
| eslint-plugin-vue | moderate | 10.11.1 (major) | lint-time only |
| postcss-selector-parser | moderate | via eslint-plugin-vue 10.11.1 | transitive lint dep |

### Accepted exceptions — rationale

- Every remaining advisory is in **development/build tooling**; none ships in the
  runtime bundle, so there is no production code-execution surface.
- The remaining advisories require **semver-major** upgrades (`vite` 8,
  `unplugin-vue-components` 32, `eslint-plugin-vue` 10) that must be staged and
  regression-tested as a dedicated toolchain upgrade, not smuggled into a
  security-hardening release.
- Interim mitigation: the Vite dev server must stay bound to localhost/trusted
  networks (relevant to the esbuild advisory), and is never exposed in
  production.

Follow-up: schedule `npm audit fix --force` (or a manual toolchain bump) in a
dedicated change with a full build + smoke test.

### Frontend validation state

- `npm run build` — **passes**.
- `npm run lint:ci` — **fails with 26 pre-existing errors / 25 warnings** in
  existing source files (`frontend/widget/src/App.vue`, several views). These are
  unrelated to the audit fix (no source files were touched) and were not
  introduced by WS6. The frontend still has no test runner; the build is the only
  automated frontend gate.

---

## 3. CI workflow

While auditing, a defect was found in `.github/workflows/ci.yml`: the `frontend`
job declared `steps:` twice (an invalid/duplicate mapping key that would break
the frontend job). This was corrected; the workflow now parses with jobs
`backend`, `frontend` (6 steps) and `backend-postgres`.

The PostgreSQL-gated job remains opt-in via the repository variable
`ENABLE_POSTGRES_CI=true`; PG-specific behaviour is still only validated when
that flag is enabled. Running it is a recommended pre-release gate.
