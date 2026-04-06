<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLineItem>
 */
class InvoiceLineItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(3),
            'quantity' => fake()->randomElement([1, 1, 1, 2, 4, 8]),
            'unit_price' => fake()->randomFloat(2, 50, 5000),
            'sort_order' => 0,
        ];
    }
}
