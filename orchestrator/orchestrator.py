"""
Verdict Multi-Agent Orchestrator
Drives parallel BE / FE / INFRA agents through gated phases.

Usage:
    pip install claude-agent-sdk anyio
    python orchestrator.py                    # run from phase 1
    python orchestrator.py --phase 3          # resume from phase 3
    python orchestrator.py --gate 2           # re-run from gate 2
"""

import anyio
import anyio.abc
import asyncio
import argparse
import io
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path

# Force UTF-8 output on Windows (must happen before any print)
if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

from claude_agent_sdk import query, ClaudeAgentOptions, AgentDefinition, ResultMessage, SystemMessage, RateLimitEvent

# ---------------------------------------------------------------------------
# Retry config
# ---------------------------------------------------------------------------

# Keywords in exception messages that indicate a quota/rate-limit error
_RATE_LIMIT_KEYWORDS = ("rate limit", "ratelimit", "429", "quota", "overloaded", "capacity", "too many requests")

# How long to wait between retries if no reset time is available (seconds)
_RETRY_WAITS = [300, 900, 1800]  # 5 min, 15 min, 30 min

# Set to True by --sequential flag; phases use run_parallel() which respects this
SEQUENTIAL = False

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------

PROJECT_ROOT = Path(__file__).parent.parent
GATE_DIR = PROJECT_ROOT / ".gates"
LOG_DIR = PROJECT_ROOT / ".agent-logs"

GATE_DIR.mkdir(exist_ok=True)
LOG_DIR.mkdir(exist_ok=True)

# ---------------------------------------------------------------------------
# Gate helpers
# ---------------------------------------------------------------------------

def gate_passed(n: int) -> bool:
    return (GATE_DIR / f"gate-{n}.json").exists()

def mark_gate(n: int, meta: dict | None = None):
    path = GATE_DIR / f"gate-{n}.json"
    path.write_text(json.dumps({"passed_at": datetime.now(timezone.utc).isoformat(), **(meta or {})}), encoding="utf-8")
    print(f"\n✅  Gate {n} marked as passed.")

def require_gate(n: int):
    if not gate_passed(n):
        print(f"❌  Gate {n} has not been passed. Run phases up to gate {n} first.")
        sys.exit(1)

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

def log_path(track: str, phase: int) -> Path:
    return LOG_DIR / f"phase-{phase}-{track}-{datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')}.log"


def _is_rate_limit_error(exc: BaseException) -> bool:
    msg = str(exc).lower()
    return any(kw in msg for kw in _RATE_LIMIT_KEYWORDS)


def _parse_reset_seconds(exc: BaseException) -> int | None:
    """Try to extract a wait duration from the error message (e.g. 'retry after 3600s')."""
    import re
    for pattern in (r"retry.{0,10}after\D+(\d+)", r"reset.{0,10}in\D+(\d+)", r"wait\D+(\d+)\s*s"):
        m = re.search(pattern, str(exc), re.IGNORECASE)
        if m:
            return int(m.group(1))
    return None

# ---------------------------------------------------------------------------
# Agent runner
# ---------------------------------------------------------------------------

_SYSTEM_PROMPT_TEMPLATE = """You are the {track} agent for the Verdict project.
Verdict is a collaborative real-time rating app built with Laravel 11, Svelte 5, Inertia.js,
Laravel Reverb (WebSockets), MySQL 8.0, Redis 7, and Docker Compose.

Your working directory is the project root: {root}
Always read existing files before modifying them.
Follow the conventions in PRD.md and IMPLEMENTATION_PLAN.md.
When your task is complete, summarize exactly what you built and what tests pass.

ENVIRONMENT: PHP, Composer, and Node.js are NOT installed on the host. All runtime commands must go through Docker.

When docker-compose.yml does not yet exist or the stack is not running, use docker run:
  PHP/artisan:  MSYS_NO_PATHCONV=1 docker run --rm -v "{root}:/app" --workdir /app php:8.4-cli php <args>
  Composer:     MSYS_NO_PATHCONV=1 docker run --rm -v "{root}:/app" --workdir /app composer:latest <args>
  Node/npm:     MSYS_NO_PATHCONV=1 docker run --rm -v "{root}:/app" --workdir /app node:22-alpine <args>

Once docker-compose.yml exists and the stack is running (`docker compose up -d` has been run), prefer:
  MSYS_NO_PATHCONV=1 docker compose exec app php artisan <args>
  MSYS_NO_PATHCONV=1 docker compose exec app composer <args>
  MSYS_NO_PATHCONV=1 docker compose exec node npm <args>

FRONTEND BUILDS: The `node` service runs `npm run dev` (Vite HMR) automatically on `docker compose up`.
- The entire project directory is bind-mounted in all containers — NEVER use `docker cp` for assets.
- Svelte/CSS changes are hot-reloaded instantly; no manual rebuild step is needed during development.
- For a production build only: MSYS_NO_PATHCONV=1 docker compose exec node npm run build

Always prefix docker commands with MSYS_NO_PATHCONV=1 to prevent Git Bash from mangling paths.
Always use Windows-style absolute paths in -v volume mounts (e.g. "C:/Users/samtr/Projects/verdict:/app")."""


