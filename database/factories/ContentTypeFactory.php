<?php

namespace Database\Factories;

use App\Models\ContentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentType>
 */
class ContentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->word();

        return [
            'slug'   => Str::slug($label),
            'label'  => ucfirst($label),
            'config' => null,
        ];
    }

    /**
     * Use the canonical "name" content type (for baby names).
     */
    public function name(): static
    {
        return $this->state([
            'slug'  => 'name',
            'label' => 'Baby Names',
        ]);
    }
}
