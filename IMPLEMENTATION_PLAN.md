# Verdict — Multi-Agent Implementation Plan

## Agent Tracks

Three agents run in parallel throughout, syncing only at gates:

| Track | Responsibility |
|---|---|
| **BE Agent** | Laravel controllers, services, middleware, jobs, events, route auth |
| **FE Agent** | Svelte 5 pages/components, Inertia props, Echo subscriptions |
| **INFRA Agent** | Migrations, seeders, Docker Compose, Nginx, GitHub Actions |

---

## Phase 1 — Foundation (Days 1–3)

**Gate 0 (sequential, ~2h):** `laravel new verdict --stack=inertia` + push to GitHub. Everything depends on this.

| Track | Task | Size |
|---|---|---|
| INFRA | Docker Compose (all 7 services) + Dockerfile + Nginx WS proxy config | M |
| BE | Hybrid auth (Breeze) + `EnsureParticipantIdentity` skeleton (no DB yet) + `GuestController` | M |
| FE | Svelte 5 adapter in `vite.config.js` + `Layout.svelte` shell | S |
| INFRA | GitHub Actions CI skeleton (test + lint jobs, build/deploy stubs) | S |

**Gate 1:** `docker compose up` → 200. Register/login works. Guest token issued. CI green.

---

## Phase 2 — Content System (Days 4–6)

All three tracks run independently after Gate 1.

| Track | Task | Size |
|---|---|---|
| INFRA | Migrations: `content_types`, `content_sets`, `content_items` + seed 200 baby boy names | S |
| BE | `ContentSetController` (index/show) + `scopeVisibleTo` + Pest tests for visibility | M |
| FE | `ContentSets/Index.svelte` + `ContentSets/Show.svelte` — paginated, typed via `types/content.ts` | M |

**Gate 2:** `migrate --seed` clean. Both pages render seeded data. Visibility scope test green in CI.

---

## Phase 3 — Session Management (Days 7–10)

| Track | Task | Size |
|---|---|---|
| INFRA | Migrations: `rating_configs`, `rating_tiers`, `verdict_sessions`, `session_participants` + seed system rating configs | M |
| BE | `SessionController` (create/store/join/enter/lobby) + upgrade `EnsureParticipantIdentity` to full DB lookup + Pest tests | M |
| FE | `Sessions/Create.svelte` + `Sessions/Join.svelte` + `Sessions/Lobby.svelte` (polling, `ParticipantList` component) | M |

> **Critical:** Use `verdict_sessions` table name — Laravel's session driver also uses a `sessions` table. Set `SESSION_DRIVER=redis`.

**Mini-gate (Day 8):** INFRA delivers migrations before BE tests can run. Lock Inertia prop shape for lobby before FE builds it.

**Gate 3:** Create → join (two tabs) → lobby shows both. Cross-session token rejection works. Host-only "Start" button visible.

---

## Phase 4 — Core Rating Experience (Days 11–15)

| Track | Task | Size |
|---|---|---|
| INFRA | `ratings` migration with `UNIQUE(session_id, participant_id, content_item_id)` + composite index on `(session_id, content_item_id)` | S |
| BE | `RatingController@store` (upsert, validates item belongs to session, dispatches stub job) + Pest tests | M |
| BE | `LeaderboardService::computeForSession()` — normalization formula, unit tested | M |
| FE | `RatingInput.svelte` (numeric/tier modes, stateless) + `Sessions/Rate.svelte` (skip/return UX, resume via `existingRatings` prop) | M |

**Mini-gate (Day 12):** Ratings saving confirmed before leaderboard FE starts.

| Track | Task | Size |
|---|---|---|
| BE | `LeaderboardController@show` wired to service | S |
| FE | `Sessions/Leaderboard.svelte` (manual refresh, `LeaderboardRow` component) | S |

> **Lock the `LeaderboardService` return shape at Gate 4** — Phase 5 broadcast payload depends on it.

**Gate 4:** Full flow without real-time. Rating upsert works. Leaderboard renders. Partial progress survives reload.

---

## Phase 5 — Real-Time Features (Days 16–20)

| Track | Task | Size |
|---|---|---|
| BE | Install Reverb. Implement all 6 broadcast events. `routes/channels.php` authorization. `UpdateLeaderboardJob` with Redis lock (TTL ≥ 5s). Upgrade `RatingController` to dispatch job. | M |
| FE | `resources/js/stores/echo.ts` — **module-level singleton**, initialized in `app.ts`, never inside a component | S |

**Mini-gate (Day 17) — highest-risk point in the project:** Confirm raw WebSocket connection works (use `wscat` first), then confirm Echo can authorize a presence channel. **Block all remaining Phase 5 FE work until this passes.** Nginx proxy misconfiguration here silently breaks everything.

| Track | Task | Size |
|---|---|---|
| FE | Upgrade Lobby (replace polling with presence channel) | M |
| FE | Upgrade Rate page (`SessionStarted` → auto-navigate, `RatingSubmitted` → confirmation, `SessionCompleted` → lock UI) | M |
| FE | Upgrade Leaderboard (subscribe to public channel, FLIP animation on `LeaderboardRow` using `svelte/animate`) | M |

**Gate 5:** Real-time end-to-end verified. Echo survives Inertia navigation. Leaderboard updates within 2–3s on all clients.

---

## Phase 6 — Polish + Hardening (Days 21–25)

All tasks are nearly independent.

