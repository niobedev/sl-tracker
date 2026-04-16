# Agent Instructions for Avatar Tracking System

## Development Commands

All interactions go through `make`:
```bash
make up              # Start dev stack (http://localhost:8080)
make down            # Stop dev stack
make restart         # Restart dev stack
make logs            # Follow all container logs
make shell           # Open shell in PHP container
make migrate         # Run pending database migrations
make user name=X pass=Y  # Create login user
make test            # Run PHPUnit tests
make test-verbose    # Run tests with verbose output
make test-coverage   # Run tests with coverage report
```

## Architecture

**Single-container production model**: php-fpm + caddy run together under supervisord (no separate scheduler anymore - Google Sheets sync was removed).

**Data flow**: LSL script → POST /api/events → MySQL → Notifications → Telegram

**Key architectural decisions**:
- EventRepository uses MySQL window functions (`LEAD`, `ROW_NUMBER`) for login/logout session pairing
- All timestamps stored as UTC, converted to browser timezone via `<time data-local>` elements
- API endpoints require `X-API-Key` header (configured via `API_SECRET_KEY` env var)
- Frontend: vanilla JS ES modules with Apache ECharts, no bundler

## Testing

- PHPUnit 10, test base class: `App\Tests\ApiTestCase`
- Tests in `tests/Api/` (endpoint tests) and `tests/Service/` (service tests)
- Test environment uses SQLite in-memory database
- API key in tests: `test-api-key-12345` (set in phpunit.xml.dist)

## Recent Changes

This project was recently reworked from a Google Sheets-based system to an API-based system. Some old code references may still exist:
- Removed: `SyncState` entity/repo, `GoogleSheetsService`, `SheetSyncService`, `SyncSheetCommand`
- Old terminology in code/comments may still refer to "visits" or "join/quit" instead of "sessions" or "login/logout"

## Database Migrations

Always run migrations after schema changes:
```bash
make migrate
```

Migrations are in `migrations/` and follow Doctrine migrations naming: `VersionYYYYMMDDHHMMSS.php`

## Adding a Chart

1. Add query method to `EventRepository`
2. Add `/api/<name>` endpoint in `ApiController`
3. Create `public/assets/charts/<name>.js` (export a render function)
4. Register in `CHARTS` array in `public/assets/app.js`
5. Add `<div id="chart-<name>">` to dashboard template

## LSL Scripts

Located in `lsl/` directory. Use `llRequestAgentData(k, DATA_ONLINE)` for global avatar tracking (not region-restricted). Script polls `/api/tracking-config` every 60s to get the list of avatars to track.

## Environment Variables

Required in `.env.local`:
```env
API_SECRET_KEY=your-secret-key
DATABASE_URL=mysql://user:pass@localhost:3306/dbname
APP_SECRET=your-app-secret
```

## Common Gotchas

- **Composer lock file**: Must be kept in sync with composer.json. If you edit composer.json manually, run `composer install` to regenerate it locally before building.
- **Missing avatar stats**: The avatar detail page will 500 if an avatar has no events yet. Handle this by checking if `$stats` is null in the controller.
- **API authentication**: All API endpoints (except login) require `X-API-Key` header. Without it, they return 401.
- **Event actions**: The database stores 'login'/'logout', but old code/comments may still use 'join'/'quit'.
