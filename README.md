# CRM Portal

Internal CRM/data-search portal used by agents to look up customer records across multiple states, e-commerce orders, LPG (IndianOil SDMS) connections, and PAN India records via a Telegram-backed search bot — plus an admin panel for account management, imports, and feature settings.

## Features

- **State-wise customer search** (`index.php`) — Tamil Nadu, Andhra Pradesh, Karnataka, Kerala
- **E-Commerce order search** (`ecommerce.php`) with bulk CSV import
- **LPG Search** (`lpg_search.php`, `lpg_bulk_search.php`) — looks up IndianOil SDMS gas-connection records via a local Flask/Selenium service (`Gas/lpg_web/`)
- **PAN India Search** (`pan_india.php`) — searches a Telegram bot for email/Aadhaar/contact records via a persistent PHP MadelineProto worker (`cli/telegram_worker.php`); results auto-archive to a deduplicated CSV for admin review
- **WhatsApp contact button** — a site-wide floating button, admin-configurable (global on/off plus a per-user allowlist)
- **Admin panel** (`admin/`) — agent/account management with per-user feature access flags, data import, audit log, LPG/WhatsApp settings

Access to LPG Search and PAN India Search is opt-in per account, granted from **Admin → Agents**. Both default to off for new accounts.

## Tech stack

- PHP 8.x + MySQL (PDO)
- Python/Flask + Selenium for the LPG search service
- PHP (MadelineProto) for the Telegram-backed PAN India worker
- Vanilla JS/CSS frontend, no build step

## Setup

1. **Config files** — copy each `.example` file and fill in real values (the real files are gitignored, never commit them):
   ```
   config/db.php.example       -> config/db.php
   config/secrets.php.example  -> config/secrets.php
   config/telegram.php.example -> config/telegram.php   (only needed for PAN India Search)
   ```
2. **Database** — create the database, then run:
   ```
   database/schema.sql
   database/schema_states.sql
   database/schema_ecommerce.sql
   database/migrate_*.sql   (in date order)
   ```
3. **Web server** — point a PHP web server (IIS, Apache, or `php -S`) at the project root. `web.config` includes an IIS reverse-proxy rule for the LPG tool if deploying under IIS.
4. **Optional: LPG Search service** — see `Gas/lpg_web/README.txt`. Requires Python, Selenium, and Chrome/Chromedriver on the host.
5. **Optional: PAN India Search** — see `PAN_INDIA_DEPLOYMENT.md` for the Telegram worker setup, one-time QR login, and required PHP extensions.

## Project structure

```
admin/        Admin panel (agents, imports, settings)
api/          JSON API endpoints
cli/          Command-line scripts (imports, Telegram worker)
config/       Environment config (gitignored except *.example)
database/     Schema + migrations
Gas/          LPG search Flask/Selenium service
includes/     Shared PHP includes (auth, header/footer, helpers)
assets/       CSS
```

## Security notes

- `config/db.php`, `config/secrets.php`, `config/telegram.php`, and `.runtime/` are gitignored — they hold real credentials and session state and must never be committed.
- Feature access (LPG Search, PAN India Search) is per-user and off by default for new accounts.
