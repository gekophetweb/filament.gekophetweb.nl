<?php

namespace Database\Factories;

use App\Enums\SpoolStatus;
use App\Models\FilamentProduct;
use App\Models\Spool;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Spool>
 */
class SpoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filament_product_id' => FilamentProduct::factory(),
            'status' => SpoolStatus::Boxed,
            'is_refill' => false,
            'purchased_at' => now()->subDays(10),
            'location' => 'Shelf',
        ];
    }

    public function onSpool(): static
    {
        return $this->state(['status' => SpoolStatus::OnSpool]);
    }

    public function usedUp(): static
    {
        return $this->state(['status' => SpoolStatus::UsedUp]);
    }
}
