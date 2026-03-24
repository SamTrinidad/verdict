<?php

use App\Models\ContentSet;
use App\Models\RatingConfig;
use App\Models\SessionParticipant;
use App\Models\User;
use App\Models\VerdictSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Create session ───────────────────────────────────────────────────────────

it('creates a session with a ULID and registers the authenticated user as the host participant', function (): void {
    $user          = User::factory()->create();
    $contentSet    = ContentSet::factory()->system()->create();
    $ratingConfig  = RatingConfig::factory()->numeric()->create();

    $this->actingAs($user)
        ->post(route('sessions.store'), [
            'content_set_id'   => $contentSet->id,
            'rating_config_id' => $ratingConfig->id,
        ])
        ->assertRedirect();

    // Session was persisted with a valid ULID.
    $session = VerdictSession::first();

    expect($session)->not->toBeNull()
        ->and($session->ulid)->not->toBeEmpty()
        ->and(Str::isUlid($session->ulid))->toBeTrue()
        ->and($session->host_user_id)->toBe($user->id)
        ->and($session->status)->toBe('waiting');

    // The creator is automatically enrolled as the first participant.
    $participants = $session->participants()->get();
    expect($participants)->toHaveCount(1);

    $hostParticipant = $participants->first();
    expect($hostParticipant->user_id)->toBe($user->id)
        ->and($hostParticipant->isHost())->toBeTrue();
});

it('redirects to the lobby after successfully creating a session', function (): void {
    $user         = User::factory()->create();
    $contentSet   = ContentSet::factory()->system()->create();
    $ratingConfig = RatingConfig::factory()->numeric()->create();

    $response = $this->actingAs($user)
        ->post(route('sessions.store'), [
            'content_set_id'   => $contentSet->id,
            'rating_config_id' => $ratingConfig->id,
        ]);

    $session = VerdictSession::first();
    $response->assertRedirect(route('sessions.lobby', $session->ulid));
});

it('returns 422 when required fields are missing from store', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sessions.store'), [])
        ->assertStatus(302) // Inertia redirects back with errors
        ->assertSessionHasErrors(['content_set_id', 'rating_config_id']);
});

it('requires authentication to create a session', function (): void {
    $this->post(route('sessions.store'), [])
        ->assertRedirect(route('login'));
});

// ─── Join via ULID ────────────────────────────────────────────────────────────

it('shows the join page for a valid ULID', function (): void {
    $session = VerdictSession::factory()->create();

    $this->get(route('sessions.join', $session->ulid))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Sessions/Join')
                ->where('session.ulid', $session->ulid)
        );
});

it('creates a guest participant when entering via a valid ULID', function (): void {
    $session    = VerdictSession::factory()->create();
    $guestToken = (string) Str::uuid();

    $this->withHeader('X-Guest-Token', $guestToken)
        ->post(route('sessions.enter', $session->ulid), [
            'display_name' => 'Guest McGuest',
        ])
        ->assertRedirect(route('sessions.lobby', $session->ulid));

    expect(
        SessionParticipant::where('session_id', $session->id)
            ->where('guest_token', $guestToken)
            ->where('display_name', 'Guest McGuest')
            ->exists()
    )->toBeTrue();
});

it('creates an authenticated participant when entering via a valid ULID', function (): void {
    $user    = User::factory()->create();
    $session = VerdictSession::factory()->create();

    $this->actingAs($user)
        ->post(route('sessions.enter', $session->ulid))
        ->assertRedirect(route('sessions.lobby', $session->ulid));

    expect(
        SessionParticipant::where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->exists()
    )->toBeTrue();
});

it('does not create a duplicate participant row on re-entry', function (): void {
    $user    = User::factory()->create();
    $session = VerdictSession::factory()->create();

    // Enter twice.
    $this->actingAs($user)->post(route('sessions.enter', $session->ulid));
    $this->actingAs($user)->post(route('sessions.enter', $session->ulid));

    expect(
        SessionParticipant::where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->count()
    )->toBe(1);
});

// ─── Invalid ULID → 404 ───────────────────────────────────────────────────────

it('returns 404 on the join page for a non-existent ULID', function (): void {
    $this->get(route('sessions.join', '01JQTHISULIDDOESNOTEXIST00'))
        ->assertNotFound();
});

it('returns 404 on the enter endpoint for a non-existent ULID', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sessions.enter', '01JQTHISULIDDOESNOTEXIST00'))
        ->assertNotFound();
});

// ─── Guest token isolation (session A token rejected in session B) ────────────

it('returns 403 when a guest tries to access a lobby they did not join', function (): void {
    $guestToken = (string) Str::uuid();
    $sessionA   = VerdictSession::factory()->create();
    $sessionB   = VerdictSession::factory()->create();

    // Enrol the guest in session A only.
    SessionParticipant::factory()->guest()->create([
        'session_id'  => $sessionA->id,
        'guest_token' => $guestToken,
    ]);

    // Also enrol the session B host so the session's host participant exists.
    // (The factory already sets host_user_id on the session.)

    // Attempt to access session B's lobby using session A's guest token.
    $this->withHeader('X-Guest-Token', $guestToken)
        ->get(route('sessions.lobby', $sessionB->ulid))
        ->assertStatus(403);
});

it('allows a guest to access the lobby they joined', function (): void {
    $guestToken = (string) Str::uuid();
    $session    = VerdictSession::factory()->create();

    SessionParticipant::factory()->guest()->create([
        'session_id'  => $session->id,
        'guest_token' => $guestToken,
    ]);

    $this->withHeader('X-Guest-Token', $guestToken)
        ->get(route('sessions.lobby', $session->ulid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sessions/Lobby'));
});

// ─── Non-host cannot start session ───────────────────────────────────────────

it('prevents a non-host participant from starting the session', function (): void {
    $host    = User::factory()->create();
    $nonHost = User::factory()->create();
    $session = VerdictSession::factory()->create(['host_user_id' => $host->id]);

    // Enrol both users as participants.
    SessionParticipant::create([
        'session_id'   => $session->id,
        'user_id'      => $host->id,
        'guest_token'  => null,
        'display_name' => $host->name,
        'joined_at'    => now(),
    ]);

    SessionParticipant::create([
        'session_id'   => $session->id,
        'user_id'      => $nonHost->id,
        'guest_token'  => null,
        'display_name' => $nonHost->name,
        'joined_at'    => now(),
    ]);

    $this->actingAs($nonHost)
        ->patch(route('sessions.start', $session->ulid))
        ->assertForbidden();

    // Status must remain unchanged.
    expect($session->fresh()->status)->toBe('waiting');
});

it('allows the host to start the session', function (): void {
    $host    = User::factory()->create();
    $session = VerdictSession::factory()->create(['host_user_id' => $host->id]);

    SessionParticipant::create([
        'session_id'   => $session->id,
        'user_id'      => $host->id,
        'guest_token'  => null,
        'display_name' => $host->name,
        'joined_at'    => now(),
    ]);

    $this->actingAs($host)
        ->patch(route('sessions.start', $session->ulid))
        ->assertRedirect(route('sessions.lobby', $session->ulid));

    expect($session->fresh()->status)->toBe('active');
});

it('returns 401 when no identity is present on a participant-only route', function (): void {
    $session = VerdictSession::factory()->create();

    $this->getJson(route('sessions.lobby', $session->ulid))
        ->assertUnauthorized();
});