async def run_agent(track: str, phase: int, prompt: str, tools: list[str], max_retries: int = 3) -> str:
    """
    Run a single agent track and return its final result text.
    Streams progress to stdout with a [TRACK] prefix.
    Retries automatically on rate-limit errors, waiting for the quota to reset.
    """
    log_file = log_path(track, phase)

    options = ClaudeAgentOptions(
        cwd=str(PROJECT_ROOT),
        allowed_tools=tools,
        permission_mode="acceptEdits",
        system_prompt=_SYSTEM_PROMPT_TEMPLATE.format(track=track, root=PROJECT_ROOT),
        max_turns=80,
    )

    for attempt in range(1, max_retries + 1):
        lines: list[str] = []
        result_text = ""
        rate_limit_wait: float | None = None

        try:
            async for message in query(prompt=prompt, options=options):
                if isinstance(message, RateLimitEvent):
                    # Record the wait needed but do NOT sleep or break inside the generator —
                    # breaking while anyio's internal task group is live causes cancel-scope errors.
                    info = getattr(message, "rate_limit_info", None) or getattr(message, "info", None)
                    reset_at = getattr(info, "reset_at", None) if info else None
                    if reset_at:
                        if isinstance(reset_at, str):
                            reset_dt = datetime.fromisoformat(reset_at.replace("Z", "+00:00"))
                        else:
                            reset_dt = reset_at
                        rate_limit_wait = max(10, (reset_dt - datetime.now(timezone.utc)).total_seconds() + 30)
                        print(f"\n[{track}] Rate limit — resets at {reset_at} ({rate_limit_wait:.0f}s). Will retry after stream ends.")
                    else:
                        rate_limit_wait = _RETRY_WAITS[min(attempt - 1, len(_RETRY_WAITS) - 1)]
                        print(f"\n[{track}] Rate limit — waiting {rate_limit_wait:.0f}s after stream ends (attempt {attempt}/{max_retries}).")

                elif isinstance(message, ResultMessage):
                    result_text = message.result
                    line = f"[{track}] DONE: {result_text[:120]}..."
                    print(line)
                    lines.append(line)

                elif isinstance(message, SystemMessage):
                    if message.subtype == "init":
                        line = f"[{track}] Session {message.data.get('session_id', '?')[:8]} started"
                        print(line)
                        lines.append(line)

                else:
                    if hasattr(message, "content"):
                        for block in getattr(message, "content", []):
                            if hasattr(block, "text") and block.text:
                                preview = block.text[:120].replace("\n", " ")
                                line = f"[{track}] {preview}"
                                print(line)
                                lines.append(line)

        except Exception as exc:
            if _is_rate_limit_error(exc) and attempt < max_retries:
                rate_limit_wait = _parse_reset_seconds(exc) or _RETRY_WAITS[min(attempt - 1, len(_RETRY_WAITS) - 1)]
                print(f"\n[{track}] Rate limit exception. Waiting {rate_limit_wait}s (attempt {attempt}/{max_retries})...")
            else:
                if lines:
                    log_file.write_text("\n".join(lines), encoding="utf-8")
                raise

        # Generator is fully consumed — safe to sleep and retry now
        if rate_limit_wait is not None:
            if attempt >= max_retries:
                # Exhausted retries — log and return empty rather than crashing the task group.
                # Work already written to disk by earlier successful attempts is preserved.
                print(f"\n[{track}] WARNING: rate limited on all {max_retries} attempts. Skipping remaining work for this track.")
                if lines:
                    log_file.write_text("\n".join(lines), encoding="utf-8")
                return ""
            await anyio.sleep(rate_limit_wait)
            rate_limit_wait = None
            continue

        # Success
        log_file.write_text("\n".join(lines), encoding="utf-8")
        return result_text

    return ""  # unreachable, satisfies type checker


async def run_parallel(*coros) -> list[str]:
    """Run agent coroutines sequentially (default, rate-limit-safe) or concurrently (--parallel).

    Sequential mode avoids all agents hammering the API quota simultaneously,
    which causes cascading rate-limit retries. Use --parallel only with API keys
    or Max plans where parallelism is practical.
    """
    if SEQUENTIAL:
        return [await coro for coro in coros]

    results: list[str] = [""] * len(coros)

    async def _run(i: int, coro) -> None:
        results[i] = await coro

    async with anyio.create_task_group() as tg:
        for i, coro in enumerate(coros):
            tg.start_soon(_run, i, coro)

    return results


# ---------------------------------------------------------------------------
# Phase definitions
# ---------------------------------------------------------------------------

STANDARD_BE_TOOLS = ["Read", "Edit", "Write", "Bash", "Glob", "Grep"]
STANDARD_FE_TOOLS = ["Read", "Edit", "Write", "Bash", "Glob", "Grep"]
STANDARD_INFRA_TOOLS = ["Read", "Edit", "Write", "Bash", "Glob", "Grep"]


