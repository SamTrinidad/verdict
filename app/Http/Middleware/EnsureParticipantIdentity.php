<?php

namespace App\Http\Middleware;

use App\DTOs\ParticipantDTO;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 1 — Identity resolution without any DB lookup.
 *
 * Priority order:
 *   1. Authenticated user via Laravel session  → ParticipantDTO(type='user')
 *   2. guest_token cookie or X-Guest-Token header → ParticipantDTO(type='guest')
 *   3. Neither present → 401 JSON
 *
 * The resolved DTO is stored in request attributes and accessed via:
 *   $request->attributes->get('participant')
 *
 * Phase 3 will add a session_participants DB lookup once that table exists.
 * This middleware MUST NOT reference that table so it never crashes before Phase 3.
 */
class EnsureParticipantIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Authenticated user takes priority.
        if ($user = $request->user()) {
            $request->attributes->set('participant', new ParticipantDTO(
                type: 'user',
                id: $user->id,
                displayName: $user->name,
            ));

            return $next($request);
        }

        // 2. Guest token from HttpOnly cookie or explicit header.
        $guestToken = $request->cookie('guest_token')
            ?? $request->header('X-Guest-Token');

        if ($guestToken && $this->looksLikeUuid($guestToken)) {
            $request->attributes->set('participant', new ParticipantDTO(
                type: 'guest',
                id: (string) $guestToken,
            ));

            return $next($request);
        }

        // 3. No recognisable identity.
        return response()->json([
            'message' => 'Participant identity required.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /** Basic UUID shape check — full validation happens in Phase 3. */
    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
