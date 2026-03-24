<script lang="ts">
    /**
     * ContentSets/Show — detail view for one content contentSet.
     *
     * Props (injected by ContentSetController@show via Inertia):
     *   contentSet    – single ContentSetResource (with contentType eager-loaded)
     *   items  – paginated ResourceCollection<ContentItemResource>
     *   auth   – authenticated User object, or null for guests
     *
     * Rendering of individual items is delegated entirely to <ContentItem>.
     * When image / video support lands, only ContentItem.svelte needs to change.
     */
    import AppLayout from '@/Layouts/AppLayout.svelte';
    import ContentItem from '@/Components/ContentItem.svelte';
    import Pagination from '@/Components/Pagination.svelte';
    import type { ContentSet, ContentItem as ContentItemType } from '@/types/content';
    import type { Paginated } from '@/types/pagination';

    // ── Auth user shape ───────────────────────────────────────────────────────
    interface AuthUser {
        id: number;
        name: string;
        email: string;
    }

    interface Props {
        contentSet: ContentSet;
        items: Paginated<ContentItemType>;
        auth: AuthUser | null;
    }

    let { contentSet, items, auth }: Props = $props();

    /** Resolve the content-type slug from the eager-loaded relation. */
    let contentTypeSlug = $derived(contentSet.content_type?.slug ?? 'name');

    /** Page heading: include page number when browsing deep. */
    let pageTitle = $derived(
        items.meta.current_page > 1
            ? `${contentSet.name} — page ${items.meta.current_page}`
            : contentSet.name
    );
</script>

<AppLayout title={pageTitle}>
    <div class="cs-show">

        <!-- ── Set header ──────────────────────────────────────────────────── -->
        <header class="cs-show__header">
            <nav class="cs-show__breadcrumb" aria-label="Breadcrumb">
                <a href="/content_contentSets">Content Sets</a>
                <span aria-hidden="true"> / </span>
                <span aria-current="page">{contentSet.name}</span>
            </nav>

            <div class="cs-show__title-row">
                <h1 class="cs-show__title">{contentSet.name}</h1>

                <!-- Content-type pill -->
                {#if contentSet.content_type}
                    <span class="badge badge--type">{contentSet.content_type.label}</span>
                {/if}
            </div>

            {#if contentSet.description}
                <p class="cs-show__desc">{contentSet.description}</p>
            {/if}

            <!-- Stats row -->
            <div class="cs-show__stats">
                {#if contentSet.items_count !== null}
                    <span>{contentSet.items_count} item{contentSet.items_count === 1 ? '' : 's'}</span>
                {/if}
                {#if auth}
                    <span class="cs-show__user">Signed in as {auth.name}</span>
                {/if}
            </div>

            <!-- CTA — stub href for Phase 3 session creation -->
            <a
                href="/sessions/create?content_contentSet={contentSet.slug}"
                class="btn btn--primary"
                aria-label="Use {contentSet.name} in a new rating session"
            >
                Use this contentSet
            </a>
        </header>

        <hr class="cs-show__divider" />

        <!-- ── Items list ──────────────────────────────────────────────────── -->
        {#if items.data.length === 0}
            <p class="cs-show__empty">This contentSet has no items yet.</p>
        {:else}
            <ul class="cs-show__items" role="list">
                {#each items.data as item (item.id)}
                    <li>
                        <!--
                            ContentItem is the single owner of per-type rendering.
                            contentTypeSlug flows down from the contentSet so the component
                            can branch without knowing its parent.
                        -->
                        <ContentItem {item} {contentTypeSlug} />
                    </li>
                {/each}
            </ul>

            <!-- ── Pagination ──────────────────────────────────────────────── -->
            <Pagination links={items.meta.links} />

            <!-- Summary -->
            <p class="cs-show__summary">
                Showing {items.meta.from ?? 0}–{items.meta.to ?? 0}
                of {items.meta.total} item{items.meta.total === 1 ? '' : 's'}
            </p>
        {/if}

    </div>
</AppLayout>

<style>
    /* ── Page shell ── */
    .cs-show {
        max-width: 48rem;
        margin-inline: auto;
    }

    /* ── Header ── */
    .cs-show__header {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .cs-show__breadcrumb {
        font-size: 0.8125rem;
        color: #94a3b8;
    }

    .cs-show__breadcrumb a {
        color: #6366f1;
        text-decoration: none;
    }

    .cs-show__breadcrumb a:hover {
        text-decoration: underline;
    }

    .cs-show__title-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.625rem;
        margin-top: 0.25rem;
    }

    .cs-show__title {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: #0f172a;
    }

    .cs-show__desc {
        font-size: 0.9375rem;
        color: #64748b;
        line-height: 1.6;
    }

    .cs-show__stats {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        font-size: 0.875rem;
        color: #94a3b8;
    }

    .cs-show__user {
        margin-left: auto;
    }

    /* ── Badge (content-type pill) ── */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1;
    }

    .badge--type { background: #ede9fe; color: #5b21b6; }

    /* ── CTA ── */
    .btn {
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 0.4375rem;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: background 0.12s;
        margin-top: 0.25rem;
    }

    .btn--primary {
        background: #6366f1;
        color: #ffffff;
    }

    .btn--primary:hover {
        background: #4f46e5;
    }

    /* ── Divider ── */
    .cs-show__divider {
        border: none;
        border-top: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }

    /* ── Items list ── */
    .cs-show__items {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        list-style: none;
    }

    .cs-show__empty {
        padding: 3rem 1rem;
        text-align: center;
        color: #94a3b8;
        font-size: 0.9375rem;
    }

    .cs-show__summary {
        margin-top: 0.75rem;
        text-align: center;
        font-size: 0.8125rem;
        color: #94a3b8;
    }
</style>