async def phase_1():
    """Foundation — parallel after Gate 0 scaffold."""
    print("\n━━━  PHASE 1: Foundation  ━━━")

    # Gate 0: manual — the human must run `laravel new verdict` first
    if not gate_passed(0):
        print("⏸  Gate 0 (scaffold) not passed. Please:")
        print("   1. Run: laravel new verdict --stack=inertia (or equivalent)")
        print("   2. Push to GitHub")
        print("   3. Run: python orchestrator.py --mark-gate 0")
        sys.exit(0)

    # All three tracks run in parallel
    await run_parallel(
        run_agent(
            "INFRA", 1,
            """Set up the full Docker Compose environment for the Verdict project.

Tasks:
1. Create docker-compose.yml with 7 services: app (PHP 8.4-FPM), nginx, reverb, worker, mysql:8.0, redis:7-alpine, mailpit (axllent/mailpit)
2. Create Dockerfile (PHP 8.4-FPM base, Composer install, Node build stage; app/reverb/worker share the same image)
3. Create nginx/default.conf with the /app/ WebSocket proxy stanza (proxy_pass http://reverb:8080, Upgrade headers, 60s read_timeout)
4. Update .env.example with REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST, BROADCAST_CONNECTION=reverb, SESSION_DRIVER=redis, QUEUE_CONNECTION=redis
5. Verify docker compose up boots all services cleanly (run the command and check output)

Use PRD.md for the full Docker Compose service list and Nginx config snippet.""",
            STANDARD_INFRA_TOOLS,
        ),
        run_agent(
            "BE", 1,
            """Implement hybrid guest + account authentication for the Verdict project.

Tasks:
1. Scaffold Laravel Breeze (minimal variant) for authenticated user flow (register, login, logout)
2. Create GuestController with an `enter` POST endpoint: accepts display_name, generates UUID guest_token, stores in signed HttpOnly cookie, returns token in JSON response
3. Create app/Http/Middleware/EnsureParticipantIdentity.php — Phase 1 version:
   - Reads user_id from Laravel session (authenticated users) OR guest_token from request cookie/header
   - Attaches a lightweight ParticipantDTO object to $request->participant (no DB lookup yet — table doesn't exist)
   - If neither is present, return 401 JSON response
4. Register the middleware in bootstrap/app.php as 'participant.identity'
5. Create route groups in routes/web.php:
   - auth:web protected group (placeholder for session creation routes)
   - participant.identity group (placeholder for session-scoped routes)
6. Write a basic Pest test confirming the guest token endpoint returns a token

Important: The EnsureParticipantIdentity middleware must NOT crash when the session_participants table doesn't exist — Phase 3 will add the DB lookup.""",
            STANDARD_BE_TOOLS,
        ),
        run_agent(
            "FE", 1,
            """Configure Svelte 5 and the base frontend layout for the Verdict project.

Tasks:
1. Update vite.config.js to use @sveltejs/vite-plugin-svelte with compilerOptions: { runes: true } for Svelte 5 runes mode
2. Update package.json to ensure svelte@^5, @sveltejs/vite-plugin-svelte@^5 are present; run npm install
3. Create resources/js/Layouts/AppLayout.svelte — a base layout with a nav bar slot, main content slot, and a simple responsive shell (no styling framework required; basic CSS is fine)
4. Update resources/js/app.js to import the layout and confirm Inertia renders a test page
5. Ensure VITE_HOST=localhost is set in .env and .env.example (the `node` compose service handles HMR automatically)
6. Create a simple resources/js/Pages/Welcome.svelte that uses AppLayout and renders "Verdict" as a heading

Svelte 5 note: use $props() rune for component props, $state() for reactive state, $effect() for side effects.""",
            STANDARD_FE_TOOLS,
        ),
        run_agent(
            "INFRA", 1,
            """Set up GitHub Actions CI pipeline for the Verdict project.

Tasks:
1. Create .github/workflows/ci.yml with:
   - Trigger: push to main, pull_requests targeting main
   - test job: PHP 8.4, MySQL 8.0 + Redis 7 as GH Actions services, composer install, php artisan migrate, vendor/bin/pest
   - lint job (parallel with test): pint --test, npm run lint (eslint), npx svelte-check
   - build job (main branch only, after test passes): docker build stub (echo placeholder)
   - deploy job (main branch only, after build): SSH deploy stub (echo placeholder)
2. Set up caching:
   - Composer: cache vendor/ keyed on composer.lock hash
   - npm: cache node_modules/ keyed on package-lock.json hash
3. Document required secrets in .github/SECRETS.md: DB_PASSWORD, REDIS_PASSWORD, APP_KEY, DEPLOY_SSH_KEY, DEPLOY_HOST, DEPLOY_USER

The CI must pass with zero tests (an empty Pest suite is fine for now — the pipeline must be green).""",
            STANDARD_INFRA_TOOLS,
        ),
    )

    print("\n⏸  Phase 1 complete. Please verify manually:")
    print("   • docker compose up → http://localhost returns 200")
    print("   • Register + login flow works")
    print("   • Guest token endpoint returns token")
    print("   • CI pipeline green on main")
    print("\nWhen verified, run: python orchestrator.py --mark-gate 1")


async def phase_2():
    """Content System."""
    require_gate(1)
    print("\n━━━  PHASE 2: Content System  ━━━")

    await run_parallel(
        run_agent(
            "INFRA", 2,
            """Create the Content System database migrations and seeders for Verdict.

Tasks:
1. Create migration for content_types: id, slug (name|image|video), label, config JSON, timestamps
2. Create migration for content_sets: id, content_type_id FK, name, slug, description, user_id FK (nullable), visibility ENUM(system|public|private), meta JSON, timestamps; add INDEX(user_id, visibility)
3. Create migration for content_items: id, content_set_id FK, display_value, meta JSON, sort_order INT, timestamps
4. Create ContentTypeSeeder: seed one row with slug='name', label='Baby Names'
5. Create ContentSetSeeder: seed one content_set with visibility='system', user_id=NULL, name='Baby Boy Names 2024'
6. Create ContentItemSeeder: seed ~200 baby boy names (Oliver, Liam, Noah, William, James, etc.) as content_items with sort_order
7. Register all seeders in DatabaseSeeder

Run php artisan migrate:fresh --seed to verify everything works cleanly.

Also create a shared TypeScript type file at resources/js/types/content.ts with:
  ContentType, ContentSet, ContentItem interfaces matching the DB schema (snake_case keys, nullable fields marked with | null)
This is the shared contract that the FE agent will use.""",
            STANDARD_INFRA_TOOLS,
        ),
        run_agent(
            "BE", 2,
            """Implement the Content System backend for Verdict.

Tasks:
1. Create app/Models/ContentType.php with hasMany ContentSets relationship
2. Create app/Models/ContentSet.php with:
   - belongsTo ContentType
   - hasMany ContentItems
   - scopeVisibleTo(User|null $user): queries WHERE visibility='system' OR visibility='public' OR (visibility='private' AND user_id = $user->id)
   - All fillable fields set
3. Create app/Models/ContentItem.php with belongsTo ContentSet
4. Create app/Http/Controllers/ContentSetController.php:
   - index(): query ContentSet with scopeVisibleTo(Auth::user()), paginate(20), pass to Inertia page ContentSets/Index
   - show($slug): find by slug, load paginated items (20 per page), pass to Inertia ContentSets/Show
5. Add resource routes for content_sets in routes/web.php
6. Create app/Http/Resources/ContentSetResource.php and ContentItemResource.php
7. Write Pest feature tests in tests/Feature/ContentSetTest.php:
   - Guest sees system + public sets but not private sets
   - Authenticated user sees own private sets
   - Show page returns items for the correct set

Note: The migration files will be provided by the INFRA agent. Run the tests against a fresh migration if needed.""",
            STANDARD_BE_TOOLS,
        ),
        run_agent(
            "FE", 2,
            """Build the Content Set browsing pages for Verdict.

Tasks:
1. Read resources/js/types/content.ts for the shared ContentSet and ContentItem types (created by INFRA agent)
2. Create resources/js/Pages/ContentSets/Index.svelte:
   - Uses $props() to receive: contentSets (paginated), auth (user or null)
   - Renders a grid of content set cards (name, description, content type label, item count)
   - Each card has a "Use this set" link/button → will link to session creation (stub href for now)
   - Inertia.js pagination links at the bottom
3. Create resources/js/Pages/ContentSets/Show.svelte:
   - Uses $props() to receive: contentSet, items (paginated), auth
   - Renders the set name/description header
   - Renders a list of ContentItem display_values (names for now)
   - Designed with a ContentItem.svelte wrapper component so images can slot in later without changing Show.svelte
   - Inertia.js pagination
4. Create resources/js/Components/ContentItem.svelte:
   - Accepts item prop (ContentItem type)
   - For content_type slug='name': renders the display_value as text
   - For other types: renders display_value as-is (extensibility stub)
5. Update AppLayout.svelte to include a nav link to /content-sets

Svelte 5: use $props() rune, typed props with TypeScript generics where helpful.""",
            STANDARD_FE_TOOLS,
        ),
    )

    print("\n⏸  Phase 2 complete. Please verify:")
    print("   • php artisan migrate:fresh --seed runs cleanly")
    print("   • ContentSets/Index and /Show render seeded data in browser")
    print("   • Visibility scope tests pass in CI")
    print("\nWhen verified, run: python orchestrator.py --mark-gate 2")


