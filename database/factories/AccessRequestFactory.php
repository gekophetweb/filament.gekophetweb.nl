<?php

namespace Database\Factories;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessRequest>
 */
class AccessRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->firstName(),
            'message' => null,
        ];
    }

    public function declined(): static
    {
        return $this->state(['status' => AccessRequestStatus::Declined]);
    }
}
