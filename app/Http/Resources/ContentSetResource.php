<?php

namespace App\Http\Resources;

use App\Models\ContentSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContentSet
 */
class ContentSetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'content_type_id' => $this->content_type_id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'description'     => $this->description,
            'user_id'         => $this->user_id,
            'visibility'      => $this->visibility,
            'meta'            => $this->meta,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'content_type'    => $this->whenLoaded('contentType'),
            'content_items'   => ContentItemResource::collection($this->whenLoaded('contentItems')),
        ];
    }
}