async def phase_3():
    """Session Management."""
    require_gate(2)
    print("\n━━━  PHASE 3: Session Management  ━━━")

    # INFRA must run first to give BE the migrations for tests
    print("  → Running INFRA migrations first (mini-gate pattern)...")
    await run_agent(
        "INFRA", 3,
        """Create the Session System migrations and seeders for Verdict.

CRITICAL: Do NOT create a table named 'sessions' — Laravel's session driver uses that name.
Use 'verdict_sessions' as the table name for Verdict's session model.
Set SESSION_DRIVER=redis in .env.example (Redis is already in the stack).

Tasks:
1. Create migration for rating_configs: id, type ENUM(numeric|tier), name, is_system BOOL, timestamps
2. Create migration for rating_tiers: id, rating_config_id FK, label, color (hex), rank_order TINYINT (lower = better), timestamps
3. Create migration for verdict_sessions: id, ulid (unique, public join code), content_set_id FK, host_user_id FK (nullable), status ENUM(waiting|active|completed), settings JSON, rating_config_id FK, timestamps, expires_at
4. Create migration for session_participants: id, session_id FK (references verdict_sessions), user_id FK (nullable), guest_token (nullable, string), display_name, joined_at, last_seen_at; add constraint: either user_id OR guest_token must be set (check constraint or enforced in application layer — document the choice)
5. Create RatingConfigSeeder: seed two system configs:
   - Numeric: type='numeric', name='1-10 Scale', is_system=true; add a rating_tier for each value isn't needed for numeric
   - Tier S/A/B/C/D: type='tier', name='S-Tier', is_system=true; seed rating_tiers with S(rank 1), A(rank 2), B(rank 3), C(rank 4), D(rank 5) with appropriate hex colors
6. Run php artisan migrate:fresh --seed to verify

Also add to resources/js/types/session.ts: Session, SessionParticipant, RatingConfig, RatingTier TypeScript interfaces.""",
        STANDARD_INFRA_TOOLS,
    )

    print("  → INFRA migrations done. Running BE + FE in parallel...")
    await run_parallel(
        run_agent(
            "BE", 3,
            """Implement Session Management controllers and upgrade EnsureParticipantIdentity for Verdict.

IMPORTANT: The sessions table is named 'verdict_sessions'. The Eloquent model should have protected $table = 'verdict_sessions'.

Tasks:
1. Create app/Models/VerdictSession.php (table: verdict_sessions) with relationships: belongsTo ContentSet, belongsTo User (as host), hasMany SessionParticipants, belongsTo RatingConfig; add a ULID generation in boot()
2. Create app/Models/SessionParticipant.php with: belongsTo VerdictSession, belongsTo User (nullable), isHost() helper method
3. Create app/Models/RatingConfig.php with hasMany RatingTiers
4. Create app/Models/RatingTier.php with belongsTo RatingConfig
5. Create app/Http/Controllers/SessionController.php:
   - create(): returns Inertia Sessions/Create with availableContentSets (scopeVisibleTo) and ratingConfigs
   - store(): validates input, generates ULID (Str::ulid()), creates verdict_session, creates host session_participant, redirects to lobby
   - join($ulid): returns Inertia Sessions/Join with the session
   - enter($ulid): POST, validates join code, creates session_participant for current user/guest, redirects to lobby
   - lobby($ulid): returns Inertia Sessions/Lobby with participant list and session status
6. UPGRADE EnsureParticipantIdentity to full DB lookup:
   - For session-scoped routes (where $ulid is in route): look up session_participants WHERE session_id = (session by ulid) AND (user_id = auth user OR guest_token = cookie token)
   - Attach the full SessionParticipant model to $request->participant
   - If no participant found: return 403 (not just 401)
7. Write Pest feature tests:
   - Create session → ULID generated, host is first participant
   - Join via valid ULID → new participant created
   - Invalid ULID → 404
   - Guest token from session A rejected in session B
   - Non-host cannot start session""",
            STANDARD_BE_TOOLS,
        ),
        run_agent(
            "FE", 3,
            """Build the Session Management pages for Verdict.

Read resources/js/types/session.ts for Session, SessionParticipant, RatingConfig, RatingTier types.

Tasks:
1. Create resources/js/Pages/Sessions/Create.svelte:
   - Props: contentSets[], ratingConfigs[], auth (user or null)
   - Form: content set selector (dropdown or card grid), rating config selector, display name input (shown only if guest/auth.user is null)
   - Submits POST to /sessions via Inertia router.post()
   - Show validation errors inline

2. Create resources/js/Pages/Sessions/Join.svelte:
   - Props: auth
   - Single ULID code input (uppercase, 26 chars)
   - Display name input (if guest)
   - Submits POST to /sessions/{ulid}/enter

3. Create resources/js/Components/ParticipantList.svelte:
   - Props: participants (SessionParticipant[]), hostId (number)
   - Renders each participant's display_name with a "(Host)" badge for the host
   - Designed as a pure display component — data source (polling vs WebSocket) is the parent's concern

4. Create resources/js/Pages/Sessions/Lobby.svelte:
   - Props: session (Session), participants (SessionParticipant[]), auth
   - Shows session ULID as a shareable code with copy-to-clipboard button
   - Renders <ParticipantList> with the participant list
   - Shows "Start Session" button ONLY if auth.user.id === session.host_user_id
   - Polls for participant list updates every 3 seconds via:
     onMount(() => { const id = setInterval(() => router.reload({ only: ['participants'] }), 3000); return () => clearInterval(id); })
   - Session status badge (waiting/active/completed)

Svelte 5: use $props(), $state(), $effect() or onMount for polling.""",
            STANDARD_FE_TOOLS,
        ),
    )

    print("\n⏸  Phase 3 complete. Please verify:")
    print("   • Full create → join (two browser tabs) → lobby shows both participants")
    print("   • Cross-session guest token rejection works")
    print("   • Host sees 'Start Session' button; guest does not")
    print("   • All P3 Pest tests green in CI")
    print("\nWhen verified, run: python orchestrator.py --mark-gate 3")


