# AGENTS.md — tds-ext-contact-tickets-pkg

The public contact-form inbox. Read `tds-frontend-contract-pkg` + `tds-core-frontend-api`
AGENTS first. Standalone — NOT the support-ticket system (separate `contact_message`
table); support-tickets also has a `/tickets/contact` ingest, but this extension
is the dedicated public inbox.

## Model

- **`POST /contact` is PUBLIC** (no auth) — the marketing form posts here. It's
  the only unauthenticated write in the platform, so it's kept tight: validation +
  honeypot (`website`) + **IP-hash rate-limit** (max 5 / IP / 10 min → 429). The IP
  is only ever stored as a salted SHA-256 (`ip_hash`, `CONTACT_RATE_SALT` else
  `SETTINGS_ENCRYPTION_KEY`); never the raw IP, and it's stripped from the detail API.
- Admin inbox gated by `contact:read` / `contact:write` (admins bypass) via the
  core UserContext. `status` moves new → handled | spam.
- **Admin reply** (`POST /contact/messages/{id}/reply`, `contact:write`) emails the
  submitter via the **core Mailer**, stores the reply in `contact_reply` (shown in the
  detail view), and moves a `new` message to `handled`. 503 when the Mailer is
  unconfigured. Admin notification on new submissions also via the core Mailer
  (`CONTACT_ADMIN_EMAIL`, else `TICKET_ADMIN_EMAIL`; Reply-To = the submitter).

## Gotchas

- Migration class names are **module-prefixed** (`ContactTickets*`) AND the
  numeric **version prefixes are globally unique** (this module owns the
  `20260726*` band) — every composed module's migrations share one `phinxlog`,
  so a reused class name OR version collides. Keep new migrations in this band.
- Routes are closures resolving `ContactRepository`/`Mailer`/`UserContext` from
  the container at request time (rebound per request by the core AuthMiddleware).
- DB-backed tests skip without `TDS_TEST_DB_DSN`; the committed test covers the
  public submit validation + honeypot + inbox/reply RBAC without a DB (all tested
  paths short-circuit at auth/validation before the repo/mailer).

## Checkpoint status

- **CP1:** `contact_message` schema, `Domain\ContactRepository`, public submit +
  admin inbox CRUD/status with RBAC, notify-admin via core Mailer, inbox UI +
  widget.
- **CP2:** `contact_reply` table + `ip_hash` column; IP-hash rate-limit on the
  public submit (429); admin reply-by-email endpoint (core Mailer → `contact_reply`
  → auto-handle); frontend detail view (full body + reply history + compose).
- **TODO (next):** optional forward to support-tickets; per-message spam heuristics.

## After a change

Bump `version` in `package.json` + `composer.json` (lockstep), update docs, commit.
