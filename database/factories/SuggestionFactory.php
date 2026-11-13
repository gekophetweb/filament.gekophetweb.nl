<?php

namespace Database\Factories;

use App\Enums\SuggestionStatus;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suggestion>
 */
class SuggestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'requester_name' => fake()->firstName(),
            'requester_email' => fake()->safeEmail(),
            'body' => fake()->sentence(12),
        ];
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }

    public function status(SuggestionStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
