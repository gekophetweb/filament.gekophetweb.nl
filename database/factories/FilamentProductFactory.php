<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\FilamentProduct;
use App\Models\Material;
use App\Support\Color;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilamentProduct>
 */
class FilamentProductFactory extends Factory
{
    public function definition(): array
    {
        $hex = strtoupper(fake()->hexColor());

        return [
            'brand_id' => fn () => Brand::firstOrCreate(['name' => 'Bambu Lab'])->id,
            'material_id' => fn () => Material::firstOrCreate(['name' => 'PLA'])->id,
            'line' => fake()->randomElement(['Basic', 'Matte', 'Silk']),
            'article_name' => fake()->unique()->colorName(),
            'article_number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'color_hex' => $hex,
            'color_family' => Color::family($hex),
            'diameter' => 1.75,
            'nominal_weight' => 1000,
            'has_refill' => true,
            'is_active' => true,
        ];
    }

    public function color(string $hex): static
    {
        return $this->state(['color_hex' => $hex, 'color_family' => Color::family($hex)]);
    }
}
