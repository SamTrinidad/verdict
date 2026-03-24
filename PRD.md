# Verdict: Product Requirements Document

## Overview

A collaborative rating/ranking app where groups of people join a session and rate items together. Starting with baby names, the system is designed to be extensible to images and videos. Think "tier list maker meets multiplayer leaderboard."

---

## Core Features

1. **Content Sets** — Curated lists of items to rate (baby boy names to start), extensible to images and videos
2. **Rating System** — Numeric 0–X scale (0 = best) OR custom tier categories (S/A/B/C/D or user-defined names/colors)
3. **Group Sessions** — Users join via a short code, rate independently, see a live group leaderboard
4. **Real-Time Leaderboard** — Shows how the group collectively ranked items as ratings come in

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 |
| Frontend | Svelte 5 via Inertia.js |
| Real-Time | Laravel Reverb (WebSockets) |
| Database | MySQL 8.0 |
| Cache / Queue | Redis 7 |
| PHP Runtime | **PHP 8.4-FPM** |
| Local Dev | Docker Compose |
| CI/CD | GitHub Actions |

### Why Inertia.js + Svelte 5?

Inertia is the right call. You get Laravel's routing/auth/sessions for free, no separate API to maintain, and Svelte 5's reactivity model pairs naturally with Inertia's page props. The real-time layer (Reverb via WebSockets) sits alongside Inertia cleanly — WebSocket events update local Svelte state, not page navigations.

**When you'd choose a separate SPA instead:** If you planned a mobile app sharing the same API, or if the frontend team wanted full independence from Laravel's routing. Neither applies here.

---

## Architecture Overview

```
Browser (Svelte 5)
  |-- HTTP/XHR (Inertia)      --> Nginx --> PHP 8.4-FPM (Laravel) --> MySQL
  |-- WebSocket (Echo/Reverb) --> Nginx --> Reverb Server         --> Redis (pub/sub)
  |-- Static assets            --> Nginx (served directly)

Queue Worker (separate container, same image) --> MySQL + Redis --> Reverb broadcasts
```

---

## Database Schema

### Content System (Extensible)

```sql
content_types
  id, slug (name|image|video), label, config JSON, timestamps

content_sets
  id, content_type_id FK, name, slug, description
  user_id FK         -- nullable; NULL = global/system set (admin-managed)
  visibility ENUM    -- system | public | private
                     --   system:  global, read-only for all users
                     --   public:  user-created, browseable by anyone
                     --   private: user-created, visible only to owner
  meta JSON          -- e.g. gender filter for names
  timestamps
  INDEX (user_id, visibility)

content_items
  id, content_set_id FK
  display_value    -- "Oliver" for names, S3 URL for images, embed URL for video
  meta JSON        -- type-specific: origin/meaning for names, duration for video
  sort_order INT
  timestamps
```

**Ownership rules:**
- `user_id IS NULL` + `visibility = 'system'` — curated global sets (e.g. the built-in baby names list). Only admins can create or modify these.
- `user_id = X` + `visibility = 'public'` — user-created, discoverable and usable by anyone when creating a session.
- `user_id = X` + `visibility = 'private'` — user-created, only visible and usable by the owner.

**Extensibility:** `display_value` stores whatever the content type needs. Type-specific metadata goes in `meta JSON`. Adding images or videos requires no schema changes to the ratings tables.

**Access query pattern:** When a user browses content sets to start a session, the query is:
```sql
WHERE visibility = 'system'
   OR (visibility = 'public')
   OR (visibility = 'private' AND user_id = ?)
```

### Session System

```sql
sessions
  id, ulid           -- public join code
  content_set_id FK
  host_user_id FK    -- nullable, guests can host
  status ENUM        -- waiting | active | completed
  settings JSON      -- max_participants, allow_late_join, etc.
  rating_config_id FK
  timestamps, expires_at

session_participants
  id, session_id FK
  user_id FK         -- nullable (authenticated users)
  guest_token        -- nullable (guest users)
  display_name, joined_at, last_seen_at
  -- Either user_id OR guest_token is set, never both
```

### Rating System

```sql
rating_configs
  id, type ENUM (numeric|tier), name
  is_system BOOL     -- system defaults vs user-created
  timestamps

rating_tiers
  id, rating_config_id FK, label  -- "S", "A", "Trash"
  color              -- hex color
  rank_order TINYINT -- lower = better (mirrors numeric 0=best convention)
  timestamps

ratings
  id, session_id FK, participant_id FK, content_item_id FK
  numeric_value TINYINT UNSIGNED  -- nullable
  tier_id FK                      -- nullable
  rated_at, updated_at
  UNIQUE(session_id, participant_id, content_item_id)
```

