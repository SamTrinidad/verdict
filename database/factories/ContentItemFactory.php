<?php

namespace Database\Factories;

use App\Models\ContentItem;
use App\Models\ContentSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentItem>
 */
class ContentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $order = 0;

        return [
            'content_set_id' => ContentSet::factory(),
            'display_value'  => fake()->firstName(),
            'meta'           => null,
            'sort_order'     => ++$order,
        ];
    }
}
