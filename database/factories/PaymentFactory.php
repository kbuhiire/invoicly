<?php

namespace Database\Factories;

use App\Enums\PaymentMatchStatus;
use App\Enums\PaymentSource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $userFactory = User::factory();

        return [
            'user_id' => $userFactory,
            'invoice_id' => Invoice::factory()->for($userFactory),
            'amount' => fake()->randomFloat(2, 100, 100000),
            'currency' => 'UGX',
            'paid_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'reference' => fake()->optional()->bothify('REF-####-????'),
            'gateway' => null,
            'external_id' => null,
            'source' => PaymentSource::Manual->value,
            'match_status' => PaymentMatchStatus::Matched->value,
            'metadata' => null,
        ];
    }

    public function unmatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => null,
            'match_status' => PaymentMatchStatus::Unmatched->value,
        ]);
    }
}