async def phase_4():
    """Core Rating Experience."""
    require_gate(3)
    print("\n━━━  PHASE 4: Core Rating Experience  ━━━")

    # INFRA first (ratings migration), then BE + FE in parallel
    await run_agent(
        "INFRA", 4,
        """Create the ratings migration and indexes for Verdict.

Tasks:
1. Create migration for ratings table:
   - id, session_id FK (references verdict_sessions), participant_id FK (references session_participants), content_item_id FK
   - numeric_value TINYINT UNSIGNED (nullable)
   - tier_id FK (references rating_tiers, nullable)
   - rated_at TIMESTAMP, updated_at TIMESTAMP
   - UNIQUE constraint: (session_id, participant_id, content_item_id)
   - Composite index: (session_id, content_item_id) — critical for leaderboard queries
2. Add sort_order index on content_items.sort_order
3. Run php artisan migrate to verify

Also update resources/js/types/rating.ts with: Rating, LeaderboardEntry { contentItemId, displayValue, score, voteCount, rank } TypeScript interfaces.
LeaderboardEntry.score is 0-1 float (lower = better), rank is 1-based integer.""",
        STANDARD_INFRA_TOOLS,
    )

    print("  → Running BE (Rating + Leaderboard) and FE (RatingInput + Rate page) in parallel...")
    await run_parallel(
        run_agent(
            "BE", 4,
            """Implement RatingController and LeaderboardService for Verdict.

Tasks:
1. Create app/Http/Controllers/RatingController.php:
   - store(): guarded by participant.identity middleware
     * Validate content_item_id belongs to the session's content_set
     * Validate numeric_value OR tier_id matches the session's rating_config type
     * Upsert via Rating::updateOrCreate(['session_id'=>..., 'participant_id'=>..., 'content_item_id'=>...], [...])
     * Set rated_at on create, updated_at always
     * Dispatch UpdateLeaderboardJob::dispatch($session)->delay(2) [stub job for now]
     * Return JSON { success: true, contentItemId: ... }
   - index(): return all ratings for the current participant in this session (for resume UX)
2. Create a stub app/Jobs/UpdateLeaderboardJob.php (just logs for now — Phase 5 will fill it in)
3. Create app/Services/LeaderboardService.php with computeForSession(VerdictSession $session): array
   - For numeric sessions: SELECT content_item_id, AVG(numeric_value) as avg_score, COUNT(*) as vote_count FROM ratings WHERE session_id = ? GROUP BY content_item_id
     Normalize: score = avg_score / max_numeric_value (from rating_config settings JSON, default 10)
   - For tier sessions: JOIN rating_tiers, SELECT AVG(rank_order) as avg_rank, COUNT(*) as vote_count
     Normalize: score = avg_rank / max(rank_order for this config)
   - Join content_items to get display_value
   - Return array of LeaderboardEntry sorted by score ASC (lower = better): [contentItemId, displayValue, score, voteCount]
   - Items with zero ratings appear at the end (score = 1.0 or null handled gracefully)
4. Create app/Http/Controllers/LeaderboardController.php:
   - show($ulid): call LeaderboardService::computeForSession(), return Inertia Sessions/Leaderboard
5. Add routes: POST /sessions/{ulid}/ratings, GET /sessions/{ulid}/ratings, GET /sessions/{ulid}/leaderboard
6. Write Pest unit tests for LeaderboardService normalization:
   - Numeric: 3 ratings of [2,4,6] with max 10 → score = 0.4
   - Tier: S/A/B/C/D (ranks 1-5), two S ratings → score = 0.2
   - Zero ratings edge case: returns empty array (not an error)
   - Mixed sessions: guard against tier_id on numeric session

LOCK this LeaderboardService return shape — Phase 5 broadcast payload depends on it:
  [{ contentItemId: int, displayValue: string, score: float, voteCount: int }]""",
            STANDARD_BE_TOOLS,
        ),
        run_agent(
            "FE", 4,
            """Build the core Rating Experience UI for Verdict.

Read resources/js/types/rating.ts and resources/js/types/session.ts for types.

Tasks:
1. Create resources/js/Components/RatingInput.svelte — STATELESS core component:
   - Props: ratingConfig (RatingConfig), contentItem (ContentItem), currentValue (number | null)
   - For numeric type: render a row of numbered buttons (1 to max_value from ratingConfig.settings.max)
   - For tier type: render a row of tier buttons (S/A/B/C/D) with tier colors as background
   - Emits a 'rate' CustomEvent with detail { contentItemId, value } — parent owns the state
   - Highlights the currently selected value
   - Keyboard accessible (tabindex, aria-label)

2. Create resources/js/Pages/Sessions/Rate.svelte:
   - Props: session, contentItems (ContentItem[]), ratingConfig, existingRatings ({ [contentItemId]: number })
   - Local state: currentIndex ($state), submittedRatings ($state, initialized from existingRatings)
   - On 'rate' event from RatingInput:
     * Optimistically mark item as rated in submittedRatings
     * POST to /sessions/{ulid}/ratings via router.post({ content_item_id, numeric_value OR tier_id })
   - Navigation: Previous / Next / Skip buttons
   - Progress indicator: "X of N rated" with a progress bar
   - Skip/return UX: show unanswered items count, allow jumping back to unanswered items
   - On session load, existingRatings pre-fills submittedRatings (resume support)

3. Create resources/js/Components/LeaderboardRow.svelte:
   - Props: entry (LeaderboardEntry), rank (number)
   - Displays rank number, displayValue, score (formatted as %), voteCount
   - Designed for future FLIP animation (bind the key to contentItemId in the parent)

4. Create resources/js/Pages/Sessions/Leaderboard.svelte:
   - Props: session, leaderboard (LeaderboardEntry[])
   - Renders <LeaderboardRow> for each entry, keyed by contentItemId
   - Manual refresh button: router.reload({ only: ['leaderboard'] })
   - "No ratings yet" empty state

Svelte 5: use $props(), $state() for all reactive state.""",
            STANDARD_FE_TOOLS,
        ),
    )

    print("\n⏸  Phase 4 complete. Please verify:")
    print("   • Full flow: create → join → rate → view leaderboard (manual refresh)")
    print("   • Rating upsert (re-rating same item) works")
    print("   • Partial progress survives page reload (existingRatings prop)")
    print("   • LeaderboardService unit tests green in CI")
    print("   • LeaderboardService return shape is documented and locked")
    print("\nWhen verified, run: python orchestrator.py --mark-gate 4")


