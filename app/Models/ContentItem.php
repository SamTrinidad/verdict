<?php

namespace App\Models;

use Database\Factories\ContentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['content_set_id', 'display_value', 'meta', 'sort_order'])]
class ContentItem extends Model
{
    /** @use HasFactory<ContentItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ContentSet, $this>
     */
    public function contentSet(): BelongsTo
    {
        return $this->belongsTo(ContentSet::class);
    }
}
