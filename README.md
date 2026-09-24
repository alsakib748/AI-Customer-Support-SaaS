<div align="center">

# 🤖 AI Customer Support SaaS

> A **multi-tenant, AI-powered customer support platform** that automates customer conversations, empowers human agents, organizes knowledge, tracks performance, and monetizes support through subscriptions — built with a Laravel + Vue.js separation of concerns.

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-3.4-4FC08D?logo=vuedotjs&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![JWT](https://img.shields.io/badge/Auth-JWT-000000)
![License](https://img.shields.io/badge/License-MIT-brightgreen)

**Backend** · [Laravel](https://laravel.com) REST API · **Frontend** · [Vue 3](https://vuejs.org) SPA

</div>

---

## 📖 Table of Contents

- [🤖 AI Customer Support SaaS](#-ai-customer-support-saas)
  - [📖 Table of Contents](#-table-of-contents)
  - [📌 Overview](#-overview)
  - [✨ Key Features](#-key-features)
    - [🤖 AI-Powered Support](#-ai-powered-support)
    - [💬 Conversation Management](#-conversation-management)
    - [👥 Customers](#-customers)
    - [🎫 Ticket Management](#-ticket-management)
    - [📚 Knowledge Base](#-knowledge-base)
    - [🌐 Chat Widget](#-chat-widget)
    - [📊 Analytics](#-analytics)
    - [💳 Billing \& Subscriptions](#-billing--subscriptions)
    - [👥 Team \& RBAC](#-team--rbac)
    - [⚙️ Platform Administration (SaaS operations)](#️-platform-administration-saas-operations)
  - [🧩 Product Modules](#-product-modules)
  - [🔁 How It Works](#-how-it-works)
  - [🏗️ System Architecture](#️-system-architecture)
  - [🏢 Multi-Tenant Architecture](#-multi-tenant-architecture)
  - [🔐 Authentication \& Authorization](#-authentication--authorization)
    - [Authentication — JWT](#authentication--jwt)
    - [Roles](#roles)
    - [Authorization model](#authorization-model)
  - [🧠 AI Architecture](#-ai-architecture)
  - [📚 Knowledge Base \& Retrieval](#-knowledge-base--retrieval)
  - [💳 Payment \& Billing](#-payment--billing)
  - [📊 Analytics](#-analytics-1)
  - [🧰 Technology Stack](#-technology-stack)
  - [📁 Project Structure](#-project-structure)
  - [🗃️ Database Architecture](#️-database-architecture)
  - [🔌 API Structure](#-api-structure)
  - [🚀 Installation](#-installation)
    - [Requirements](#requirements)
    - [Clone](#clone)
    - [Backend](#backend)
    - [Frontend](#frontend)
    - [Build for production](#build-for-production)
  - [⚙️ Environment Configuration](#️-environment-configuration)
  - [▶️ Running the Application](#️-running-the-application)
  - [🧪 Testing](#-testing)
  - [🔐 Security](#-security)
  - [☁️ Deployment](#️-deployment)
  - [🔀 Development Workflow](#-development-workflow)
  - [🗺️ Roadmap](#️-roadmap)
    - [Completed](#completed)
    - [In Progress](#in-progress)
    - [Planned](#planned)
  - [🤝 Contributing](#-contributing)
  - [📄 License](#-license)

---

## 📌 Overview

Businesses receive customer questions from many places at once. Traditional support stacks force agents to manually answer repetitive questions, dig through scattered documentation, and juggle conversations across disconnected tools.

This platform combines **AI-powered support automation**, **human agent collaboration**, **knowledge management**, **ticket management**, **analytics**, and an **embeddable chat widget** into one unified, multi-tenant SaaS product.

Each customer workspace (tenant) gets its own isolated database, its own AI configuration, its own knowledge base, and its own team — while a **platform administration** layer manages users, tenants, billing, and RBAC at the SaaS level.

---

## ✨ Key Features

### 🤖 AI-Powered Support
- Automated AI responses to customer messages
- **Streaming responses** (SSE) rendered token-by-token
- Configurable AI provider, model, and **temperature**
- Custom **system prompts** per tenant
- Agent tool-calling: create tickets, fetch customers, search the knowledge base
- Automatic **human handoff / escalation** for complex issues
- AI usage tracking, request limits, and plan-limit enforcement

### 💬 Conversation Management
- Customer conversations from the chat widget **or** created by agents
- Status workflow (open → pending → resolved / closed) with resolve / reopen / close
- **Agent assignment** and unassignment
- Internal **notes** and AI / system messages
- Full message audit on the customer timeline

### 👥 Customers
- CRUD with soft delete, restore, and force delete
- Block / unblock, custom tag management, bulk delete
- Customer analytics (lifetime value, activity, conversation history)

### 🎫 Ticket Management
- Full lifecycle: open → assigned → in progress → pending → resolved → closed
- Priorities, assignment, and unassignment
- Public **comments** + internal notes
- Optional AI-driven ticket creation from conversations

### 📚 Knowledge Base
- Categories and articles with draft / published / archived workflow
- **Visibility scopes** control whether an article is AI-visible
- **AI search endpoint** that feeds only relevant, published, visible articles to the model
- RAG-ready infrastructure (see [Knowledge Base & Retrieval](#knowledge-base--retrieval))

### 🌐 Chat Widget
- Embeddable, lightweight website widget (single snippet)
- Public sessions / bootstrap endpoints with **origin validation** and **rate limiting**
- Automatic customer + conversation creation from widget visitors
- Per-widget management: enable / disable, regenerate keys, installation code
- Widget analytics (sessions, messages, resolution)

### 📊 Analytics
- Overview dashboard with KPIs
- Conversation, customer, agent, ticket, AI, widget, and knowledge-base analytics
- **CSV export** builder + export history (queued generation)

### 💳 Billing & Subscriptions
- Plans with **per-provider prices** (Stripe and PayPal)
- Subscribe, upgrade, downgrade, cancel, resume, and coupon validation
- Invoices, payments, prorated billing, and refunds
- Provider **webhooks as the source of truth**, signature-verified and idempotent
- Usage tracking with plan limits (AI requests, agents, customers, storage, …)

### 👥 Team & RBAC
- Multi-user workspaces with invitations (accept / resend / revoke)
- **Spatie Permission** with team-scoped roles
- Roles: `super_admin` (platform) · `owner` · `admin` · `manager` · `support_agent` (tenant)
- Permission-driven API middleware + frontend route/permission gating
- **Protected system accounts** — Super Admins cannot be edited, suspended, or modified, even by other admins

### ⚙️ Platform Administration (SaaS operations)
- Tenant lifecycle: activate, suspend, archive, restore, retry provisioning, transfer ownership
- Platform user management with sessions revocation
- RBAC dashboard to manage roles, permissions, and mappings
- Billing administration: plans, prices, coupons, subscriptions, invoices, payments, refunds
- Platform / tenant usage analytics

---

## 🧩 Product Modules

```
Platform
│
├── Authentication            JWT login, registration, verification, recovery
├── Multi-Tenancy             Per-tenant databases + tenant switching
├── Workspace                 Branding, logo/favicon, business hours, stats
├── Team Management           Members, invitations, departments
├── Customer Management       CRM-style customer lifecycle + tags
├── Conversation Management   Channel-agnostic conversations + statuses
├── Message Management        Messages, internal notes, AI streaming
├── Ticket Management         Support tickets, priorities, comments
├── Knowledge Base            Categories, articles, publishing, AI search
├── AI Support                Streaming replies, tools, escalation, limits
├── Chat Widget               Embeddable public widget + management
├── Analytics                 Dashboards + CSV exports
├── Billing                   Plans, subscriptions, invoices, payments
├── RBAC                      Roles, permissions, assignments
└── Platform Administration   Tenants, users, billing, analytics oversight
```

---

## 🔁 How It Works

```
Customer
   │
   ▼
Chat Widget          (embeddable snippet on the customer's site)
   │
   ▼
Conversation         (auto-created, routed to the tenant workspace)
   │
   ▼
AI Support Agent     (streams a reply using tenant config)
   │
   ├── Customer / conversation context
   ├── Knowledge base retrieval (visible, published articles)
   └── Tools (fetch customer, create ticket, escalate)
   │
   ▼
Answer resolved? ─── Yes ──► AI Response (resolved, optional close)
   │
   No
   ▼
Human Agent          (assigned via conversation workflow)
   │
   ▼
Resolution           (closed with full audit trail)
```

**Design intent:** the AI answers the routine 80% instantly, preserving full conversation history for handoff; agents focus on the complex 20%.

---

## 🏗️ System Architecture

The product is split into a **stateless REST API** (`backend/`) and a **Vue SPA** (`frontend/`) that talks to it exclusively over JSON.

```
                     ┌─────────────────────┐
                     │   Marketing SPA      │  (static Vue pages: landing, plans)
                     └──────────┬──────────┘
                                │
                                ▼
                     ┌─────────────────────┐
                     │      SPA (Vue)       │  Pinia stores · axios · PrimeVue
                     │  auth + feature UIs  │
                     └──────────┬──────────┘
                                │  JWT Bearer
                                ▼
                     ┌──────────────────────┐
                     │   Laravel REST API    │  /api/v1
                     │  v1 (tenant + admin)  │
                     └──────────┬───────────┘
               ┌────────────────┼──────────────────┐
               ▼                ▼                  ▼
      ┌──────────────┐   ┌─────────────┐   ┌──────────────┐
      │  PostgreSQL   │   │  AI Provider │   │ Queue/Queue   │
      │ central + N   │   │ (OpenAI,     │   │ workers, jobs │
      │ tenant DBs    │   │ Gemini, …)   │   │ + scheduler   │
      └───────┬──────┘   └──────┬──────┘   └──────────────┘
              │                 │
              │                 ▼
              │        Knowledge Base context (RAG-ready layer)
              │
              ▼
   MySQL/Postgres per-tenant data, isolated by database
```

Cross-cutting layers in the backend: **JWT authentication middleware**, **tenant-aware context**, **permission middleware**, **rate limiting** (widget / AI / billing), **audit logging**, and **queued jobs** for provisioning, billing, exports, and AI processing.

---

## 🏢 Multi-Tenant Architecture

Each **tenant owns a dedicated database** (Stancl Tenancy v3, PostgreSQL managed via `PostgreSQLDatabaseManager`). A shared **central database** holds platform-global records:

```
                    Central DB (platform)
                    ┌────────────────────────────┐
                    │ users · tenants · roles    │
                    │ permissions · plans · ...  │
                    └───────────┬────────────────┘
                                │
                 ┌──────────────┴──────────────┐
                 ▼                             ▼
        ┌──────────────────┐        ┌──────────────────┐
        │  tenant_<id> DB   │        │  tenant_<id> DB   │
        │  (Tenant A)       │        │  (Tenant B)       │
        │  conversations    │        │  conversations    │
        │  customers        │        │  customers        │
        │  tickets · KB ·   │        │  tickets · KB ·   │
        │  widgets · AI cfg │        │  widgets · AI cfg │
        └──────────────────┘        └──────────────────┘
```

A user can belong to **many tenants** and hold a different role in each:

```
User
 │
 └── tenant_user (membership pivot)
      ├── Tenant A  →  owner
      └── Tenant B  →  manager
```

**Key behaviors**

- New tenants are **provisioned asynchronously** (`ProvisionTenantJob`) — database created, migrations run, owner assigned.
- The `tenant.aware` middleware resolves the current tenant from the authenticated request and sets the tenant connection.
- **Spatie Permission teams** scope roles to a tenant, so `manager` in Tenant A means nothing in Tenant B.
- The `/billing/usage` endpoint, AI streaming, and all tenant routes operate only inside an authenticated tenant context.

> **Security principle:** tenant context is determined and validated by the backend. Client-provided tenant IDs are never trusted as an authorization mechanism.

---

## 🔐 Authentication & Authorization

### Authentication — JWT

```
Credentials
   │
   ▼
POST /api/v1/auth/login
   │
   ▼
JWT issued (tymon/jwt-auth)
   │
   ▼
Authenticated User
   │
   ▼
Scope detection → platform (super_admin) or tenant (owner/admin/manager/agent)
   │
   ▼
Role(s) → Permission set (Spatie)
   │
   ▼
Middleware (permission, super.admin, tenant.aware) → Controller → Service
```

- Emails are verified; passwords are **hashed with bcrypt**; sessions can be **revoked server-side**.
- Password reset uses signed, expiring tokens; tenant-aware middleware guards all tenant routes.

### Roles

| Role             | Scope   | Purpose                                        |
| ---------------- | ------- | ---------------------------------------------- |
| `super_admin`    | Platform| Operates the whole SaaS (tenants, users, billing, RBAC) |
| `owner`          | Tenant  | Owns the workspace; full tenant permissions    |
| `admin`          | Tenant  | Manages the workspace, team, and settings      |
| `manager`        | Tenant  | Manages support/team operations                 |
| `support_agent`  | Tenant  | Handles conversations, tickets, and knowledge   |

### Authorization model

- **Frontend permission helpers** control what the UI shows (UX only).
- **Backend `permission` middleware** is the real gate; `super_admin` bypasses all permission checks but is itself **protected from modification**.
- **`super.admin` middleware** restricts platform-admin routes to the super admin role.
- Roles and permissions are editable at runtime through the RBAC module (with system records flagged `is_system`).

---

## 🧠 AI Architecture

The AI layer is a small **tool-calling support agent**, wrapped in streaming and usage controls:

```
Customer message
   │
   ▼
AI Service
   ├── ConversationContextBuilder   → recent messages, subject, status
   ├── CustomerContextBuilder       → name, company, history
   ├── Tenant AI Configuration      → provider/model, temperature, system prompt, enabled
   ├── Knowledge retrieval          → published + AI-visible articles (searchForAI)
   │
   ▼
Prompt (system + context + knowledge)
   │
   ▼
AI Provider (streaming enabled)
   │
   ▼
AI Response
   │
   ├── streamed to the client (SSE, token-by-token)
   ├── persisted as a message
   └── recorded in AI usage (provider, model, tokens, latency)
```

- **Providers** are configured through `config/ai.php` (Virtually / Laravel AI): OpenAI (default), Anthropic, Google Gemini, Azure OpenAI, AWS Bedrock, Groq, Mistral, DeepSeek, xAI, OpenRouter, Ollama, and more.
- **Tenant configuration** (per-workspace) selects provider/model/temperature, toggles enablement and streaming, and supplies a custom system prompt.
- **Tools** let the agent *do* things: `GetCustomerTool`, `GetConversationTool`, `SearchKnowledgeBaseTool`, `RAGSearchTool`, `CreateTicketTool`, `EscalateConversationTool`.
- **Escalation** hands the conversation to a human agent with a recorded reason; the agent can continue the same thread.
- **Guardrails:** AI request **rate limits**, monthly **plan limits** enforced by `BillingLimitService`, plus max-message and token ceilings.

---

## 📚 Knowledge Base & Retrieval

Articles live in the tenant's schema and carry a **publication status** (`draft`, `published`, `archived`) and a **visibility scope** (`ai` by default) that decides whether the AI may use them.

Current behavior (**keyword → context injection**):

```
Knowledge Article (published + AI-visible)
   │
   ▼
/ai/knowledge-base/articles/search  (AI search endpoint)
   │
   ▼
Relevant articles
   │
   ▼
Injected into AI system/context prompt
   │
   ▼
AI Response grounded in your content
```

**Planned / config-gated — semantic RAG.** Chunking, embedding, and semantic-search services (`KnowledgeBaseChunkingService`, `EmbeddingService`, `SemanticSearchService`, `RAGSearchTool`, `ReindexKnowledgeBaseJob`) are implemented and wired, but enabled **only when toggled on** (`AI_EMBEDDING_ENABLED` / `AI_RAG_ENABLED`). The default shipping path is keyword-based retrieval so the product works out of the box. See [Roadmap](#-roadmap).

---

## 💳 Payment & Billing

Billing is **provider-independent** and event-driven:

```
                    Billing Service
                         │
              ┌──────────┴──────────┐
              ▼                     ▼
       Stripe Gateway         PayPal Gateway      (PaymentGatewayManager)
              │                     │
              └──────────┬──────────┘
                         ▼
                  Webhook Handler
                         │
              signature verification + idempotency
                         │
                         ▼
                  Queued Job (ProcessBillingWebhookJob)
                         │
                         ▼
                 Database state (subscription, invoice, payment)
```

- **Plans** are defined with per-provider prices; coupons apply discounts at checkout.
- **Subscriptions** support upgrade, downgrade, cancel, resume, trial, and renewal — with **proration handled per provider**.
- **Invoices and payments** are generated from real provider events; refunds are processed through the gateway.
- **Webhooks are the source of truth.** Redirect-based "success" pages never confirm a payment.
- **Usage-based limits** (AI requests, agents, customers, widgets, storage, conversations) update usage records and surface on `/billing/usage`.
- Scheduled jobs keep state in sync: renewals, reminders, reconciliations, monthly resets, and storage syncs.

---

## 📊 Analytics

Analytics are scoped per tenant (and per platform for admins) and served from dedicated services:

- **Overview** — headline KPIs
- **Conversations** — volume, resolution, channels-trends
- **Customers** — acquisition, activity, lifetime value
- **Agents** — response times, resolution load, first-response metrics
- **Tickets** — backlog, aging, status, priority flows
- **AI** — tokens, requests, costs, latency, health, failure rates
- **Widget** — sessions, engagement, follower lines
- **Knowledge base** — article views, feedback

All analytic surfaces share a **CSV export** pipeline (`GenerateAnalyticsExport` job) with an export history page.

---

## 🧰 Technology Stack

| Layer             | Technology                                                   |
| ----------------- | ------------------------------------------------------------ |
| Backend           | Laravel 13                                                   |
| Language          | PHP 8.3                                                      |
| API               | REST (versioned `/api/v1`)                                   |
| Authentication    | JWT (`tymon/jwt-auth`) with email verification               |
| Database          | PostgreSQL (central + per-tenant databases)                  |
| Multi-tenancy     | `stancl/tenancy` v3 (database-per-tenant)                    |
| Authorization     | `spatie/laravel-permission` (team-scoped roles)              |
| AI                | Laravel AI (`laravel/ai`) + provider tooling                 |
| Payments          | Stripe, PayPal                                               |
| Queue             | Laravel Queue (jobs for provisioning, billing, exports, AI)  |
| Scheduler         | Laravel Scheduler (renewals, reconciliations, cleanups)      |
| Frontend          | Vue 3.4 SPA                                                  |
| UI library        | PrimeVue 4 + Tailwind CSS 4                                  |
| State management  | Pinia                                                       |
| HTTP client       | Axios                                                        |
| Charts            | Chart.js                                                     |
| Streaming         | Server-Sent Events (`event-source-polyfill`)                 |
| Tooling           | Vite 5, ESLint + Prettier, phpunit                           |

---

## 📁 Project Structure

The repository is a **monorepo** with a decoupled backend and frontend:

```
ai-customer-support-saas/
│
├── backend/                         # Laravel REST API
│   ├── app/
│   │   ├── Ai/                      # AI agent, tools, context, services
│   │   │   ├── Agents/              #   support agent
│   │   │   ├── Context/             #   conversation/customer context builders
│   │   │   ├── Tools/               #   ticket, escalate, KB search, RAG
│   │   │   └── Services/            #   AI streaming, usage, embeddings, RAG
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/  #   auth, tenant, team, customers, …
│   │   │   │   ├── Admin/           #   platform administration
│   │   │   │   ├── AI/ · Analytics/ · Billing/ · ChatWidget/ · …
│   │   │   ├── Middleware/          #   jwt.auth, tenant.aware, permission, rate limits
│   │   │   ├── Requests/            #   validated form requests
│   │   │   └── Resources/           #   API resource presenters
│   │   ├── Services/                #   business logic per domain
│   │   │   ├── Billing/ · Analytics/ · ChatWidget/ · Customer/ · …
│   │   ├── Jobs/                    #   provisioning, billing, exports, AI
│   │   ├── Models/                  #   central models (+ Models/Tenant)
│   │   ├── Policies/
│   │   ├── Console/Commands/        #   billing, widget cleanup
│   │   └── Trait/ GuardsSystemUsers #   protects Super Admins from mutation
│   ├── config/                      #   tenancy, ai, billing, payment, permission, jwt, …
│   ├── database/
│   │   ├── migrations/              #   central migrations
│   │   ├── migrations/tenant/       #   per-tenant schema (conversations, tickets, KB, …)
│   │   └── seeders/                 #   RBAC, super admin, roles
│   ├── routes/
│   │   ├── api.php                  #   versioned API + platform admin routes
│   │   └── console.php              #   scheduled jobs
│   ├── tests/
│   ├── .env.example
│   └── composer.json
│
├── frontend/                        # Vue 3 SPA
│   ├── src/
│   │   ├── views/                   #   per-module pages (admin/, billing/, tickets/, …)
│   │   │   ├── admin/               #   platform: tenants, users, rbac, billing
│   │   │   ├── conversations/ · tickets/ · customers/ · knowledge-base/ · analytics/ …
│   │   ├── components/              #   shared widgets
│   │   ├── stores/                  #   Pinia stores (auth, users, team, …)
│   │   ├── services/                #   axios API clients per domain
│   │   ├── router/                  #   route table + guards
│   │   └── layouts/
│   ├── vite.config.mjs
│   └── package.json
│
└── README.md
```

---

## 🗃️ Database Architecture

- **Central connection (`pgsql`)** — platform identity: `users`, `tenants`, `tenant_user` memberships, `roles`/`permissions` (Spatie), `audit_logs`, `plans`/`subscriptions`/`invoices`/`payments`, `payment_webhook_events`, platform `api_logs`/`ai_logs`, etc.
- **Tenant connection (`tenant`)** — created per tenant as `tenant_<id>` and migrated with `database/migrations/tenant/`: `conversations`, `messages`, `customers`, `tickets` + `ticket_comments` + `ticket_activity`, `knowledge_base_categories` / `knowledge_base_articles`, `chat_widgets`, `widget_sessions`, `ai_configuration`, `ai_usage`, `analytics_exports`, and more.

Tenant state (conversations, tickets, customers, knowledge) stays entirely inside the owning tenant's database. Platform state (users, tenancy, billing, RBAC) lives centrally — giving clean isolation and a clean story for auditing.

---

## 🔌 API Structure

All endpoints are JSON under `/api/v1`:

```
/api/v1
│
├── auth                register · login · logout · refresh · me · verify · password
├── tenants             current · my-tenants · switch · users · invite · role
├── workspace           profile · branding · business hours · statistics
├── team                members · departments · invitations
├── customers           CRUD · tags · block/unblock · bulk · export
├── conversations       CRUD · statuses · assign/unassign
│   └── messages        messages · notes
├── tickets             lifecycle · priorities · comments
├── knowledge-base      categories · articles · publish · AI search
├── ai                  configuration · test · stream (SSE) · usage · health · logs
├── chat-widgets        management · installation code · statistics
├── analytics           overview · conversations · customers · agents · tickets · ai · widget · KB · exports
├── plans               public plan catalog
├── subscription        current · checkout · upgrade/downgrade · cancel/resume · providers
├── invoices            list · statistics · download
├── payments            list · statistics
├── notifications       list · read · read-all
└── admin               platform-only:
    ├── analytics · tenants (activate/suspend/archive/…)
    ├── users (create/update/activate/suspend/revoke …)
    ├── rbac (roles · permissions · assignments)
    └── billing (plans · prices · subscriptions · invoices · payments · coupons)
```

**Public, unauthenticated:** widget endpoints (`/v1/widget/*` — rate limited), public plan catalog, auth, and billing webhooks (`/v1/billing/webhooks/{stripe|paypal}`).

---

## 🚀 Installation

### Requirements

- PHP **8.3+**
- Composer 2
- Node.js 20+ and npm
- PostgreSQL **15+** (with the `pgsql` PHP extension)
- A running queue driver (database or Redis) — Redis needs the `phpredis` extension

### Clone

```bash
git clone https://github.com/your-org/ai-customer-support-saas.git
cd ai-customer-support-saas
```

### Backend

```bash
cd backend
composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret                 # generates JWT_SECRET
```

Configure `.env` (see [Environment Configuration](#-environment-configuration)), then:

```bash
php artisan migrate --seed             # central schema + RBAC + default roles
php artisan storage:link               # expose local storage (avatars, exports)
```

> The seeder creates a super admin: `superadmin@gmail.com` / `11111111`. **Change this password before any public deployment.**

### Frontend

```bash
cd frontend
npm install
```

### Build for production

```bash
npm run build
```

---

## ⚙️ Environment Configuration

`backend/.env.example` is the starting point. The essential keys:

```env
APP_NAME=AI Customer Support
APP_URL=http://localhost:8000

# CORS — allow your frontend origin(s)
CORS_ALLOWED_ORIGINS=http://localhost:5173

# Database (central + tenant DBs are created from these credentials)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_ai_customer_support
DB_USERNAME=postgres
DB_PASSWORD=root

# Auth
JWT_SECRET=                                # php artisan jwt:secret
JWT_TTL=60

# Queue / cache / session — database or redis
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=file

# Mail
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=support@example.local
MAIL_FROM_NAME="${APP_NAME}"

# AI (see config/ai.php for the full provider list)
AI_ENABLED=true
AI_PROVIDER=openai
OPENAI_API_KEY=
AI_STREAMING_ENABLED=true
AI_EMBEDDING_ENABLED=false
AI_RAG_ENABLED=false

# Billing (see config/billing.php / config/payment.php)
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_WEBHOOK_ID=
PAYPAL_MODE=sandbox
PAYPAL_WEBHOOK_URL=

# Object storage (optional, for avatars / exports)
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_BUCKET=
```

> **Never commit `.env` or production credentials to source control.** Use a secret manager in production and keep `.env.example` free of real values.

---

## ▶️ Running the Application

**Backend** (terminal 1):

```bash
cd backend
php artisan serve                     # → http://localhost:8000
```

**Frontend dev server** (terminal 2):

```bash
cd frontend
npm run dev                           # → http://localhost:5173
```

**Queue worker** (terminal 3) — required for provisioning, billing webhooks, exports, and AI jobs:

```bash
php artisan queue:work
```

**Scheduler** (always on in production):

```bash
php artisan schedule:work
```

Then open `http://localhost:5173`, sign in as `superadmin@gmail.com`, and create your first tenant (or use the platform admin to manage tenants, users, and billing).

---

## 🧪 Testing

```bash
cd backend
php artisan test
```

Targeted run:

```bash
php artisan test --testsuite=Feature
```

**Test strategy (being built out):**

- Authentication and verification flows
- RBAC — each role's allowed/denied permission matrix
- Tenant isolation — **Tenant A must never access Tenant B's resources**
- Customers, conversations, messages, tickets, knowledge base, widget, analytics, billing, and platform administration

> **Status:** the repository currently ships the framework's default unit/feature skeletons. Dedicated coverage for the matrix above is **in progress** (see [Roadmap](#-roadmap)) — the services and middleware are structured to make these tests straightforward.

---

## 🔐 Security

The application follows a defense-in-depth approach:

- **Database-per-tenant isolation** — tenant data never shares a schema with other tenants
- **Backend-validated tenant context** — client-supplied tenant IDs are never trusted
- **JWT authentication** with token refresh and server-side session revocation
- **Permission middleware** on every protected route + **super.admin** gating for platform routes
- **Team-scoped roles** (Spatie teams) so roles mean nothing outside their tenant
- **Protected system accounts** — Super Admins cannot be edited, suspended, or have sessions revoked through the admin API
- **Rate limiting** on public widget, AI, and billing surfaces
- **Input validation** via Form Requests; HTML sanitization with `dompurify` (frontend) and `htmlpurifier` (backend)
- **Webhook signature verification** and idempotent event processing
- **Audit logging** for sensitive mutations (users, tenants, sessions, lifecycle events)
- **Request-size and plan-limit enforcement** (AI requests, storage, seats)
- **Sensitive credential isolation** — API keys live in `.env` / secrets, never in the client bundle

**Reporting a vulnerability:** please do **not** open a public issue. Report privately so a fix can be shipped before disclosure.

---

## ☁️ Deployment

Suggested production topology:

```
                        Internet
                           │
                           ▼
                      Load Balancer / CDN  (TLS termination)
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
      Vue SPA (static)          Laravel API (php-fpm/octane)
                                      │
                                      ▼
                                  PostgreSQL
                                      │
             ┌────────────────────────┼────────────────────────┐
             ▼                        ▼                        ▼
        Queue workers            Redis / scheduler         Object storage
        (billing, exports, AI)   (cache, sessions)         (avatars, exports)
```

**Deployment checklist**

- [ ] Production `.env` with real, secret-managed credentials
- [ ] `php artisan migrate --seed` on the central database
- [ ] Queue workers running (`php artisan queue:work`)
- [ ] Scheduler enabled (`php artisan schedule:work` / cron `schedule:run`)
- [ ] HTTPS enforced; `APP_DEBUG=false`
- [ ] Webhook endpoints registered with Stripe and PayPal (HTTPS)
- [ ] Frontend built (`npm run build`) and served from your CDN/static host
- [ ] `APP_URL` and `CORS_ALLOWED_ORIGINS` match your public host
- [ ] Object storage configured for avatars and analytics exports
- [ ] Automated database backups and monitoring
- [ ] Change the seeded super-admin password

---

## 🔀 Development Workflow

```
Issue
  │
  ▼
Feature branch  (feature/, fix/, refactor/, docs/, test/)
  │
  ▼
Implementation  → focused commits
  │
  ▼
Tests (unit / feature)
  │
  ▼
Code review → pull request
  │
  ▼
Merge → deploy
```

**Branch naming:**

- `feature/ai-copilot`
- `feature/customer-import`
- `fix/widget-session`
- `refactor/billing-gateway`
- `docs/api-authentication`

**Commit messages (Conventional Commits):**

- `feat: add conversation assignment`
- `fix: prevent cross-tenant conversation access`
- `refactor: extract billing gateway`
- `test: add tenant isolation tests`
- `docs: update API documentation`

---

## 🗺️ Roadmap

### Completed

- [x] JWT authentication, email verification, password recovery
- [x] Database-per-tenant multi-tenancy + tenant switching
- [x] Workspace, team management, invitations
- [x] Customer lifecycle (tags, block/unblock, bulk operations)
- [x] Conversations, messages, internal notes, assignment workflow
- [x] Ticket lifecycle, priorities, comments
- [x] Knowledge base (categories, articles, publishing, AI visibility)
- [x] AI streaming support with tools, escalation, usage tracking, and limits
- [x] Embeddable chat widget with rate limiting and analytics
- [x] Analytics dashboards + CSV exports
- [x] Stripe + PayPal billing (plans, subscriptions, invoices, payments, coupons, webhooks)
- [x] RBAC (roles, permissions, team scoping) + platform administration
- [x] Protected system accounts (Super Admin immutability)

### In Progress

- [ ] Automated RBAC permission-matrix tests
- [ ] Tenant-isolation test suite
- [ ] Analytics runtime verification tests
- [ ] Production hardening and ops documentation

### Planned

- [ ] Semantic RAG pipeline (chunking, embeddings, vector search) enabled by default
- [ ] Additional AI providers and evaluation tooling
- [ ] Additional communication channels (email, WhatsApp, social)
- [ ] Advanced automation / intent-driven routing
- [ ] Mobile application

---

## 🤝 Contributing

1. Fork the repository.
2. Create a feature branch (`feature/your-change`).
3. Make focused, well-scoped changes with tests where possible.
4. Run lint (`npm run lint` in `frontend/`, `vendor/bin/pint` in `backend/`) and the test suite.
5. Open a pull request describing the change and how it was verified.

Please follow conventional commits and keep PRs reviewable.

---

## 📄 License

MIT — see `backend/composer.json` for the declared license. You are free to use, modify, and redistribute this project for commercial purposes; the SaaS template is provided "as is", without warranty.