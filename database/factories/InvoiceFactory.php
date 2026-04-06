<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $userFactory = User::factory();

        return [
            'user_id' => $userFactory,
            'client_id' => Client::factory()->for($userFactory),
            'number' => fake()->unique()->regexify('[A-Z]{4}-[0-9]{4}-[0-9]{1,4}'),
            'issue_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'status' => fake()->randomElement([
                InvoiceStatus::Paid->value,
                InvoiceStatus::AwaitingPayment->value,
            ]),
            'currency' => 'UGX',
            'amount' => fake()->randomFloat(2, 500, 500000),
            'amount_secondary' => fake()->randomFloat(2, 10, 5000),
            'currency_secondary' => 'EUR',
            'attachment_path' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function awaitingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::AwaitingPayment->value,
        ]);
    }
}
