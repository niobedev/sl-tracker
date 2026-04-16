# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

All common tasks are wrapped in `make` targets:

```bash
make up              # Start dev stack (http://localhost:8080)
make down            # Stop and remove containers
make restart         # Restart stack
make build           # Rebuild dev image (after Dockerfile changes)
make shell           # Open shell in PHP container
make logs            # Follow all container logs
make logs-php        # Follow PHP/sync logs only
make cache-clear     # Clear Symfony cache
make routes          # List all registered routes
make debug-container # Search DI container (q=<term>)

make migrate         # Run pending database migrations
make sync            # Incremental Google Sheets sync
make sync-full       # Full re-sync (wipes events, re-imports all rows)
make sync-profiles   # Refresh SL avatar profiles (delay=<ms> optional)
make user            # Create login user (name=<n> pass=<p>)

make composer-install
make composer-require pkg=vendor/name
```

There is **no test suite** in this project.

## Architecture

**Stack:** Symfony 8 / PHP 8.4-fpm / MySQL 8 (Doctrine ORM) / Apache ECharts (ES modules) / Tailwind CSS / Docker + Caddy

### Single-container production model

In both dev and prod, a single Docker container runs three processes under **supervisor**:
- `php-fpm` on `:9000`
- `caddy` on `:80` (reverse-proxies to php-fpm)
- `scheduler` — infinite loop: `php bin/console app:sync-sheet; sleep 60`

Database migrations run automatically at container startup.

### Data flow

1. Google Sheets API (service account) → `GoogleSheetsService` → `SheetSyncService` → `Event` rows in MySQL
2. HTTP request → Caddy → php-fpm → Symfony Router → Controller → `EventRepository` (window functions for join/quit pairing) → JSON response
3. Browser polls `/api/sync-status` every 10s; re-renders all ECharts modules on new sync

### Key source areas

| Area | Path |
|------|------|
| Console commands (sync, user creation) | `src/Command/` |
| API + page controllers | `src/Controller/` |
| Business logic (Sheets, SL profiles) | `src/Service/` |
| DB queries (window functions for pairing) | `src/Repository/EventRepository.php` |
| Doctrine entities | `src/Entity/` |
| Chart ES modules | `public/assets/charts/` |
| Chart initialization + poll loop | `public/assets/app.js` |
| UTC→local time conversion | `public/assets/local-time.js` |
| Twig templates | `templates/` |
| DB migrations | `migrations/` |

### Adding a chart (standard pattern)

1. Add query method to `EventRepository`
2. Add `/api/<name>` endpoint in `ApiController`
3. Create `public/assets/charts/<name>.js` (export a render function)
4. Register in the `CHARTS` array in `public/assets/app.js`
5. Add `<div id="chart-<name>">` to the Twig template

### Key conventions

- **Timestamps:** all stored as UTC in MySQL; `local-time.js` converts `<time data-local>` elements to browser timezone
- **Markdown:** rendered server-side via `league/commonmark` in XSS-strip mode
- **Auth:** session-based (HTML login form); all routes guarded in `config/packages/security.yaml`
- **CSRF:** Symfony tokens on all POST/PUT/DELETE form actions
- **SL profiles:** cached as `AvatarProfile` with image BLOB; stale-while-revalidate on avatar page load
- **Sync state:** `SyncState` entity tracks last Google Sheets row processed for incremental sync

### Environment

Copy `.env.example` to `.env.local` and set:
- `GOOGLE_SHEET_ID`, `GOOGLE_SHEET_RANGE`, `SHEET_TIMEZONE`
- `GOOGLE_SERVICE_ACCOUNT_PATH` (points to `secrets/google-sa-key.json`)
- `DATABASE_URL`
