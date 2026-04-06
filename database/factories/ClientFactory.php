<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'user_id' => User::factory(),
            'name' => $company,
            'type' => ClientType::External->value,
            'is_business' => true,
            'first_name' => null,
            'last_name' => null,
            'business_name' => $company,
            'country' => 'UG',
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'email' => fake()->optional()->companyEmail(),
            'vat_number' => null,
        ];
    }

    public function invoicly(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => ClientType::Invoicly->value,
        ]);
    }

    public function external(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => ClientType::External->value,
        ]);
    }
}
