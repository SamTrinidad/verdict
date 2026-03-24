<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatingConfig extends Model
{
    /** @use HasFactory<\Database\Factories\RatingConfigFactory> */
    use HasFactory;

    protected $fillable = ['type', 'name', 'is_system'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @return HasMany<RatingTier, $this>
     */
    public function ratingTiers(): HasMany
    {
        return $this->hasMany(RatingTier::class)->orderBy('rank_order');
    }
}
