/**
 * Session System – shared TypeScript contract.
 *
 * Mirrors the DB schema (snake_case keys).  Nullable DB columns are typed
 * as `T | null`.  JSON columns are typed with documented shapes where known.
 *
 * Table naming note: the Verdict session model uses `verdict_sessions` in the
 * database to avoid colliding with Laravel's built-in `sessions` table.
 */

import type { ContentSet } from './content';

// ---------------------------------------------------------------------------
// RatingConfig
// ---------------------------------------------------------------------------

/** Discriminator for the two supported rating strategies. */
export type RatingConfigType = 'numeric' | 'tier';

export interface RatingConfig {
    id: number;
    type: RatingConfigType;
    /** Human-readable name, e.g. "1-10 Scale" or "S-Tier". */
    name: string;
    /** System configs are created by seeders and cannot be deleted by users. */
    is_system: boolean;
    created_at: string;
    updated_at: string;

    // Optional eager-loaded relation
    rating_tiers?: RatingTier[];
}

// ---------------------------------------------------------------------------
// RatingTier
// ---------------------------------------------------------------------------

export interface RatingTier {
    id: number;
    rating_config_id: number;
    /** Display label shown in the UI, e.g. "S", "A", "B". */
    label: string;
    /** CSS hex colour string including the leading #, e.g. "#FF7F7F". */
    color: string;
    /**
     * Ordinal rank — lower is better.
     * rank_order=1 is the highest tier (S), rank_order=5 is the lowest (D).
     */
    rank_order: number;
    created_at: string;
    updated_at: string;

    // Optional eager-loaded relation
    rating_config?: RatingConfig;
}

// ---------------------------------------------------------------------------
// Session (verdict_sessions)
// ---------------------------------------------------------------------------

/** Lifecycle state of a Verdict session. */
export type SessionStatus = 'waiting' | 'active' | 'completed';

/**
 * Per-session configuration overrides stored as JSON.
 * Extend this interface as the settings schema evolves.
 */
export interface SessionSettings {
    /** Whether participant identities are hidden from other voters. */
    anonymous_votes?: boolean;
    /** Whether results are revealed only after everyone has voted. */
    reveal_after_all_voted?: boolean;
    [key: string]: unknown;
}

export interface Session {
    id: number;
    /**
     * Public ULID used as the human-readable join code (safe to embed in URLs).
     * Example: "01HV2X3Y4Z5A6B7C8D9E0F1G2H"
     */
    ulid: string;
    content_set_id: number;
    /** null when the session is anonymous / host-less. */
    host_user_id: number | null;
    status: SessionStatus;
    settings: SessionSettings | null;
    rating_config_id: number;
    created_at: string;
    updated_at: string;
    /** ISO-8601 timestamp; null means the session never expires. */
    expires_at: string | null;

    // Optional eager-loaded relations
    content_set?: ContentSet;
    rating_config?: RatingConfig;
    participants?: SessionParticipant[];
}

// ---------------------------------------------------------------------------
// SessionParticipant
// ---------------------------------------------------------------------------

/**
 * Identity constraint — application-layer enforced (XOR):
 *   (user_id IS NOT NULL) XOR (guest_token IS NOT NULL)
 *
 * Exactly one of `user_id` or `guest_token` must be non-null.
 *
 * Note: A DB-level CHECK constraint cannot reference foreign-key columns in
 * MySQL 8.0 (error 3823), so the invariant is validated in the
 * SessionParticipant model (boot/creating observer) and at the
 * controller/service layer before insert.
 */
export type SessionParticipant =
    | SessionParticipantUser
    | SessionParticipantGuest;

interface SessionParticipantBase {
    id: number;
    session_id: number;
    display_name: string;
    joined_at: string;
    last_seen_at: string | null;

    // Optional eager-loaded relation
    session?: Session;
}

/** A registered user participating in a session. */
export interface SessionParticipantUser extends SessionParticipantBase {
    user_id: number;
    guest_token: null;
}

/** An anonymous guest participating via a one-time token. */
export interface SessionParticipantGuest extends SessionParticipantBase {
    user_id: null;
    guest_token: string;
}
