<?php

namespace Database\Factories;

use App\Models\ContentSet;
use App\Models\ContentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentSet>
 */
class ContentSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'content_type_id' => ContentType::factory(),
            'name'            => ucwords($name),
            'slug'            => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description'     => fake()->optional()->sentence(),
            'user_id'         => null,
            'visibility'      => 'private',
            'meta'            => null,
        ];
    }

    /**
     * Mark the set as system-level visibility.
     */
    public function system(): static
    {
        return $this->state(['visibility' => 'system', 'user_id' => null]);
    }

    /**
     * Mark the set as publicly visible.
     */
    public function public(): static
    {
        return $this->state(['visibility' => 'public']);
    }

    /**
     * Mark the set as private (default).
     */
    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }
}