### Leaderboard Score Normalization

Both rating types normalize to a 0–1 score (lower = better) in `LeaderboardService`:

```
Numeric: score = avg(numeric_value) / max_possible_value
Tier:    score = avg(rank_order) / max_tier_rank_order
```

This enables consistent leaderboard computation regardless of rating type, and supports future cross-session comparisons.

---

## Docker Compose Services

| Service | Image | Purpose |
|---|---|---|
| `app` | Custom (PHP 8.4-FPM) | Laravel application |
| `nginx` | nginx:alpine | Reverse proxy, static assets, WS proxy |
| `reverb` | Same as `app` | Runs `php artisan reverb:start` |
| `worker` | Same as `app` | Runs `php artisan queue:work` |
| `mysql` | mysql:8.0 | Primary database |
| `redis` | redis:7-alpine | Cache, queues, pub/sub |
| `mailpit` | axllent/mailpit | Dev mail catcher (dev only) |

`app`, `reverb`, and `worker` all share one Docker image — single `docker build`, different `command` override in `docker-compose.yml`. In production, the `node` dev service is replaced by assets baked into the image at build time.

### Nginx WebSocket Proxy Config

```nginx
location /app/ {
    proxy_pass http://reverb:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 60s;
}
```

---

## Authentication: Hybrid Guest + Account

**Guests:**
- Enter a display name → get a `guest_token` (stored in localStorage + signed cookie)
- Can join sessions and rate — scoped to that session only
- No account required to participate

**Authenticated Users:**
- Standard Laravel session auth (cookie-based — Inertia works natively with Laravel sessions)
- Can create sessions, view rating history, manage custom rating configs

**Guest → Account Upgrade Path:**
- After session ends, prompt: "Save your results? Create an account."
- On account creation, backfill `user_id` on their `session_participants` and `ratings` records
- Single SQL update — the schema planned for this with nullable `user_id` + `guest_token`

**Key Middleware — `EnsureParticipantIdentity`:**
Resolves whether the requester is an authenticated user or a valid guest token holder, and attaches the `SessionParticipant` model to the request. Keeps controllers clean.

```
Session creation → auth:web required
Session joining  → guest OR auth:web (EnsureParticipantIdentity)
Rating endpoints → session participant auth (guest_token OR user session)
Leaderboard view → public, no auth required
```

---

## Real-Time Broadcasting (Laravel Reverb)

### Channel Strategy

| Channel | Type | Purpose |
|---|---|---|
| `presence-session.{ulid}` | Presence | Who's online, join/leave events |
| `private-session.{ulid}.participant.{id}` | Private | Rating confirmations, nudges |
| `session.{ulid}.leaderboard` | Public | Leaderboard updates for all in session |

### Events

| Event | Channel | Trigger |
|---|---|---|
| `SessionStarted` | Presence | Host starts the session |
| `ParticipantJoined` | Presence | New participant joins |
| `ParticipantLeft` | Presence | Participant disconnects |
| `RatingSubmitted` | Private | Confirms individual rating saved |
| `LeaderboardUpdated` | Public | Pushes new leaderboard snapshot |
| `SessionCompleted` | Presence | Session ends, ratings locked |

### Leaderboard Update Strategy

Do NOT recompute on every rating — that's O(n) DB queries under load. Instead:

**Debounced recompute (launch approach):** When a rating is saved, dispatch a queue job with a 2-second delay. A second rating within 2 seconds replaces the queued job via a Redis lock keyed on `session_id`. One DB query per 2-second window maximum.

**Future optimization:** Maintain the leaderboard as a Redis sorted set. Update in O(log n) per rating, broadcast delta only.

### Svelte 5 + Echo Integration Pattern

```javascript
// Initialize Echo once in a persistent module store — NOT in a component
// This survives Inertia page navigations without reconnecting

$effect(() => {
    const channel = Echo.join(`session.${sessionUlid}`)
        .here(handleParticipantList)
        .joining(handleParticipantJoined)
        .leaving(handleParticipantLeft)
        .listen('LeaderboardUpdated', handleLeaderboardUpdate);

    return () => channel.stopListening('LeaderboardUpdated').leave();
});
```

---

## CI/CD Pipeline (GitHub Actions)

### Trigger
Push to `main`, pull requests targeting `main`.

