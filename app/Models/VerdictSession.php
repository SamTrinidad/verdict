<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VerdictSession extends Model
{
    /** @use HasFactory<\Database\Factories\VerdictSessionFactory> */
    use HasFactory;

    /**
     * Use the 'verdict_sessions' table to avoid colliding with
     * Laravel's built-in session driver table ('sessions').
     */
    protected $table = 'verdict_sessions';

    protected $fillable = [
        'ulid',
        'content_set_id',
        'host_user_id',
        'status',
        'settings',
        'rating_config_id',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'host_user_id'     => 'integer',
            'content_set_id'   => 'integer',
            'rating_config_id' => 'integer',
            'settings'         => 'array',
            'expires_at'       => 'datetime',
        ];
    }

    /**
     * Auto-generate a ULID on create if one is not already set.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VerdictSession $session): void {
            if (empty($session->ulid)) {
                $session->ulid = (string) Str::ulid();
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<ContentSet, $this>
     */
    public function contentSet(): BelongsTo
    {
        return $this->belongsTo(ContentSet::class);
    }

    /**
     * The user who created and hosts this session.
     *
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /**
     * @return HasMany<SessionParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class, 'session_id');
    }

    /**
     * @return BelongsTo<RatingConfig, $this>
     */
    public function ratingConfig(): BelongsTo
    {
        return $this->belongsTo(RatingConfig::class);
    }
}
