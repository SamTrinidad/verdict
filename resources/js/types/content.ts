/**
 * Content System – shared TypeScript contract.
 *
 * Mirrors the DB schema (snake_case keys).  Nullable DB columns are typed
 * as `T | null`.  JSON columns are typed as `Record<string, unknown> | null`
 * unless a tighter shape is documented below.
 */

// ---------------------------------------------------------------------------
// ContentType
// ---------------------------------------------------------------------------

/** Optional per-type configuration stored as JSON. */
export interface ContentTypeConfig {
    /** Maximum character length for "name" type items. */
    max_length?: number;
    [key: string]: unknown;
}

export interface ContentType {
    id: number;
    /** Machine identifier: 'name' | 'image' | 'video' */
    slug: string;
    label: string;
    config: ContentTypeConfig | null;
    created_at: string;
    updated_at: string;
}

// ---------------------------------------------------------------------------
// ContentSet
// ---------------------------------------------------------------------------

/** Audience / ownership of a content set. */
export type ContentSetVisibility = 'system' | 'public' | 'private';

/** Arbitrary key-value metadata stored as JSON on a content set. */
export interface ContentSetMeta {
    year?: number;
    gender?: 'boy' | 'girl' | 'neutral';
    [key: string]: unknown;
}

export interface ContentSet {
    id: number;
    content_type_id: number;
    name: string;
    slug: string;
    description: string | null;
    /** null  ➜  system-owned set */
    user_id: number | null;
    visibility: ContentSetVisibility;
    meta: ContentSetMeta | null;
    created_at: string;
    updated_at: string;

    /**
     * Item count — populated when the controller loads the set with
     * `->withCount('contentItems')`.  Null when not loaded.
     */
    items_count: number | null;

    // Optional eager-loaded relations
    content_type?: ContentType;
    content_items?: ContentItem[];
}

// ---------------------------------------------------------------------------
// ContentItem
// ---------------------------------------------------------------------------

export interface ContentItem {
    id: number;
    content_set_id: number;
    display_value: string;
    meta: Record<string, unknown> | null;
    sort_order: number;
    created_at: string;
    updated_at: string;

    // Optional eager-loaded relation
    content_set?: ContentSet;
}
