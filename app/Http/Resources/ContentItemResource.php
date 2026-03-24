<?php

namespace App\Http\Resources;

use App\Models\ContentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContentItem
 */
class ContentItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'content_set_id' => $this->content_set_id,
            'display_value'  => $this->display_value,
            'meta'           => $this->meta,
            'sort_order'     => $this->sort_order,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
