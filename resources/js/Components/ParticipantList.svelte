<script lang="ts">
    /**
     * ParticipantList — pure display component for session participants.
     *
     * Props:
     *   participants – SessionParticipant[] to render
     *   hostId       – the session's host_user_id; matched against
     *                  participant.user_id to surface the "(Host)" badge.
     *                  Guests (user_id === null) never match.
     *
     * This component owns no data-fetching logic.  The parent decides whether
     * to source participants from the initial Inertia page props, a polling
     * reload, or a WebSocket channel.
     */
    import type { SessionParticipant } from '@/types/session';

    interface Props {
        participants: SessionParticipant[];
        hostId: number;
    }

    let { participants, hostId }: Props = $props();

    /** True when this participant is the session host. */
    function isHost(p: SessionParticipant): boolean {
        return p.user_id !== null && p.user_id === hostId;
    }

    /** Relative "joined N minutes ago" label for accessibility title text. */
    function joinedLabel(joinedAt: string): string {
        const diffMs  = Date.now() - new Date(joinedAt).getTime();
        const diffMin = Math.floor(diffMs / 60_000);
        if (diffMin < 1)  return 'just now';
        if (diffMin === 1) return '1 minute ago';
        if (diffMin < 60) return `${diffMin} minutes ago`;
        const diffHrs = Math.floor(diffMin / 60);
        return diffHrs === 1 ? '1 hour ago' : `${diffHrs} hours ago`;
    }
</script>

{#if participants.length === 0}
    <p class="pl-empty">No participants yet — share the session code to invite others.</p>
{:else}
    <ul class="pl" role="list" aria-label="Participants ({participants.length})">
        {#each participants as p (p.id)}
            <li
                class="pl__item"
                title="Joined {joinedLabel(p.joined_at)}"
            >
                <!-- Avatar initial -->
                <span class="pl__avatar" aria-hidden="true">
                    {p.display_name.charAt(0).toUpperCase()}
                </span>

                <!-- Name -->
                <span class="pl__name">{p.display_name}</span>

                <!-- Badges -->
                <span class="pl__badges">
                    {#if isHost(p)}
                        <span class="badge badge--host">Host</span>
                    {/if}
                    {#if p.guest_token !== null}
                        <span class="badge badge--guest">Guest</span>
                    {/if}
                </span>
            </li>
        {/each}
    </ul>
{/if}

<style>
    /* ── Empty state ── */
    .pl-empty {
        padding: 1.5rem 0;
        color: #94a3b8;
        font-size: 0.9375rem;
        text-align: center;
    }

    /* ── List ── */
    .pl {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    /* ── Row ── */
    .pl__item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 0.875rem;
        border-radius: 0.625rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        transition: background 0.12s;
    }

    .pl__item:hover {
        background: #f1f5f9;
    }

    /* ── Avatar circle ── */
    .pl__avatar {
        flex-shrink: 0;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        color: #ffffff;
        font-size: 0.875rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        user-select: none;
    }

    /* ── Name ── */
    .pl__name {
        flex: 1;
        font-size: 0.9375rem;
        font-weight: 500;
        color: #0f172a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* ── Badges ── */
    .pl__badges {
        display: flex;
        gap: 0.375rem;
        flex-shrink: 0;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
        line-height: 1;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .badge--host {
        background: #ede9fe;
        color: #5b21b6;
    }

    .badge--guest {
        background: #f1f5f9;
        color: #64748b;
    }
</style>
