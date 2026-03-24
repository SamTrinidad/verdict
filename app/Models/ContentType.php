<?php

namespace App\Models;

use Database\Factories\ContentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'label', 'config'])]
class ContentType extends Model
{
    /** @use HasFactory<ContentTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /**
     * @return HasMany<ContentSet, $this>
     */
    public function contentSets(): HasMany
    {
        return $this->hasMany(ContentSet::class);
    }
}
