<script lang="ts">
    /**
     * Pagination — renders a row of page navigation buttons.
     *
     * Driven by the `meta.links` array that Laravel attaches to every
     * ResourceCollection produced with `->paginate()`.  Each link is
     * `{ url: string|null, label: string, active: boolean }`.
     *
     * - If there is only one page (links.length === 3: prev + 1 page + next)
     *   the component renders nothing.
     * - Navigation uses Inertia's `router.visit()` so the full page shell is
     *   preserved (no full-browser reload).
     * - Labels may contain HTML entities (e.g. "«  Previous"), so they are
     *   rendered with {@html …} inside a <span aria-hidden> wrapper while the
     *   accessible text comes from aria-label.
     */
    import { router } from '@inertiajs/svelte';
    import type { PaginationLink } from '@/types/pagination';

    interface Props {
        links: PaginationLink[];
    }

    let { links }: Props = $props();

    /** Only show the component when there are 2+ real pages. */
    let multiPage = $derived(links.length > 3);

    function navigate(url: string | null): void {
        if (url) router.visit(url, { preserveScroll: false });
    }

    /**
     * Strip HTML entities from a label so we can build a clean aria-label.
     * «  Previous  →  "Previous"   |   Next »  →  "Next"   |   "3"  →  "Page 3"
     */
    function ariaLabel(label: string): string {
        const text = label.replace(/&[^;]+;/g, '').trim();
        return /^\d+$/.test(text) ? `Page ${text}` : text;
    }
</script>

{#if multiPage}
    <nav class="pagination" aria-label="Page navigation">
        <ul class="pagination__list">
            {#each links as link (link.label)}
                <li>
                    {#if link.active}
                        <!-- Current page — not a button -->
                        <span
                            class="pager-btn pager-btn--active"
                            aria-current="page"
                            aria-label={ariaLabel(link.label)}
                        >
                            <span aria-hidden="true">{@html link.label}</span>
                        </span>
                    {:else if link.url}
                        <!-- Navigable page -->
                        <button
                            type="button"
                            class="pager-btn"
                            onclick={() => navigate(link.url)}
                            aria-label={ariaLabel(link.label)}
                        >
                            <span aria-hidden="true">{@html link.label}</span>
                        </button>
                    {:else}
                        <!-- Disabled (prev on page 1 / next on last page) -->
                        <span
                            class="pager-btn pager-btn--disabled"
                            aria-label={ariaLabel(link.label)}
                            aria-disabled="true"
                        >
                            <span aria-hidden="true">{@html link.label}</span>
                        </span>
                    {/if}
                </li>
            {/each}
        </ul>
    </nav>
{/if}

<style>
    .pagination {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
    }

    .pagination__list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        list-style: none;
        align-items: center;
    }

    /* ── Shared button/span base ── */
    .pager-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.25rem;
        height: 2.25rem;
        padding: 0 0.625rem;
        border-radius: 0.4375rem;
        border: 1px solid #e2e8f0;
        font-size: 0.875rem;
        font-family: inherit;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
        transition: background 0.12s, border-color 0.12s, color 0.12s;
        text-decoration: none;
        user-select: none;
    }

    /* Hover — only applies to real buttons */
    button.pager-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    /* Active / current page */
    .pager-btn--active {
        background: #6366f1;
        border-color: #6366f1;
        color: #ffffff;
        font-weight: 600;
        cursor: default;
    }

    /* Disabled (prev on p.1, next on last page) */
    .pager-btn--disabled {
        color: #cbd5e1;
        border-color: #f1f5f9;
        background: #f8fafc;
        cursor: not-allowed;
    }

    /* ── Responsive ── */
    @media (max-width: 480px) {
        .pager-btn {
            min-width: 2rem;
            height: 2rem;
            font-size: 0.8125rem;
        }
    }
</style>
