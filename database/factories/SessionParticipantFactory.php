<?php

namespace Database\Factories;

use App\Models\SessionParticipant;
use App\Models\User;
use App\Models\VerdictSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SessionParticipant>
 */
class SessionParticipantFactory extends Factory
{
    /**
     * Default state: a registered-user participant (user_id set, guest_token null).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id'   => VerdictSession::factory(),
            'user_id'      => User::factory(),
            'guest_token'  => null,
            'display_name' => $this->faker->name(),
            'joined_at'    => now(),
            'last_seen_at' => null,
        ];
    }

    /**
     * Guest participant state (guest_token set, user_id null).
     * XOR invariant satisfied.
     */
    public function guest(): static
    {
        return $this->state([
            'user_id'      => null,
            'guest_token'  => (string) Str::uuid(),
            'display_name' => $this->faker->name(),
        ]);
    }
}
