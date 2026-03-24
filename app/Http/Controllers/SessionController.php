<?php

namespace App\Http\Controllers;

use App\Models\ContentSet;
use App\Models\RatingConfig;
use App\Models\SessionParticipant;
use App\Models\VerdictSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    /**
     * Show the session creation form.
     * Only accessible to authenticated users (auth:web middleware).
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Sessions/Create', [
            'contentSets'   => ContentSet::visibleTo($user)->get(),
            'ratingConfigs' => RatingConfig::all(),
        ]);
    }

    /**
     * Create a new session, add the authenticated user as the host participant,
     * and redirect to the lobby.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'content_set_id'   => ['required', 'integer', 'exists:content_sets,id'],
            'rating_config_id' => ['required', 'integer', 'exists:rating_configs,id'],
            'settings'         => ['nullable', 'array'],
        ]);

        $ulid = (string) Str::ulid();

        $session = VerdictSession::create([
            'ulid'             => $ulid,
            'content_set_id'   => $validated['content_set_id'],
            'host_user_id'     => $request->user()->id,
            'rating_config_id' => $validated['rating_config_id'],
            'settings'         => $validated['settings'] ?? null,
            'status'           => 'waiting',
        ]);

        // Register the creator as the first (host) participant.
        SessionParticipant::create([
            'session_id'   => $session->id,
            'user_id'      => $request->user()->id,
            'guest_token'  => null,
            'display_name' => $request->user()->name,
            'joined_at'    => now(),
        ]);

        return redirect()->route('sessions.lobby', $session->ulid);
    }

    /**
     * Show the public join page for a session.
     * Accessible without authentication — anyone with the ULID can see it.
     */
    public function join(Request $request, string $ulid): Response
    {
        $session = VerdictSession::where('ulid', $ulid)->firstOrFail();

        return Inertia::render('Sessions/Join', [
            'session' => $session,
        ]);
    }

    /**
     * Create a participant record for the current user/guest and redirect to lobby.
     *
     * If the caller is already a participant (idempotent re-entry), they are
     * redirected to the lobby without creating a duplicate row.
     */
    public function enter(Request $request, string $ulid): RedirectResponse
    {
        $session = VerdictSession::where('ulid', $ulid)->firstOrFail();

        if ($user = $request->user()) {
            // ── Authenticated user ────────────────────────────────────────────
            $existing = SessionParticipant::where('session_id', $session->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $existing) {
                SessionParticipant::create([
                    'session_id'   => $session->id,
                    'user_id'      => $user->id,
                    'guest_token'  => null,
                    'display_name' => $user->name,
                    'joined_at'    => now(),
                ]);
            }
        } else {
            // ── Guest ─────────────────────────────────────────────────────────
            $guestToken = $request->cookie('guest_token')
                ?? $request->header('X-Guest-Token');

            abort_unless(
                $guestToken && $this->looksLikeUuid((string) $guestToken),
                401,
                'A valid guest token is required to join.'
            );

            $request->validate([
                'display_name' => ['required', 'string', 'max:50'],
            ]);

            $existing = SessionParticipant::where('session_id', $session->id)
                ->where('guest_token', $guestToken)
                ->first();

            if (! $existing) {
                SessionParticipant::create([
                    'session_id'   => $session->id,
                    'user_id'      => null,
                    'guest_token'  => $guestToken,
                    'display_name' => $request->input('display_name'),
                    'joined_at'    => now(),
                ]);
            }
        }

        return redirect()->route('sessions.lobby', $ulid);
    }

    /**
     * Show the session lobby.
     * Requires participant.identity middleware — caller must be a participant.
     */
    public function lobby(Request $request, string $ulid): Response
    {
        $session = VerdictSession::where('ulid', $ulid)
            ->with(['participants.user', 'contentSet', 'ratingConfig'])
            ->firstOrFail();

        return Inertia::render('Sessions/Lobby', [
            'session'      => $session,
            'participants' => $session->participants,
        ]);
    }

    /**
     * Transition the session from 'waiting' → 'active'.
     * Only the host participant may call this endpoint.
     * Requires participant.identity middleware.
     */
    public function start(Request $request, string $ulid): RedirectResponse
    {
        $session     = VerdictSession::where('ulid', $ulid)->firstOrFail();
        $participant = $request->attributes->get('participant');

        abort_unless(
            $participant instanceof SessionParticipant && $participant->isHost(),
            403,
            'Only the host can start the session.'
        );

        $session->update(['status' => 'active']);

        return redirect()->route('sessions.lobby', $ulid);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Basic UUID shape check for guest tokens. */
    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
