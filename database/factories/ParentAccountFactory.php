<?php

namespace Database\Factories;

use App\Models\ParentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentAccount>
 */
class ParentAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'ParentPassword123!',
        ];
    }
}
