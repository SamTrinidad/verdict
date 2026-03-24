<script lang="ts">
    /**
     * ContentSets/Index — browsable grid of all visible content contentSets.
     *
     * Props (injected by ContentSetController@index via Inertia):
     *   contentSets  – paginated ResourceCollection<ContentSetResource>
     *   auth  – authenticated User object, or null for guests
     */
    import AppLayout from '@/Layouts/AppLayout.svelte';
    import Pagination from '@/Components/Pagination.svelte';
    import type { ContentSet } from '@/types/content';
    import type { Paginated } from '@/types/pagination';

    // ── Auth user shape (subset of App\Models\User) ──────────────────────────
    interface AuthUser {
        id: number;
        name: string;
        email: string;
    }

    interface Props {
        contentSets: Paginated<ContentSet>;
        auth: AuthUser | null;
    }

    let { contentSets, auth }: Props = $props();

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Map visibility value to a human-readable badge label + colour class. */
    function visibilityMeta(vis: ContentSet['visibility']): { label: string; cls: string } {
        switch (vis) {
            case 'system':  return { label: 'System',  cls: 'badge--system'  };
            case 'public':  return { label: 'Public',  cls: 'badge--public'  };
            case 'private': return { label: 'Private', cls: 'badge--private' };
        }
    }
</script>

<AppLayout title="Content Sets">
    <div class="cs-index">

        <!-- ── Page header ─────────────────────────────────────────────────── -->
        <header class="cs-index__header">
            <div>
                <h1 class="cs-index__title">Content Sets</h1>
                <p class="cs-index__subtitle">
                    Choose a set to use in your rating session.
                </p>
            </div>
            {#if auth}
                <span class="cs-index__user">Signed in as {auth.name}</span>
            {/if}
        </header>

        <!-- ── Empty state ─────────────────────────────────────────────────── -->
        {#if contentSets.data.length === 0}
            <div class="cs-index__empty">
                <p>No content contentSets are available yet.</p>
            </div>

        {:else}

            <!-- ── Card grid ─────────────────────────────────────────────────── -->
            <ul class="cs-grid" role="list">
                {#each contentSets.data as set (set.id)}
                    {@const vis = visibilityMeta(set.visibility)}
                    <li class="cs-card">

                        <!-- Type label + visibility badge -->
                        <div class="cs-card__meta">
                            {#if set.content_type}
                                <span class="badge badge--type">
                                    {set.content_type.label}
                                </span>
                            {/if}
                            <span class="badge {vis.cls}">{vis.label}</span>
                        </div>

                        <!-- Title -->
                        <h2 class="cs-card__name">
                            <a href="/content_contentSets/{set.slug}" class="cs-card__name-link">
                                {set.name}
                            </a>
                        </h2>

                        <!-- Description -->
                        {#if set.description}
                            <p class="cs-card__desc">{set.description}</p>
                        {/if}

                        <!-- Footer: item count + CTA -->
                        <div class="cs-card__footer">
                            <span class="cs-card__count">
                                {#if set.items_count !== null}
                                    {set.items_count} item{set.items_count === 1 ? '' : 's'}
                                {:else}
                                    &mdash;
                                {/if}
                            </span>

                            <!--
                                "Use this set" — stub href points to the future session
                                creation page.  The content_set query param will be
                                picked up by Sessions/Create.svelte in Phase 3.
                            -->
                            <a
                                href="/sessions/create?content_set={set.slug}"
                                class="btn btn--primary"
                                aria-label="Use {set.name} in a new session"
                            >
                                Use this set
                            </a>
                        </div>

                    </li>
                {/each}
            </ul>

            <!-- ── Pagination ─────────────────────────────────────────────── -->
            <Pagination links={contentSets.meta.links} />

            <!-- Summary line -->
            <p class="cs-index__summary">
                Showing {contentSets.meta.from ?? 0}–{contentSets.meta.to ?? 0}
                of {contentSets.meta.total} set{contentSets.meta.total === 1 ? '' : 's'}
            </p>

        {/if}
    </div>
</AppLayout>

<style>
    /* ── Page shell ── */
    .cs-index {
        max-width: 72rem;
        margin-inline: auto;
    }

    .cs-index__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 2rem;
    }

    .cs-index__title {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: #0f172a;
    }

    .cs-index__subtitle {
        margin-top: 0.25rem;
        font-size: 0.9375rem;
        color: #64748b;
    }

    .cs-index__user {
        font-size: 0.875rem;
        color: #64748b;
        align-self: center;
    }

    .cs-index__empty {
        padding: 3rem 1rem;
        text-align: center;
        color: #94a3b8;
        font-size: 0.9375rem;
    }

    .cs-index__summary {
        margin-top: 0.75rem;
        text-align: center;
        font-size: 0.8125rem;
        color: #94a3b8;
    }

    /* ── Grid ── */
    .cs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
        gap: 1.25rem;
        list-style: none;
    }

    /* ── Card ── */
    .cs-card {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1.25rem;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        transition: box-shadow 0.15s, border-color 0.15s;
    }

    .cs-card:hover {
        border-color: #c7d2fe;
        box-shadow: 0 4px 16px -4px rgba(99, 102, 241, 0.12);
    }

    .cs-card__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.375rem;
    }

    .cs-card__name {
        font-size: 1.0625rem;
        font-weight: 600;
        color: #0f172a;
        margin-top: 0.125rem;
    }

    .cs-card__name-link {
        color: inherit;
        text-decoration: none;
    }

    .cs-card__name-link:hover {
        color: #6366f1;
        text-decoration: underline;
        text-underline-offset: 0.125em;
    }

    .cs-card__desc {
        font-size: 0.875rem;
        color: #64748b;
        line-height: 1.5;
        flex: 1; /* push footer down */
    }

    .cs-card__footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
    }

    .cs-card__count {
        font-size: 0.8125rem;
        color: #94a3b8;
    }

    /* ── Badges ── */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1;
    }

    .badge--type    { background: #ede9fe; color: #5b21b6; }
    .badge--system  { background: #e0f2fe; color: #0369a1; }
    .badge--public  { background: #dcfce7; color: #15803d; }
    .badge--private { background: #fef3c7; color: #92400e; }

    /* ── CTA Button ── */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.4375rem 0.875rem;
        border-radius: 0.4375rem;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: background 0.12s, opacity 0.12s;
        cursor: pointer;
    }

    .btn--primary {
        background: #6366f1;
        color: #ffffff;
    }

    .btn--primary:hover {
        background: #4f46e5;
    }
</style>
