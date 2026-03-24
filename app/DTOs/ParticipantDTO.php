<?php

namespace App\DTOs;

/**
 * Lightweight value object representing a participant identity.
 *
 * Phase 1: constructed purely from session/cookie data — no DB lookup.
 * Phase 3 will hydrate additional fields from session_participants.
 */
final class ParticipantDTO
{
    public function __construct(
        /** 'user' for authenticated accounts, 'guest' for cookie-based visitors */
        public readonly string $type,
        /** user_id (int) for authenticated users; UUID string for guests */
        public readonly int|string $id,
        public readonly ?string $displayName = null,
    ) {}

    public function isGuest(): bool
    {
        return $this->type === 'guest';
    }

    public function isAuthenticated(): bool
    {
        return $this->type === 'user';
    }
}
