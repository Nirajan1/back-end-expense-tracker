<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => $this->faker->word(),
            'type' => $this->faker->randomElement(['INCOME', 'EXPENSE']),

        ];
    }
    public function forUser($userId = 3): self
    {
        return $this->state(fn() => [
            'user_id' => $userId,
        ]);
    }
}