async def phase_5():
    """Real-Time Features."""
    require_gate(4)
    print("\n━━━  PHASE 5: Real-Time Features  ━━━")

    # BE Reverb setup + FE Echo store in parallel — then mini-gate before P5-F2
    print("  → Running Reverb setup and Echo store in parallel...")
    await run_parallel(
        run_agent(
            "BE", 5,
            """Implement Laravel Reverb broadcasting and the UpdateLeaderboardJob for Verdict.

Tasks:
1. Install Laravel Reverb: composer require laravel/reverb; php artisan reverb:install
2. Update .env: BROADCAST_CONNECTION=reverb, REVERB_APP_KEY=verdict-local, REVERB_APP_SECRET=verdict-secret, REVERB_HOST=reverb (the Docker service name), REVERB_PORT=8080
3. Implement the 6 broadcast events in app/Events/:
   - SessionStarted: ShouldBroadcast, broadcasts on PresenceChannel("session.{ulid}")
   - ParticipantJoined: ShouldBroadcast, broadcasts on PresenceChannel("session.{ulid}"), payload: participant display_name and id
   - ParticipantLeft: ShouldBroadcast, broadcasts on PresenceChannel("session.{ulid}"), payload: participant id
   - RatingSubmitted: ShouldBroadcast, broadcasts on PrivateChannel("session.{ulid}.participant.{participantId}"), payload: contentItemId
   - LeaderboardUpdated: ShouldBroadcast, broadcasts on Channel("session.{ulid}.leaderboard") [PUBLIC], payload: FULL LeaderboardService output array
   - SessionCompleted: ShouldBroadcast, broadcasts on PresenceChannel("session.{ulid}")
4. Implement routes/channels.php authorization:
   - Broadcast::channel('presence-session.{ulid}', function($user, $ulid) { ... }) — verify participant exists in session; return ['id'=>$participant->id, 'name'=>$participant->display_name]
   - Broadcast::channel('private-session.{ulid}.participant.{participantId}', ...) — verify participant ID matches current participant
   - Public leaderboard channel needs no auth
5. Fill in app/Jobs/UpdateLeaderboardJob.php:
   - Constructor: receives VerdictSession $session
   - handle(): acquire Redis lock Cache::lock("leaderboard_job_{$session->id}", 5) — TTL MUST be 5 seconds, not 2
   - If lock acquired: run LeaderboardService::computeForSession(), broadcast LeaderboardUpdated event, release lock
   - If lock NOT acquired: return early (another job will fire)
6. Update RatingController@store to dispatch UpdateLeaderboardJob::dispatch($session)->delay(now()->addSeconds(2))
7. Update SessionController: broadcast SessionStarted when host triggers start; broadcast SessionCompleted on end
8. Write Pest tests: job broadcasts with correct payload, second dispatch within 2s does not double-broadcast""",
            STANDARD_BE_TOOLS,
        ),
        run_agent(
            "FE", 5,
            """Create the Echo singleton store for Verdict.

CRITICAL ARCHITECTURAL DECISION: Echo MUST be initialized as a module-level singleton,
NOT inside a Svelte component (even layout). If initialized inside a component, it reconnects
on every Inertia page navigation, causing duplicate channel subscriptions.

Tasks:
1. Install: npm install laravel-echo pusher-js
2. Create resources/js/stores/echo.ts as a plain ES module (NOT a Svelte store):
   ```typescript
   import Echo from 'laravel-echo';
   import Pusher from 'pusher-js';

   window.Pusher = Pusher;

   const echo = new Echo({
     broadcaster: 'reverb',
     key: import.meta.env.VITE_REVERB_APP_KEY,
     wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
     wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
     forceTLS: false,
     enabledTransports: ['ws'],
   });

   export default echo;
   ```
3. Import echo.ts in resources/js/app.ts (the Inertia bootstrap file) so it initializes once on page load
4. Add VITE_REVERB_APP_KEY, VITE_REVERB_HOST, VITE_REVERB_PORT to .env.example
5. Do NOT subscribe to any channels yet — just ensure the module exports the echo instance

Verify the module can be imported without runtime errors (no channel subscriptions needed at this step).""",
            STANDARD_FE_TOOLS,
        ),
    )

    print("\n⏸  MINI-GATE: WebSocket connection must be verified before continuing.")
    print("   1. Ensure the 'reverb' Docker service is running")
    print("   2. Test raw WS: wscat -c ws://localhost:8080/app/verdict-local?protocol=7&client=js&version=8.0&flash=false")
    print("   3. Open browser, check DevTools Network tab for WS connection to reverb")
    print("   4. Confirm Echo can authorize a presence channel (check Laravel logs)")
    print("\nThis is the highest-risk integration point. Do NOT proceed until WS works.")
    print("\nWhen WebSocket connection is confirmed, run: python orchestrator.py --mark-gate 5-mini")

    if not gate_passed(51):  # gate 51 = phase 5 mini-gate
        print("⏸  Phase 5 mini-gate not passed. Run: python orchestrator.py --mark-gate 5-mini")
        sys.exit(0)

    print("  → Mini-gate passed. Running real-time FE upgrades...")
    await run_agent(
        "FE", 5,
        """Upgrade all three session pages to use real-time WebSockets for Verdict.

Import echo from resources/js/stores/echo (the singleton — never construct new Echo() in a component).
All channel subscriptions live in $effect() blocks that return cleanup functions.

Tasks:
1. UPGRADE Sessions/Lobby.svelte — replace polling with presence channel:
   - Remove the setInterval polling
   - In $effect(): const channel = echo.join(`presence-session.${session.ulid}`)
     .here(users => participants = users)
     .joining(user => participants = [...participants, user])
     .leaving(user => participants = participants.filter(p => p.id !== user.id))
   - Listen for 'SessionStarted' on the presence channel → router.visit(`/sessions/${session.ulid}/rate`)
   - Return cleanup: () => channel.stopListening().leave()

2. UPGRADE Sessions/Rate.svelte — add real-time events:
   - Listen to private channel `private-session.${ulid}.participant.${participant.id}`:
     * 'RatingSubmitted' event → show a checkmark/confirmation on the rated item (local UI state)
   - Listen to presence channel `presence-session.${ulid}`:
     * 'SessionCompleted' event → disable all rating buttons, show "Session ended" banner
   - All listeners in $effect() with cleanup

3. UPGRADE Sessions/Leaderboard.svelte — subscribe to public leaderboard channel:
   - In $effect(): echo.channel(`session.${ulid}.leaderboard`).listen('LeaderboardUpdated', (data) => { leaderboard = data.leaderboard; })
   - Remove manual refresh button (or keep as fallback — your call)
   - Add FLIP animation to LeaderboardRow position changes using svelte/animate flip:
     * Import { flip } from 'svelte/animate'
     * {#each leaderboard as entry (entry.contentItemId)} <div animate:flip={{ duration: 400 }}><LeaderboardRow {entry} /></div> {/each}
     * This requires keying by contentItemId (NOT array index) so Svelte tracks item movement

4. Add a connection state indicator in AppLayout.svelte:
   - Import echo from stores/echo
   - In $effect(): echo.connector.pusher.connection.bind('state_change', ({ current }) => { connectionState = current; })
   - Show a small "Reconnecting..." banner when connectionState !== 'connected'
   - On reconnect ('connected' state): router.reload() to catch any missed events""",
        STANDARD_FE_TOOLS,
    )

    print("\n⏸  Phase 5 complete. Please verify:")
    print("   • Lobby updates in real time when a second participant joins")
    print("   • Host clicks Start → all clients navigate to Rate page")
    print("   • Rating a name → leaderboard updates within 2-3s on all clients")
    print("   • Leaderboard rows animate position changes")
    print("   • Echo survives an Inertia page navigation (navigate away and back)")
    print("\nWhen verified, run: python orchestrator.py --mark-gate 5")


