<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchFactory extends Factory
{
    protected $model = \App\Models\FootballMatch::class;

    public function definition(): array
    {
        return [
            'external_id' => 'MTH-' . $this->faker->unique()->numerify('#####'),
            'team_id' => Team::factory(),
            'opponent' => $this->faker->randomElement(['FC Amsterdam', 'SC Rotterdam', 'VV Utrecht', 'AZ Alkmaar B']) . ' ' . $this->faker->numerify('#'),
            'match_datetime' => $this->faker->dateTimeBetween('now', '+3 months'),
            'location' => $this->faker->streetAddress(),
            'is_home' => $this->faker->boolean(),
            'status' => $this->faker->randomElement(['scheduled', 'scheduled', 'played']),
        ];
    }
}
