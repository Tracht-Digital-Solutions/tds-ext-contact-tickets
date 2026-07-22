# tds-ext-contact-tickets-pkg

The **contact-form inbox** as a frontend extension. The public marketing site's
contact form submits to a **public endpoint**; each submission becomes a
`contact_message` an admin triages in the frontend. Standalone — its own table,
separate from the support-ticket system.

## Surface (checkpoint-1)

- **Public:** `POST /contact` — no auth; validated (name≥2, valid email,
  message≥20) + honeypot (`website` field) guarded; stores the message and (best-
  effort) notifies the admin via the **core Mailer** (`CONTACT_ADMIN_EMAIL`,
  Reply-To the submitter).
- **Admin inbox** (`contact:read` / `contact:write`): `GET /contact/summary`
  (the "Neue Anfragen" widget), `GET /contact/messages?status=`,
  `GET /contact/messages/{id}`, `PATCH /contact/messages/{id}` (status
  new → handled | spam).
- **Frontend:** nav "Kontaktanfragen" → `/kontakt`, the inbox (filter + set
  status), the new-requests widget, DE/EN i18n.

## Still to port (later checkpoints)

The message detail view + email-reply-from-frontend, rate-limiting on the public
endpoint, and optional forwarding to the support-ticket system.

## Develop

```bash
npm install        # pulls tds-frontend-contract from GitHub Packages (needs NPM_TOKEN)
npm run build && npm run type-check
composer install   # resolves tds-frontend-contract from its public VCS repo
composer test      # phpunit — route/RBAC/validation coverage; DB tests skip without TDS_TEST_DB_DSN
```

## Enable it

Host `astro.config.mjs`: add the manifest to `frontendHost({ extensions: [...] })`.
Base API: add `new ContactTicketsModule()` to `Modules::enabled()`. Set
`CONTACT_ADMIN_EMAIL` (+ the core `MAIL_DSN`) for submission notifications.
