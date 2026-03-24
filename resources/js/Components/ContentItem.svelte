<script lang="ts">
    /**
     * ContentItem — renders a single content item according to its content type.
     *
     * Props:
     *   item            – the ContentItem record
     *   contentTypeSlug – slug of the parent set's content_type (default: 'name')
     *
     * Branching on `contentTypeSlug` keeps this component as the single place to
     * add image / video rendering later without touching Show.svelte.
     */
    import type { ContentItem } from '@/types/content';

    interface Props {
        item: ContentItem;
        /** Slug from the parent ContentSet's content_type (e.g. 'name' | 'image' | 'video'). */
        contentTypeSlug?: string;
    }

    let { item, contentTypeSlug = 'name' }: Props = $props();
</script>

<div class="content-item">
    {#if contentTypeSlug === 'name'}
        <!-- ── "name" type: plain text display ── -->
        <span class="content-item__value">{item.display_value}</span>
    {:else if contentTypeSlug === 'image'}
        <!-- ── "image" type stub: swap for <img> when images are seeded ── -->
        <span class="content-item__value content-item__value--stub">
            🖼 {item.display_value}
        </span>
    {:else if contentTypeSlug === 'video'}
        <!-- ── "video" type stub: swap for <video> / embed when ready ── -->
        <span class="content-item__value content-item__value--stub">
            🎬 {item.display_value}
        </span>
    {:else}
        <!-- ── Fallback: render display_value as-is for unknown future types ── -->
        <span class="content-item__value">{item.display_value}</span>
    {/if}
</div>

<style>
    .content-item {
        display: flex;
        align-items: center;
        padding: 0.625rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        transition: background 0.1s;
    }

    .content-item:hover {
        background: #f8fafc;
    }

    .content-item__value {
        font-size: 0.9375rem;
        color: #0f172a;
        line-height: 1.4;
    }

    .content-item__value--stub {
        color: #94a3b8;
        font-style: italic;
    }
</style>
