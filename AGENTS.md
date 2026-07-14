# AGENTS.md — tds-ext-contact-tickets

The public contact-form inbox. Read `tds-panel-contract` + `tds-core-panel-api`
AGENTS first. Standalone — NOT the support-ticket system (separate `contact_message`
table); support-tickets also has a `/tickets/contact` ingest, but this extension
is the dedicated public inbox.

## Model

- **`POST /contact` is PUBLIC** (no auth) — the marketing form posts here. It's
  the only unauthenticated write in the platform, so keep it tight: validation +
  honeypot (`website`) now; rate-limiting is a TODO.
- Admin inbox gated by `contact:read` / `contact:write` (admins bypass) via the
  core UserContext. `status` moves new → handled | spam.
- Notifications via the **core Mailer** (`CONTACT_ADMIN_EMAIL`, else
  `TICKET_ADMIN_EMAIL`; Reply-To = the submitter). No-op when unconfigured.

## Gotchas

- Migration class name is **module-prefixed** (`ContactTickets*`).
- Routes are closures resolving `ContactRepository`/`Mailer`/`UserContext` from
  the container at request time (rebound per request by the core AuthMiddleware).
- DB-backed tests skip without `TDS_TEST_DB_DSN`; the committed test covers the
  public submit validation + honeypot + inbox RBAC without a DB.

## Checkpoint status

- **CP1:** `contact_message` schema, `Domain\ContactRepository`, public submit +
  admin inbox CRUD/status with RBAC, notify-admin via core Mailer, inbox UI +
  widget.
- **TODO (next):** message detail + reply-by-email from the panel; rate-limiting;
  optional forward to support-tickets.

## After a change

Bump `version` in `package.json` + `composer.json` (lockstep), update docs, commit.