### Jobs

```
Push / PR to main
  ├── test         Run Pest suite (MySQL + Redis as GH Actions services)
  ├── lint         Pint + ESLint + Svelte check  [parallel with test]
  └── [main branch only, after test passes]
      ├── build    docker build → push to ghcr.io/yourorg/verdict:{sha} + :latest
      └── deploy   SSH → docker compose up → migrate --force → health check
```

### Caching

```yaml
- uses: actions/cache@v4
  with:
    path: vendor
    key: composer-${{ hashFiles('composer.lock') }}

- uses: actions/cache@v4
  with:
    path: node_modules
    key: npm-${{ hashFiles('package-lock.json') }}

- uses: actions/cache@v4
  with:
    path: /tmp/.buildx-cache
    key: docker-${{ github.sha }}
    restore-keys: docker-
```

### Required Secrets

`DB_PASSWORD`, `REDIS_PASSWORD`, `APP_KEY`, `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`

---

## Build Order

### Phase 1 — Foundation (Days 1–3)
- Scaffold `laravel new verdict` with Inertia + Svelte 5 adapter
- Configure `vite.config.js` for Svelte 5
- Set up `docker-compose.yml`, verify all services boot
- Implement hybrid guest + account auth
- Wire up GitHub Actions test + lint (done early so every PR is covered)

### Phase 2 — Content System (Days 4–6)
- Migrations for `content_types`, `content_sets`, `content_items`
- Seed first content set: ~200 baby boy names
- Build `ContentSets/Index.svelte` and `ContentSets/Show.svelte`
- Verify DB → Laravel → Inertia → Svelte pipeline end to end

### Phase 3 — Session Management (Days 7–10)
- Migrations for `sessions`, `session_participants`, `rating_configs`, `rating_tiers`
- `SessionController@store` — creates session with ULID join code
- `SessionController@join` — validates code, creates participant record
- Build `Sessions/Create.svelte`, `Sessions/Join.svelte`, `Sessions/Lobby.svelte`
- Lobby uses polling initially (real-time upgrade in Phase 5)

### Phase 4 — Core Rating Experience (Days 11–15)
- `RatingController@store` — validates + saves ratings
- Build `RatingInput.svelte` component (handles both numeric and tier modes)
- Build `Sessions/Rate.svelte` — items one at a time or as a list
- Implement skip/return UX for undecided items
- `LeaderboardService` + `Sessions/Leaderboard.svelte` (manual refresh for now)

### Phase 5 — Real-Time Features (Days 16–20)
- Install + configure `laravel-reverb`, `laravel-echo`, `pusher-js`
- Set up channel authorization in `channels.php`
- Upgrade lobby: replace polling with presence channel
- Upgrade leaderboard: debounced queue job + `LeaderboardUpdated` broadcast
- Upgrade session start: broadcast `SessionStarted` → redirect all participants
- Animate leaderboard position changes with Svelte transitions

### Phase 6 — Polish + Hardening (Days 21–25)
- Session expiry enforcement
- Participant reconnection handling
- Host-leave logic (promote next or end session)
- Rate limiting on rating endpoints
- Complete Docker build + deploy CI/CD jobs
- Confirm extensibility: seed an image content type, verify schema handles it

---

## Architectural Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Inertia navigation tears down WebSocket | Initialize Echo in a persistent Svelte module store, not in a component |
| Leaderboard slow at scale | Index `(session_id, content_item_id)` on ratings; queries are always session-scoped |
| Guest tokens forgeable or shared cross-session | Tokens validated server-side against `session_id`; a token from session A is rejected in session B |
| Reverb connection drops mid-session | Echo reconnection config + REST fallback on leaderboard for degraded mode |

---

## Key Files

| File | Purpose |
|---|---|
| `database/migrations/` | All schema migrations — content, sessions, ratings are the foundational data layer |
| `app/Services/LeaderboardService.php` | Score normalization across numeric and tier types; determines real-time broadcast integration |
| `app/Http/Middleware/EnsureParticipantIdentity.php` | Resolves guest vs authenticated participant on every session-scoped request |
| `resources/js/Pages/Sessions/Rate.svelte` | Primary user-facing experience; composes `RatingInput` and manages WebSocket subscriptions |
| `routes/channels.php` | Reverb channel authorization — must match channel naming strategy exactly |
| `docker-compose.yml` | Full local dev environment including separate `reverb` and `worker` containers |
| `.github/workflows/ci.yml` | Full CI/CD pipeline definition |
