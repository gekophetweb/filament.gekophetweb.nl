<?php

namespace Database\Factories;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintRequest>
 */
class PrintRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'requester_name' => fake()->firstName(),
            'model_url' => 'https://www.printables.com/model/'.fake()->numberBetween(1000, 99999),
            'status' => PrintRequestStatus::Open,
        ];
    }

    public function printed(): static
    {
        return $this->state(['status' => PrintRequestStatus::Printed]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => PrintRequestStatus::Accepted]);
    }
}
