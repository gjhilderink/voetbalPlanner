<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => 'EXT-' . $this->faker->unique()->numerify('####'),
            'name' => $this->faker->randomElement(['JO8-1', 'JO10-2', 'JO12-1', 'JO15-3', 'Senioren 1']) . ' ' . $this->faker->city(),
            'category' => $this->faker->randomElement(['Junioren', 'Senioren', 'Meisjes']),
            'age_group' => $this->faker->randomElement(['JO8', 'JO10', 'JO12', 'JO15', 'JO17', 'Senioren']),
            'season' => '2024-2025',
            'is_active' => true,
        ];
    }
}
