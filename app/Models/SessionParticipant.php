<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class SessionParticipant extends Model
{
    /** @use HasFactory<\Database\Factories\SessionParticipantFactory> */
    use HasFactory;

    /**
     * No created_at / updated_at columns — the table uses joined_at and
     * last_seen_at instead.
     */
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'user_id',
        'guest_token',
        'display_name',
        'joined_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_id'   => 'integer',
            'user_id'      => 'integer',
            'joined_at'    => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Enforce the XOR identity constraint at the application layer.
     * MySQL 8.0 cannot use FK columns in CHECK constraints (error 3823).
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SessionParticipant $participant): void {
            $hasUser  = $participant->user_id !== null;
            $hasGuest = $participant->guest_token !== null;

            if ($hasUser === $hasGuest) {
                throw new InvalidArgumentException(
                    'A SessionParticipant must have exactly one of user_id or guest_token set (XOR).'
                );
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<VerdictSession, $this>
     */
    public function verdictSession(): BelongsTo
    {
        return $this->belongsTo(VerdictSession::class, 'session_id');
    }

    /**
     * The registered user, or null for a guest participant.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Returns true when this participant is the session host.
     *
     * Guests can never be hosts (host_user_id is always a registered user).
     * Also handles the edge case where host_user_id was nulled by cascading
     * delete (nullOnDelete).
     */
    public function isHost(): bool
    {
        if ($this->user_id === null) {
            return false;
        }

        $session = $this->loadMissing('verdictSession')->verdictSession;

        if ($session->host_user_id === null) {
            return false;
        }

        return (int) $session->host_user_id === (int) $this->user_id;
    }
}
