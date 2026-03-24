# Verdict Orchestrator

Drives the BE / FE / INFRA agents through the gated implementation phases defined in `IMPLEMENTATION_PLAN.md`.

## Setup

```bash
pip install -r orchestrator/requirements.txt
```

Requires Claude Code CLI to be installed and authenticated (`claude --version` should work).

## How It Works

```
orchestrator.py
    |
    ├── reads gate files from .gates/gate-N.json
    ├── spawns agents in parallel using claude_agent_sdk.query()
    └── writes logs to .agent-logs/
```

Each agent runs as a full Claude Code session with:
- `cwd` set to the project root
- `permission_mode: acceptEdits` (auto-accepts file edits)
- A track-specific system prompt
- Tools: Read, Edit, Write, Bash, Glob, Grep

**Agents do not talk to each other directly.** They coordinate through:
1. **Gate files** — `.gates/gate-N.json` signals when a phase is done
2. **Shared files** — `resources/js/types/*.ts` (type contracts), migrations (schema contracts)
3. **The implementation plan** — each agent reads `IMPLEMENTATION_PLAN.md` and `PRD.md`

## Usage

```bash
# Check current gate status
python orchestrator/orchestrator.py --status

# Run everything from the start
python orchestrator/orchestrator.py

# Run a specific phase
python orchestrator/orchestrator.py --phase 3

# Resume from phase 3 through phase 6
python orchestrator/orchestrator.py --from-phase 3

# Mark a gate as passed (after manual verification)
python orchestrator/orchestrator.py --mark-gate 0   # scaffold done
python orchestrator/orchestrator.py --mark-gate 1   # foundation verified
python orchestrator/orchestrator.py --mark-gate 2   # content system verified
python orchestrator/orchestrator.py --mark-gate 5-mini  # WebSocket confirmed
```

## Gate Flow

```
Gate 0  → manual (laravel new verdict, push to GitHub)
Gate 1  → manual (docker compose up works, auth works, CI green)
Gate 2  → manual (content pages render seeded data, tests green)
Gate 3  → manual (full session flow works, tests green)
Gate 4  → manual (rating + leaderboard works, tests green)
Gate 5-mini → manual (WebSocket connection confirmed — CRITICAL)
Gate 5  → manual (real-time verified end-to-end)
Gate 6  → auto-marked when phase 6 completes
```

## Why Manual Gates?

Gates 0-5 require human verification (open a browser, check Docker, run tests). Automating gate verification is possible but adds complexity — you'd need:
- A test runner agent that checks the exact acceptance criteria
- Docker health check polling
- Browser automation (Playwright MCP)

For a solo/small-team project, manual gate verification is faster and more reliable.

## Parallelism

Within each phase, independent tracks run via `asyncio.gather()`:

```python
await run_parallel(
    run_agent("BE", 2, ...),
    run_agent("FE", 2, ...),
    run_agent("INFRA", 2, ...),
)
```

This launches 3 Claude Code sessions simultaneously. Each session has its own context window and tool call budget. On a fast machine with a good API connection, parallel phases complete in roughly the time of the slowest track.

## Extending the Orchestrator

To add a new track (e.g., a QA agent that writes tests after each phase):

```python
# In phase_4():
await run_parallel(
    run_agent("BE", 4, ...),
    run_agent("FE", 4, ...),
    run_agent("QA", 4,
        "Write Pest feature tests covering the rating flow end-to-end...",
        STANDARD_BE_TOOLS,
    ),
)
```

To add automated gate verification:

```python
async def verify_gate_3() -> bool:
    """Run Pest tests and check they pass."""
    result = await run_agent("VERIFY", 3,
        "Run php artisan test and report whether all tests pass. Return 'PASS' or 'FAIL: <reason>'.",
        ["Bash"],
    )
    return result.strip().startswith("PASS")
```
