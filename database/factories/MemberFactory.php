<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => 'MBR-' . $this->faker->unique()->numerify('#####'),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'date_of_birth' => $this->faker->dateTimeBetween('-40 years', '-8 years')->format('Y-m-d'),
            'role' => $this->faker->randomElement(['player', 'player', 'player', 'coach']),
            'is_active' => true,
        ];
    }
}
