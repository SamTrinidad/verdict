/**
 * Pagination — shared generic contract.
 *
 * Mirrors the JSON shape that Laravel's `ResourceCollection::collection($paginator)`
 * produces: top-level `data` + `links` + `meta`.
 *
 * The `meta.links` array is used to render page navigation buttons.
 */

/** A single entry in `meta.links` (prev / numbered / next). */
export interface PaginationLink {
    /** Full URL for this page, or null when the button should be disabled. */
    url: string | null;
    /** Human-readable label.  May contain HTML entities (e.g. "&laquo; Previous"). */
    label: string;
    /** Whether this entry represents the current page. */
    active: boolean;
}

/** Cursor-style prev/next shortcut URLs. */
export interface PaginationNavLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

/** Full pagination metadata block. */
export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    /** Full list of page links including Prev, numbered pages, and Next. */
    links: PaginationLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
}

/**
 * A generic paginated collection from Laravel + Inertia.
 *
 * @example
 * let { contentSets }: { contentSets: Paginated<ContentSet> } = $props();
 */
export interface Paginated<T> {
    data: T[];
    links: PaginationNavLinks;
    meta: PaginationMeta;
}
