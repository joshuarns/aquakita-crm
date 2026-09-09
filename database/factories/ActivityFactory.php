<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'type' => fake()->randomElement(array_keys(Activity::TYPES)),
            'comment' => fake()->sentence(),
            'performed_by' => User::factory(),
            'completed' => false,
        ];
    }

    /** Seguimiento vencido: fecha pasada y sin completar (§6, §8). */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'follow_up_at' => now()->subDays(2),
            'completed' => false,
        ]);
    }

    /** Seguimiento agendado para hoy, aún por vencer (§4.2 agenda del día). */
    public function dueToday(): static
    {
        return $this->state(fn () => [
            'follow_up_at' => today()->setTime(23, 30),
            'completed' => false,
        ]);
    }
}
