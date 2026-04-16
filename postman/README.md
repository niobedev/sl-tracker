# SL Tracker API - Postman Collection

A comprehensive Postman collection for testing the SL Tracker API.

## Setup

1. **Import the collection and environment:**
   - Open Postman
   - Click "Import" and select `SL-Tracker-API.postman_collection.json`
   - Click "Import" again and select `SL-Tracker-Local.postman_environment.json`

2. **Configure your environment:**
   - Go to Environments (gear icon)
   - Select "SL Tracker - Local"
   - Update `baseUrl` if your server runs on a different port
   - Update `apiKey` with your actual API secret key

3. **Select the environment:**
   - Use the dropdown in the top-right to select "SL Tracker - Local"

## Collections Overview

### Authentication
- Login page (for reference)

### Public Analytics (No API Key Required)
- `GET /api/live-visitors` - Currently online visitors
- `GET /api/recent-visitors` - Visitors by time period
- `GET /api/leaderboard` - Top visitors by visit count
- `GET /api/heatmap` - Activity heatmap
- `GET /api/hourly` - Hourly activity
- `GET /api/daily` - Daily statistics
- `GET /api/weekday` - Day-of-week statistics
- `GET /api/concurrent` - Concurrent presence
- `GET /api/duration-distribution` - Visit duration stats
- `GET /api/frequency-vs-duration` - Frequency correlation
- `GET /api/new-vs-returning` - Visitor breakdown

### Avatar Profiles (No API Key Required)
- `GET /api/avatar/{key}/profile` - Fetch Second Life profile
- `GET /api/avatar/{key}` - Get avatar stats & history
- `GET /api/reminders/active` - Active reminders

### Tracked Avatars (API Key Required)
- `GET /api/avatars` - List all tracked avatars
- `POST /api/avatars` - Add new avatar
- `PATCH /api/avatars/{key}` - Update avatar settings
- `DELETE /api/avatars/{key}` - Remove avatar

### Notification Channels (API Key Required)
- `GET /api/notification-channels` - List all channels
- `POST /api/notification-channels` - Create channel
- `PUT /api/notification-channels/{id}` - Update channel
- `DELETE /api/notification-channels/{id}` - Delete channel
- `POST /api/notification-channels/{id}/test` - Send test notification

### Events Ingestion (LSL Scripts)
- `POST /api/events` - Receive login/logout events

### Tracking Config (LSL Scripts)
- `GET /api/tracking-config` - Get avatars to track

## Running Tests

Each request includes test scripts that verify:
- Status codes
- Response structure
- Required fields

To run all tests:
1. Click "Collections" in the sidebar
2. Right-click "SL Tracker API"
3. Select "Run collection"
4. Click "Run SL Tracker API"

## Example: Setting Up Your API Key

1. Find your API key in `.env.local`:
   ```
   API_SECRET_KEY=your-secret-key-here
   ```

2. Update the Postman environment variable `apiKey` with this value

3. The collection automatically adds the `X-API-Key` header to protected requests

## Testing LSL Integration

To test the LSL script integration:

1. **Start tracking an avatar:**
   ```bash
   POST /api/avatars
   {
       "avatarKey": "12345678-1234-1234-1234-123456789012"
   }
   ```

2. **Send events from your LSL script:**
   ```bash
   POST /api/events
   [
       {
           "event_ts": "2024-01-15T10:30:00Z",
           "action": "login",
           "avatarKey": "12345678-1234-1234-1234-123456789012",
           "displayName": "Test Avatar",
           "username": "test.avatar"
       }
   ]
   ```

3. **Check tracking config:**
   ```bash
   GET /api/tracking-config
   ```
   Should return the avatar you just added.

## Troubleshooting

### 401 Unauthorized
- Make sure `apiKey` is set in your environment
- Verify the key matches `API_SECRET_KEY` in your `.env.local`

### 404 Not Found
- Check the URL path is correct
- For avatars, use lowercase UUID (e.g., `12345678-...` not `12345678-...`)

### 400 Bad Request
- Verify request body matches expected format
- Check required fields are present
- For events, ensure `action` is "login" or "logout" (lowercase)
