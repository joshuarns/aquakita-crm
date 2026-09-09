<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('55########'),
            'status_id' => Status::firstOrCreate(
                ['order' => 1],
                ['name' => 'Nuevo', 'is_final' => false, 'is_won' => false, 'active' => true],
            )->id,
        ];
    }
}
