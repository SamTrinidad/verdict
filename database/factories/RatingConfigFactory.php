<?php

namespace Database\Factories;

use App\Models\RatingConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingConfig>
 */
class RatingConfigFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type'      => $this->faker->randomElement(['numeric', 'tier']),
            'name'      => $this->faker->unique()->words(3, true),
            'is_system' => false,
        ];
    }

    /** Numeric scale config (e.g. 1-10). */
    public function numeric(): static
    {
        return $this->state(['type' => 'numeric', 'name' => 'Test Numeric Scale']);
    }

    /** Tier-based config (e.g. S/A/B/C/D). */
    public function tier(): static
    {
        return $this->state(['type' => 'tier', 'name' => 'Test Tier Config']);
    }

    /** Mark as a built-in system config. */
    public function system(): static
    {
        return $this->state(['is_system' => true]);
    }
}
