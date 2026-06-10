<?php

namespace App\Support;

/**
 * Shared bcmath line-item arithmetic for invoices and quotes.
 */
class LineItems
{
    /**
     * @param  array<int, array{quantity: mixed, unit_price: mixed}>  $lineItems
     */
    public static function total(array $lineItems): string
    {
        $sum = '0.00';
        foreach ($lineItems as $row) {
            $line = bcmul((string) $row['quantity'], (string) $row['unit_price'], 2);
            $sum = bcadd($sum, $line, 2);
        }

        return $sum;
    }
}
