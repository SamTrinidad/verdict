<script lang="ts">
    /**
     * Sessions/Lobby — waiting room shown after a session is created / joined.
     *
     * Props (injected by SessionController@lobby via Inertia):
     *   session      – the Session record (including status)
     *   participants – current SessionParticipant[] (refreshed by polling)
     *   auth         – authenticated User object, or null for guests
     *
     * Behaviour:
     *  • Polls Inertia every 3 seconds to refresh only the `participants` prop
     *    (router.reload with `only` avoids a full-page replacement).
     *  • Exposes the session ULID as a shareable join code with a copy button.
     *  • "Start Session" is rendered only for the host.
     *  • Status badge reflects the session lifecycle: waiting → active → completed.
     */
    import AppLayout from '@/Layouts/AppLayout.svelte';
    import ParticipantList from '@/Components/ParticipantList.svelte';
    import { router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import type { Session, SessionParticipant } from '@/types/session';

    // ── Auth user shape ───────────────────────────────────────────────────────
    interface AuthUser {
        id: number;
        name: string;
        email: string;
    }

    interface Props {
        session:      Session;
        participants: SessionParticipant[];
        auth:         AuthUser | null;
    }

    let { session, participants, auth }: Props = $props();

    // ── Derived ───────────────────────────────────────────────────────────────

    /** True only for the authenticated user who owns this session. */
    let isHost = $derived(
        auth !== null &&
        session.host_user_id !== null &&
        auth.id === session.host_user_id,
    );

    // ── Clipboard ─────────────────────────────────────────────────────────────
    let copied = $state(false);

    function copyCode() {
        navigator.clipboard.writeText(session.ulid).then(() => {
            copied = true;
            setTimeout(() => { copied = false; }, 2000);
        });
    }

    // ── Polling ───────────────────────────────────────────────────────────────
    // Reload only the `participants` prop every 3 s to track new arrivals.
    onMount(() => {
        const id = setInterval(
            () => router.reload({ only: ['participants'] }),
            3000,
        );
        return () => clearInterval(id);
    });

    // ── Session controls ─────────────────────────────────────────────────────
    let starting = $state(false);

    function startSession() {
        starting = true;
        router.post(`/sessions/${session.ulid}/start`, {}, {
            onFinish: () => { starting = false; },
        });
    }

    // ── Status badge ─────────────────────────────────────────────────────────
    function statusMeta(status: Session['status']): { label: string; cls: string } {
        switch (status) {
            case 'waiting':   return { label: 'Waiting for players', cls: 'badge--waiting'   };
            case 'active':    return { label: 'Active',              cls: 'badge--active'    };
            case 'completed': return { label: 'Completed',           cls: 'badge--completed' };
        }
    }

    /** Reactive status badge meta — recalculates when session.status changes. */
    let sm = $derived(statusMeta(session.status));
</script>

<AppLayout title="Session Lobby — Verdict">
    <div class="lobby">

        <!-- ── Page header ─────────────────────────────────────────────────── -->
        <header class="lobby__header">
            <div class="lobby__title-row">
                <h1 class="lobby__title">Session Lobby</h1>

                {#if session.content_set}
                    <span class="lobby__set-name">{session.content_set.name}</span>
                {/if}

                <span class="badge {sm.cls}" role="status" aria-live="polite">
                    {sm.label}
                </span>
            </div>

            {#if auth}
                <p class="lobby__user">
                    Signed in as <strong>{auth.name}</strong>
                </p>
            {/if}
        </header>

        <hr class="lobby__divider" />

        <!-- ── Share code ──────────────────────────────────────────────────── -->
        <section class="lobby__share" aria-label="Session join code">
            <p class="lobby__share-label">Share this code with participants</p>

            <div class="code-block">
                <code class="code-block__text" aria-label="Session code: {session.ulid}">
                    {session.ulid}
                </code>

                <button
                    type="button"
                    class="code-block__copy"
                    class:code-block__copy--done={copied}
                    onclick={copyCode}
                    aria-label={copied ? 'Copied!' : 'Copy session code to clipboard'}
                    title={copied ? 'Copied!' : 'Copy to clipboard'}
                >
                    {#if copied}
                        <!-- Checkmark icon -->
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M2.5 8.5L6 12L13.5 4.5" stroke="currentColor" stroke-width="2"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Copied!
                    {:else}
                        <!-- Clipboard icon -->
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <rect x="5" y="1" width="8" height="11" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M3 4H2.5A1.5 1.5 0 0 0 1 5.5v9A1.5 1.5 0 0 0 2.5 16h7A1.5 1.5 0 0 0 11 14.5V14"
                                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        Copy code
                    {/if}
                </button>
            </div>

            <p class="lobby__share-hint">
                Others can join at <strong>{window.location.origin}/sessions/join</strong>
            </p>
        </section>

        <!-- ── Participant list ────────────────────────────────────────────── -->
        <section class="lobby__participants" aria-label="Participants">
            <h2 class="lobby__section-title">
                Participants
                <span class="lobby__count">({participants.length})</span>
            </h2>

            <ParticipantList
                {participants}
                hostId={session.host_user_id ?? 0}
            />
        </section>

        <!-- ── Host controls ───────────────────────────────────────────────── -->
        {#if isHost && session.status === 'waiting'}
            <section class="lobby__controls" aria-label="Host controls">
                <hr class="lobby__divider" />

                <div class="lobby__controls-inner">
                    <div class="lobby__controls-info">
                        <p class="lobby__controls-title">Ready to start?</p>
                        <p class="lobby__controls-hint">
                            All participants are in. Starting will lock the session to new joins.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn--start"
                        disabled={starting || participants.length < 1}
                        aria-busy={starting}
                        onclick={startSession}
                    >
                        {starting ? 'Starting…' : 'Start Session'}
                    </button>
                </div>
            </section>
        {/if}

        <!-- ── Non-host waiting message ────────────────────────────────────── -->
        {#if !isHost && session.status === 'waiting'}
            <p class="lobby__waiting" aria-live="polite">
                ⏳ Waiting for the host to start the session…
            </p>
        {/if}

    </div>
</AppLayout>

<style>
    /* ── Page shell ── */
    .lobby {
        max-width: 44rem;
        margin-inline: auto;
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
    }

    /* ── Header ── */
    .lobby__header {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .lobby__title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
    }

    .lobby__title {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: #0f172a;
    }

    .lobby__set-name {
        font-size: 0.9375rem;
        color: #64748b;
        font-weight: 500;
    }

    .lobby__user {
        font-size: 0.875rem;
        color: #64748b;
    }

    /* ── Divider ── */
    .lobby__divider {
        border: none;
        border-top: 1px solid #e2e8f0;
        margin: 0;
    }

    /* ── Status badges ── */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .badge--waiting   { background: #fef3c7; color: #92400e; }
    .badge--active    { background: #dcfce7; color: #15803d; }
    .badge--completed { background: #f1f5f9; color: #64748b; }

    /* ── Share code ── */
    .lobby__share {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .lobby__share-label {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #1e293b;
    }

    .code-block {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1.125rem;
        border: 2px dashed #c7d2fe;
        border-radius: 0.75rem;
        background: #eef2ff;
        flex-wrap: wrap;
    }

    .code-block__text {
        flex: 1;
        font-family: ui-monospace, 'Cascadia Code', 'Fira Code', monospace;
        font-size: 1.0625rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        color: #4338ca;
        word-break: break-all;
        user-select: all;
    }

    .code-block__copy {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.4375rem 0.875rem;
        border-radius: 0.4375rem;
        border: 1px solid #a5b4fc;
        background: #ffffff;
        color: #4338ca;
        font-size: 0.8125rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.12s, color 0.12s, border-color 0.12s;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .code-block__copy:hover {
        background: #ede9fe;
    }

    .code-block__copy--done {
        background: #dcfce7;
        border-color: #86efac;
        color: #15803d;
    }

    .lobby__share-hint {
        font-size: 0.8125rem;
        color: #94a3b8;
        line-height: 1.5;
    }

    /* ── Participants section ── */
    .lobby__participants {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
    }

    .lobby__section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .lobby__count {
        font-weight: 400;
        color: #94a3b8;
        font-size: 0.9375rem;
    }

    /* ── Host controls ── */
    .lobby__controls-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .lobby__controls-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #1e293b;
    }

    .lobby__controls-hint {
        font-size: 0.8125rem;
        color: #64748b;
        margin-top: 0.125rem;
    }

    /* ── Buttons ── */
    .btn--start {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        padding: 0.6875rem 1.5rem;
        border-radius: 0.5rem;
        border: none;
        font-size: 0.9375rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        color: #ffffff;
        transition: opacity 0.12s, transform 0.12s;
        box-shadow: 0 2px 12px -2px rgba(99, 102, 241, 0.45);
        white-space: nowrap;
    }

    .btn--start:hover:not(:disabled) {
        opacity: 0.92;
        transform: translateY(-1px);
    }

    .btn--start:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        box-shadow: none;
        transform: none;
    }

    /* ── Waiting message ── */
    .lobby__waiting {
        text-align: center;
        color: #64748b;
        font-size: 0.9375rem;
        padding: 1rem;
        border-radius: 0.625rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
    }

    /* ── Responsive ── */
    @media (max-width: 540px) {
        .lobby__title-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .lobby__controls-inner {
            flex-direction: column;
            align-items: stretch;
        }

        .btn--start {
            width: 100%;
        }
    }
</style>
