# Avatar Tracking System

A Symfony 8 application for tracking Second Life avatar login/logout events with real-time notifications.

## Features

- 🎯 Global avatar tracking via LSL script using `llRequestAgentData()`
- 📊 Real-time analytics dashboard with interactive charts
- 🔔 Multi-channel notifications (Telegram, Matrix)
- 👥 Avatar management with Second Life profile integration
- ⚙️ Configurable notification channels per avatar
- 📈 Comprehensive analytics: online time, activity patterns, leaderboards
- 🤖 Automatic avatar username fetching from Second Life website

## Architecture

### Tech Stack

- **Backend**: Symfony 8, PHP 8.4-fpm, MySQL 8, Doctrine ORM
- **Frontend**: Vanilla JavaScript (ES modules), Apache ECharts 5, Tailwind CSS
- **Web Server**: Caddy (reverse proxy to php-fpm)
- **Containerization**: Docker + Docker Compose
- **Testing**: PHPUnit 10

### Data Flow

```
LSL Script (in-world)
    ↓ HTTP POST /api/events
API Controller
    ↓
Event Repository (save to MySQL)
    ↓
Notification Service
    ↓
Telegram (send notifications)
```

### Database Schema

- `event` - Login/logout events with timestamps
- `tracked_avatar` - Avatars being monitored
- `notification_channel` - Notification destinations (Telegram, Matrix)
- `avatar_profile` - Cached Second Life profile data (name, username, avatar image, bio)
- `user` - Application users

## Quick Start

### Prerequisites

- Docker and Docker Compose
- A Second Life account (for LSL script deployment)
- Telegram bot token (for notifications, optional)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/sl-tracker.git
   cd sl-tracker
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env.local
   # Edit .env.local with your settings
   ```

3. **Start the application**
   ```bash
   make up
   ```

4. **Run database migrations**
   ```bash
   make migrate
   ```

5. **Create an admin user**
   ```bash
   make user name=admin pass=your-password
   ```

6. **Access the application**
   - Open http://localhost:8080 in your browser
   - Log in with your admin credentials

### Environment Variables

```env
# API Authentication
API_SECRET_KEY=your-secret-api-key-change-in-production

# Database
DATABASE_URL=mysql://user:password@localhost:3306/sl_tracker

# Application
APP_ENV=prod
APP_SECRET=generate-with:php -r "echo bin2hex(random_bytes(32));"
```

## Usage

### 1. Set Up Notification Channels

Navigate to **Channels** page and create a notification channel:

**Telegram:**
- Name: e.g., "Main Notifications"
- Type: "telegram"
- Config:
  - Bot Token: Get from [@BotFather](https://t.me/BotFather)
  - Chat ID: Your Telegram chat ID

**Matrix:**
- Name: e.g., "Matrix Notifications"
- Type: "matrix"
- Config:
  - Server URL: Your Matrix server (e.g., `https://matrix.example.com`)
  - Room ID: The Matrix room ID (e.g., `!abc123:example.com`)
  - Bot Token: Your Matrix bot access token

Click **Test** to verify the configuration.

### 2. Add Avatars to Track

Navigate to **Avatars** page:
- Enter avatar UUID and click **Add Avatar**
- The system will fetch the profile from Second Life
- Enable/disable tracking using the toggle
- Assign a notification channel from the dropdown

### 3. Deploy LSL Script

Copy the script from `lsl/tracker.lsl` to Second Life:

1. Create a new script in-world
2. Paste the LSL script content
3. Configure these variables at the top:
   ```lsl
   string API_URL = "https://your-domain.com";
   string API_KEY = "your-api-key-from-env";
   ```
4. Save and reset the script

The script will:
- Poll your API every 60 seconds for the tracking list
- Check avatar online status every 60 seconds using `llRequestAgentData()`
- Send login/logout events to your API
- Trigger notifications for tracked avatars

### 4. Monitor Dashboard

The main dashboard shows:
- **Online Now** - Avatars currently logged in
- **Recent Activity** - Recent login/logout events
- **Leaderboard** - Top avatars by total online time
- **Heatmap** - Activity patterns by day and hour
- **Trend Charts** - Daily, weekly, monthly statistics

## API Documentation

### Authentication

All API endpoints require an `X-API-Key` header:

```bash
curl -H "X-API-Key: your-api-key" https://your-domain.com/api/tracking-config
```

### Endpoints

#### Receive Events

```http
POST /api/events
Content-Type: application/json
X-API-Key: your-api-key

[
  {
    "event_ts": "2026-04-16T12:00:00Z",
    "action": "login",
    "avatarKey": "12345678-1234-1234-1234-123456789012",
    "displayName": "John Doe",
    "regionName": "global"
  }
]
```

**Response**: `201 Created` with `{"received": 1}`

**Note**: The `username` field is now automatically fetched from the Second Life website and stored in the avatar profile. The LSL script only needs to send `displayName`.

