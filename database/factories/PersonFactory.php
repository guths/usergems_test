<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'api_key' => $this->faker->uuid,
            'avatar' => $this->faker->imageUrl(),
            'title' => $this->faker->jobTitle,
            'linkedin_url' => $this->faker->url,
            'enabled' => true,
            'company_id' => null,
            'last_synced_at' => now(),
            'is_internal' => true,
        ];
    }
}
