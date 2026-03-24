<?php

namespace App\Http\Middleware;

use App\DTOs\ParticipantDTO;
use App\Models\SessionParticipant;
use App\Models\VerdictSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 3 — Full DB-backed participant identity resolution.
 *
 * When a {ulid} route parameter is present the middleware looks up the
 * session_participants table to confirm the caller is actually enrolled in
 * that specific session.  If they are not, a 403 is returned.
 *
 * When no {ulid} is present (non-session-scoped routes) the middleware falls
 * back to the Phase-1 behaviour: presence of a valid user session or a valid
 * guest_token UUID is sufficient.
 *
 * Priority order (both paths):
 *   1. Authenticated user via Laravel session  → DB lookup (ulid present) or DTO
 *   2. guest_token cookie / X-Guest-Token header → DB lookup (ulid present) or DTO
 *   3. Neither present → 401 JSON
 *
 * The resolved value is stored in request attributes:
 *   - Session-scoped routes : full SessionParticipant Eloquent model
 *   - Non-session routes    : ParticipantDTO (lightweight VO)
 *
 * Access via: $request->attributes->get('participant')
 */
class EnsureParticipantIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $ulid                 = $request->route('ulid');
        $needsParticipantCheck = $ulid !== null;

        // 1. Authenticated user takes priority.
        if ($user = $request->user()) {
            if ($needsParticipantCheck) {
                $participant = $this->lookupByUserId((string) $ulid, $user->id);

                if ($participant === null) {
                    return response()->json(
                        ['message' => 'You are not a participant in this session.'],
                        Response::HTTP_FORBIDDEN
                    );
                }

                $request->attributes->set('participant', $participant);
            } else {
                $request->attributes->set('participant', new ParticipantDTO(
                    type: 'user',
                    id: $user->id,
                    displayName: $user->name,
                ));
            }

            return $next($request);
        }

        // 2. Guest token from HttpOnly cookie or explicit header.
        $guestToken = $request->cookie('guest_token')
            ?? $request->header('X-Guest-Token');

        if ($guestToken && $this->looksLikeUuid((string) $guestToken)) {
            if ($needsParticipantCheck) {
                $participant = $this->lookupByGuestToken((string) $ulid, (string) $guestToken);

                if ($participant === null) {
                    return response()->json(
                        ['message' => 'You are not a participant in this session.'],
                        Response::HTTP_FORBIDDEN
                    );
                }

                $request->attributes->set('participant', $participant);
            } else {
                $request->attributes->set('participant', new ParticipantDTO(
                    type: 'guest',
                    id: (string) $guestToken,
                ));
            }

            return $next($request);
        }

        // 3. No recognisable identity.
        return response()->json(
            ['message' => 'Participant identity required.'],
            Response::HTTP_UNAUTHORIZED
        );
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Resolve the VerdictSession by ULID and find the participant row for a
     * registered user.  Returns null if either the session or the participant
     * row does not exist.  Aborts with 404 when the session is not found so
     * the caller sees a meaningful error rather than a 403.
     */
    private function lookupByUserId(string $ulid, int $userId): ?SessionParticipant
    {
        $session = VerdictSession::where('ulid', $ulid)->first();

        if ($session === null) {
            abort(404);
        }

        return SessionParticipant::where('session_id', $session->id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Same as lookupByUserId but matches on guest_token instead.
     */
    private function lookupByGuestToken(string $ulid, string $guestToken): ?SessionParticipant
    {
        $session = VerdictSession::where('ulid', $ulid)->first();

        if ($session === null) {
            abort(404);
        }

        return SessionParticipant::where('session_id', $session->id)
            ->where('guest_token', $guestToken)
            ->first();
    }

    /** Basic UUID shape check — used for guest tokens only. */
    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