| Track | Task | Size |
|---|---|---|
| BE | `ExpireSessionsCommand` (scheduled, broadcasts `SessionCompleted`) + `throttle:60,1` on rating endpoint | S |
| BE | `last_seen_at` updates + host-leave logic (promote or end session) | S |
| FE | Reconnection banner (Echo connection state) + `router.reload()` REST fallback | S |
| FE | Leaderboard rank number animation + keyboard a11y on `RatingInput` | S |
| INFRA | Complete `build` + `deploy` CI jobs (GHCR push, SSH deploy, `migrate --force`, health check) + extensibility seed test | M |

**Gate 6 (Acceptance):** Full smoke test. Clean `docker compose up --build` < 3min. Rate limiting returns 429 at 61st request. Session expiry fires scheduler. WS disconnect banner works.

---

## Dependency Graph

```
Gate 0 (scaffold — sequential)
    |
    +--------- P1-I1 (Docker) ──────── P1-I2 (CI)
    +--------- P1-B1 (Auth)
    +--------- P1-F1 (Svelte 5)
                    |
                Gate 1
                    |
    +---------------+---------------+
P2-I1 (migrations)  P2-B1 (ctrl)   P2-F1 (pages)
                    |
                Gate 2
                    |
    +---------------+---------------+
P3-I1 (migrations)  P3-B1 (ctrl)   P3-F1 (pages)
    |___mini-gate___|
                    |
                Gate 3
                    |
    +-------+-------+-------+
P4-I1   P4-B1   P4-B2   P4-F1
            |___mini-gate___|
                P4-B3   P4-F2
                    |
                Gate 4
                    |
    +---------------+
P5-B1           P5-F1 (Echo store)
        |___mini-gate (WS confirmed)___|
                P5-F2 (real-time upgrades)
                    |
                Gate 5
                    |
P6-B1  P6-B2  P6-F1  P6-F2  P6-I1
                    |
                Gate 6 ✓
```

---

## Critical Path

Gate 0 → Docker → Gate 1 → `verdict_sessions` migrations → Gate 3 → `LeaderboardService` shape locked → Gate 4 → **Nginx/Reverb WS mini-gate** → Gate 5 → Gate 6

The WebSocket proxy mini-gate in Phase 5 is the single highest-risk point — validate it with a raw `wscat` test before investing FE time in Echo subscriptions.

---

## Flagged Risks

| Risk | Resolution |
|---|---|
| `sessions` table name collision | Use `verdict_sessions`, `SESSION_DRIVER=redis` — decide at start of P3-I1 |
| `EnsureParticipantIdentity` used before table exists | Phase 1 version is DTO-only; Phase 3 adds DB lookup — cannot crash when table is absent |
| `LeaderboardService` shape changing after Phase 5 starts | Lock shape at Gate 4, treat as versioned interface |
| Echo placed in component (reconnects on Inertia nav) | Module singleton in `echo.ts`, imported in `app.ts` — enforced in P5-F1 |
| Redis lock TTL shorter than job execution | Set lock TTL to ≥ 5s (not 2s) to cover dispatch delay + query + broadcast |

---

## Task Complexity Summary

| Task | Track | Size | Phase |
|---|---|---|---|
| Docker Compose + Dockerfile + Nginx | INFRA | M | 1 |
| Hybrid auth + `EnsureParticipantIdentity` skeleton | BE | M | 1 |
| Svelte 5 vite config + layout shell | FE | S | 1 |
| GitHub Actions CI skeleton | INFRA | S | 1 |
| Content system migrations + name seed | INFRA | S | 2 |
| `ContentSetController` + visibility scope | BE | M | 2 |
| `ContentSets/Index` + `Show` pages | FE | M | 2 |
| Session + rating config migrations | INFRA | M | 3 |
| `SessionController` + middleware upgrade | BE | M | 3 |
| Create + Join + Lobby pages | FE | M | 3 |
| `ratings` migration + indexes | INFRA | S | 4 |
| `RatingController@store` | BE | M | 4 |
| `LeaderboardService` normalization | BE | M | 4 |
| `RatingInput` + `Sessions/Rate` page | FE | M | 4 |
| `LeaderboardController` wiring | BE | S | 4 |
| `Sessions/Leaderboard` page | FE | S | 4 |
| Reverb + broadcast events + `UpdateLeaderboardJob` | BE | M | 5 |
| Echo singleton store | FE | S | 5 |
| Real-time upgrades to all three pages | FE | M | 5 |
| Session expiry + rate limiting | BE | S | 6 |
| Reconnection + `last_seen_at` logic | BE | S | 6 |
| Reconnection banner + REST fallback | FE | S | 6 |
| Animation polish + a11y | FE | S | 6 |
| Complete CI/CD + extensibility verification | INFRA | M | 6 |

---

## Key Files (Critical Continuity Across Agents)

| File | Why It Matters |
|---|---|
| `app/Http/Middleware/EnsureParticipantIdentity.php` | Two-phase implementation (P1 skeleton → P3 full DB lookup); every session-scoped route depends on it |
| `app/Services/LeaderboardService.php` | Return shape is a hard contract between BE and FE from Gate 4 onward; also the direct source for P5 broadcast payload |
| `resources/js/stores/echo.ts` | Must be a module singleton (not a component) — placing it inside a component causes reconnect on every Inertia navigation |
| `routes/channels.php` | Channel names must exactly match event class `broadcastOn()` returns and FE Echo subscriptions; a mismatch silently blocks WebSocket auth |
| `docker-compose.yml` | Shared-image pattern for `app`/`reverb`/`worker`; Nginx WS proxy; service dependency chain every agent's environment depends on |
