<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingTier extends Model
{
    protected $fillable = ['rating_config_id', 'label', 'color', 'rank_order'];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<RatingConfig, $this>
     */
    public function ratingConfig(): BelongsTo
    {
        return $this->belongsTo(RatingConfig::class);
    }
}