async def phase_6():
    """Polish + Hardening."""
    require_gate(5)
    print("\n━━━  PHASE 6: Polish + Hardening  ━━━")

    await run_parallel(
        run_agent(
            "BE", 6,
            """Implement session expiry enforcement and rate limiting for Verdict.

Tasks:
1. Create app/Console/Commands/ExpireSessionsCommand.php:
   - Finds verdict_sessions WHERE expires_at < now() AND status != 'completed'
   - Updates status to 'completed'
   - Broadcasts SessionCompleted event for each expired session
   - Register in routes/console.php: Schedule::command(ExpireSessionsCommand::class)->everyFiveMinutes()
2. Ensure the worker container runs the scheduler: add a supervisor config or update the worker container command to run both queue:work and schedule:work
3. Rate limiting:
   - Apply throttle:60,1 middleware to RatingController@store (60 per minute per participant)
   - Apply throttle:10,1 to the GuestController@enter endpoint (prevent token farming)
   - Return proper 429 responses
4. Host-leave logic in SessionController or a SessionObserver:
   - When a participant's last_seen_at is > 30 seconds ago and they are the host, check the settings JSON for host_leave_action (default: 'end')
   - If 'end': mark session as completed, broadcast SessionCompleted
   - If 'promote': find the next oldest participant, update host_user_id, broadcast a HostChanged event
5. Write tests: 61st rating returns 429, expired session gets completed by scheduler""",
            STANDARD_BE_TOOLS,
        ),
        run_agent(
            "BE", 6,
            """Implement participant reconnection and last_seen_at tracking for Verdict.

Tasks:
1. Add a UpdateLastSeenMiddleware that runs after() on all participant-authenticated requests:
   - Updates session_participants.last_seen_at = now() for the current participant
   - Register as a post-middleware on the participant.identity route group
2. Add ParticipantReconnected broadcast event:
   - Fires when a participant's last_seen_at resumes after a gap > 30 seconds
   - Detect in UpdateLastSeenMiddleware: if $participant->last_seen_at < now()->subSeconds(30), fire event
   - Broadcasts on PresenceChannel("session.{ulid}") with payload: participantId
3. Ensure Docker services restart gracefully:
   - Add restart: unless-stopped to worker and reverb services in docker-compose.yml
   - Add HEALTHCHECK instruction to Dockerfile: CMD curl -f http://localhost/up || exit 1""",
            STANDARD_BE_TOOLS,
        ),
        run_agent(
            "FE", 6,
            """Polish animations and accessibility for Verdict.

Tasks:
1. Leaderboard rank number animation:
   - In LeaderboardRow.svelte, add a $derived rank number that triggers a CSS transition on change
   - The rank number should briefly highlight (flash yellow → normal) when the position changes
   - Use Svelte transitions (fly or fade) for items entering and leaving the leaderboard
2. RatingInput.svelte accessibility:
   - Ensure all buttons have aria-label describing the action: e.g., "Rate Oliver as S tier" or "Rate Oliver 7 out of 10"
   - Ensure the currently selected rating has aria-pressed="true"
   - Keyboard navigation: arrow keys move between options, Enter/Space selects
3. Add a skip/unanswered count badge in Sessions/Rate.svelte header: "3 unanswered" with a button to jump to the first unanswered item
4. Session completion screen: when SessionCompleted fires, show a full-screen overlay with the final leaderboard and a "Save your results? Create an account." prompt (stub the account creation flow — just show the prompt for now)""",
            STANDARD_FE_TOOLS,
        ),
        run_agent(
            "INFRA", 6,
            """Complete the CI/CD pipeline and verify extensibility for Verdict.

Tasks:
1. Complete the GitHub Actions build job in .github/workflows/ci.yml:
   - docker buildx build --push ghcr.io/${{ github.repository }}:${{ github.sha }}
   - Also tag as :latest
   - Use /tmp/.buildx-cache for layer caching with restore-keys: docker-
2. Complete the deploy job (runs on main after build):
   - SSH to deploy host using DEPLOY_SSH_KEY secret
   - docker compose pull
   - docker compose up -d --remove-orphans
   - php artisan migrate --force
   - Health check: curl -f http://localhost/up (retry 3x with 5s sleep)
3. Add HEALTHCHECK to Dockerfile
4. Add depends_on with healthcheck condition in docker-compose.yml so app waits for MySQL to be ready
5. Extensibility verification:
   - Seed a second content_type row: slug='image', label='Images'
   - Seed a small content_set with 3-5 items where display_value is a placeholder S3 URL
   - Verify LeaderboardService handles it without code changes (it should — normalization is type-agnostic)
   - Document the 'adding a new content type' runbook as a comment block in app/Models/ContentType.php""",
            STANDARD_INFRA_TOOLS,
        ),
    )

    mark_gate(6)
    print("\n🎉  Phase 6 complete — Verdict build finished!")
    print("\nFinal acceptance checklist:")
    print("   • Full smoke test: 2 guests + 1 auth user → create → join → rate → real-time leaderboard → session ends → upgrade prompt")
    print("   • docker compose up --build from clean state < 3 minutes, all health checks pass")
    print("   • CI pipeline green on main including build + deploy")
    print("   • Rate limiting: 61st rating in one minute returns 429")
    print("   • Session expiry: set expires_at to past, confirm scheduler fires SessionCompleted")
    print("   • WS disconnect banner appears and disappears correctly")


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

