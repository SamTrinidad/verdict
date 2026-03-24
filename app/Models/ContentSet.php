<?php

namespace App\Models;

use Database\Factories\ContentSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['content_type_id', 'name', 'slug', 'description', 'user_id', 'visibility', 'meta'])]
class ContentSet extends Model
{
    /** @use HasFactory<ContentSetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<ContentType, $this>
     */
    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class);
    }

    /**
     * The user who owns this content set (null for system sets).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ContentItem, $this>
     */
    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class)->orderBy('sort_order');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope to sets visible to the given user (or guest when null).
     *
     * Guests see:  visibility IN ('system', 'public')
     * Auth users:  same as above  PLUS  their own private sets
     *
     * @param  Builder<ContentSet>  $query
     * @param  User|null  $user
     * @return Builder<ContentSet>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->whereIn('visibility', ['system', 'public']);

            if ($user !== null) {
                $q->orWhere(function (Builder $inner) use ($user): void {
                    $inner->where('visibility', 'private')
                          ->where('user_id', $user->id);
                });
            }
        });
    }
}
