<?php

namespace Database\Factories;

use App\Models\ContentSet;
use App\Models\RatingConfig;
use App\Models\User;
use App\Models\VerdictSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VerdictSession>
 */
class VerdictSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid'             => (string) Str::ulid(),
            'content_set_id'   => ContentSet::factory(),
            'host_user_id'     => User::factory(),
            'status'           => 'waiting',
            'settings'         => null,
            'rating_config_id' => RatingConfig::factory()->numeric(),
            'expires_at'       => null,
        ];
    }

    /** Session is open and accepting participants. */
    public function waiting(): static
    {
        return $this->state(['status' => 'waiting']);
    }

    /** Rating is in progress. */
    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    /** Session has finished. */
    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }
}
