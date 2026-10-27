# Deploy & operate the landing

## Upload

Credentials: `.env.deploy` (gitignored; copy from `.env.deploy.example`). Same SSH host as the main kulik site.

```bash
./scripts/deploy.sh --dry-run   # list files
./scripts/deploy.sh            # upload → https://event.kuliksystem.us
./scripts/deploy.sh discover   # list remote folders if path is wrong
```

Or in Cursor: `/deploy` (reviews, commits + pushes to GitHub, then uploads).

Keeps remote `config.php` and `storage/*.jsonl`. Ships:

- `index.html`, `css/`, `js/`, `fonts/`, `assets/`
- `submit.php`, `lib/`, `cron/`, `storage/.gitkeep`
- `config.example.php`, `.htaccess`

No SQL import. No Composer. PHP 8+ with `curl` and `mbstring`.

Document root (default): `/home/ksus/kuliksystem.us/event` — create the `event` subdomain in the hosting panel first if the folder is missing.

## config.php

```bash
cp config.example.php config.php
```

Fill:

| Field | Purpose |
| --- | --- |
| `mail_to` / `mail_from` | Organizer inbox and From address |
| `smtp_*` | SMTP host, port, user, password, `tls`/`ssl`/`none` |
| `crm_url` / `crm_token` | Kommo: `…/api/v4/leads/complex` + long-lived token; empty = CRM `pending` |
| `kommo_pipeline_id` / `kommo_status_id` | Invitation Leads pipeline + first stage (New request) |
| `telegram_*` | Leave empty — Telegram stays off |
| `gtm_id` | Copy the same value into `window.KULIK_GTM_ID` in `index.html` |
| `retry_key` | Long random string for the retry URL |

`.htaccess` blocks direct HTTP access to `config.php`, `storage/`, `lib/`, and `cron/`.

## Cron (every 5 minutes)

Retries Invitation Requests whose Delivery status is still `pending` or `failed`:

```cron
*/5 * * * * /usr/bin/php /path/to/site/cron/retry.php >/dev/null 2>&1
```

Or hit `https://YOUR-HOST/cron/retry.php?key=YOUR_RETRY_KEY` on a schedule. Prefer CLI when the host allows it; if `.htaccess` blocks `/cron/`, use CLI only.

## Local check

```bash
cp config.example.php config.php
php -S 127.0.0.1:8080
```

Open `http://127.0.0.1:8080/`. Submit the form. Confirm:

1. Success message for the Guest
2. New line in `storage/requests.jsonl`
3. One `form_submit` in the dataLayer (DevTools → Console: `dataLayer`)
4. Second submit with the same token does not add a second line (refresh creates a new token)
5. Empty `crm_url` leaves `delivery.crm` as `pending`; Guest still sees success
