<?php

namespace Database\Factories;

use App\Models\LeadForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadForm>
 */
class LeadFormFactory extends Factory
{
    protected $model = LeadForm::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'token' => LeadForm::generateToken(),
            'source_id' => null,
            'campaign_id' => null,
            'fields' => LeadForm::defaultFields(),
            'success_message' => '¡Gracias! Hemos recibido tus datos.',
            'redirect_url' => null,
            'allowed_domains' => null,
            'accent_color' => '#0e7490',
            'active' => true,
            'submissions_count' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