PHASES = {
    1: phase_1,
    2: phase_2,
    3: phase_3,
    4: phase_4,
    5: phase_5,
    6: phase_6,
}


def parse_args():
    parser = argparse.ArgumentParser(description="Verdict multi-agent orchestrator")
    parser.add_argument("--phase", type=int, choices=PHASES.keys(), help="Run a specific phase")
    parser.add_argument("--from-phase", type=int, choices=PHASES.keys(), help="Run from this phase through phase 6")
    parser.add_argument("--mark-gate", type=str, help="Mark a gate as passed (e.g. 0, 1, 2, 5-mini)")
    parser.add_argument("--status", action="store_true", help="Show current gate status")
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--sequential", action="store_true", default=True,
                      help="Run tracks one at a time (default — rate-limit safe for Pro)")
    mode.add_argument("--parallel", action="store_true", default=False,
                      help="Run tracks concurrently (faster, but hits Pro rate limits)")
    return parser.parse_args()


async def main():
    global SEQUENTIAL
    args = parse_args()
    SEQUENTIAL = not args.parallel

    if args.status:
        print("Gate status:")
        for n in range(7):
            label = f"gate-{n}"
            passed = (GATE_DIR / f"{label}.json").exists()
            print(f"  Gate {n}: {'✅ passed' if passed else '⬜ pending'}")
        # Check mini-gate
        mini = (GATE_DIR / "gate-51.json").exists()
        print(f"  Gate 5-mini: {'✅ passed' if mini else '⬜ pending'}")
        return

    if args.mark_gate:
        key = args.mark_gate
        if key == "5-mini":
            mark_gate(51)
        else:
            mark_gate(int(key))
        return

    if args.phase:
        await PHASES[args.phase]()
    elif args.from_phase:
        for n in range(args.from_phase, 7):
            await PHASES[n]()
    else:
        # Run all phases in sequence
        for n in range(1, 7):
            await PHASES[n]()


if __name__ == "__main__":
    anyio.run(main)