#### Get Tracking Config

```http
GET /api/tracking-config
X-API-Key: your-api-key
```

**Response**: `200 OK`
```json
{
  "trackedAvatars": ["uuid1", "uuid2"],
  "version": 123,
  "pollInterval": 60
}
```

#### Avatar Management

```http
GET /api/avatars          # List all avatars
POST /api/avatars         # Add avatar
DELETE /api/avatars/{key} # Remove avatar
PATCH /api/avatars/{key}  # Update avatar settings
```

#### Notification Channels

```http
GET /api/notification-channels              # List channels
POST /api/notification-channels             # Create channel
PUT /api/notification-channels/{id}         # Update channel
DELETE /api/notification-channels/{id}      # Delete channel
POST /api/notification-channels/{id}/test   # Test notification
```

#### Analytics Endpoints

```http
GET /api/live-visitors           # Currently online avatars
GET /api/recent-visitors         # Recent activity
GET /api/leaderboard             # Top avatars by time
GET /api/heatmap                 # Activity heatmap
GET /api/hourly                  # Hourly distribution
GET /api/daily                   # Daily statistics
GET /api/weekday                 # Day of week stats
GET /api/concurrent              # Concurrent presence over time
GET /api/duration-distribution   # Visit duration buckets
GET /api/frequency-vs-duration   # Scatter plot
GET /api/new-vs-returning        # New vs returning visitors
```

## Development

### Running Tests

```bash
make test              # Run all tests
make test-verbose      # Run tests with verbose output
make test-coverage     # Run tests with coverage report
```

### Common Commands

```bash
make up              # Start dev stack
make down            # Stop dev stack
make restart         # Restart dev stack
make logs            # Follow logs
make shell           # Open shell in container
make migrate         # Run database migrations
make user name=X pass=Y  # Create user
make routes          # List all routes
```

### Project Structure

```
sl-tracker/
├── config/              # Symfony configuration
├── docker/              # Docker configurations
│   ├── php/            # PHP-FPM + Caddy + Supervisor
│   └── caddy/          # Caddy web server config
├── lsl/                 # Second Life scripts
│   └── tracker.lsl     # Main tracking script
├── migrations/          # Database migrations
├── public/              # Web root
│   └── assets/         # Frontend JavaScript
│       ├── app.js      # Main entry point
│       ├── local-time.js
│       └── charts/     # ECharts modules
├── src/
│   ├── Command/        # Console commands
│   ├── Controller/     # HTTP controllers
│   ├── Entity/         # Doctrine entities
│   ├── Repository/     # Data access layer
│   └── Service/        # Business logic
├── templates/           # Twig templates
├── tests/              # PHPUnit tests
└── Makefile           # Command shortcuts
```

### Adding a New Chart

1. Add query method to `EventRepository`
2. Add `/api/<name>` endpoint in `ApiController`
3. Create `public/assets/charts/<name>.js`
4. Register in the `CHARTS` array in `public/assets/app.js`
5. Add `<div id="chart-<name>">` to dashboard template

### Adding a New Notification Channel

1. Create `XyzNotifier` class implementing `NotificationChannelInterface`
2. Add to `NotificationService` factory
3. Update `NotificationChannel::$type` enum/validation
4. Add type-specific config fields in UI

## Deployment

### Production Build

```bash
# Build production image
make prod-build

# Push to registry
make prod-push
```

### Production Checklist

- [ ] Set strong `API_SECRET_KEY`
- [ ] Configure HTTPS (Caddy handles this automatically with valid domain)
- [ ] Set `APP_ENV=prod`
- [ ] Use production MySQL instance
- [ ] Configure firewall to restrict API access if needed
- [ ] Set up log rotation
- [ ] Configure backup for database
- [ ] Monitor disk space (profile images in DB)

## Troubleshooting

### LSL Script Not Sending Events

1. Check script output for errors: enable `DEBUG = TRUE`
2. Verify `API_URL` and `API_KEY` are correct
3. Check server logs: `make logs-php`
4. Ensure API is accessible from Second Life (no firewall blocking)

### Notifications Not Working

**Telegram:**
1. Test the channel via UI (Channels → Test button)
2. Verify bot token and chat ID are correct
3. Check Telegram bot has permission to send messages
4. Check server logs for errors

**Matrix:**
1. Test the channel via UI (Channels → Test button)
2. Verify server URL, room ID, and bot token are correct
3. Ensure the bot is a member of the target room
4. Check server logs for errors

### Database Issues

```bash
# Re-run migrations
make migrate

# Access database shell
make shell
mysql -u user -p dbname
```

### Charts Not Loading

1. Check browser console for JavaScript errors
2. Verify ECharts CDN is accessible
3. Check API endpoints are returning data
4. Clear browser cache

## License

Proprietary

## Support

For issues and questions, please contact your system administrator.
