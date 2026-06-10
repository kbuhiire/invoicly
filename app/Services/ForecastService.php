<?php

namespace App\Services;

use App\Enums\ClientType;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Statistical cash-flow forecast (no ML). For each still-open invoice the
 * expected payment date is issue_date + the client's median days-to-pay (or a
 * configured fallback when the client has too little history). Outstanding
 * amounts are then bucketed into weekly inflows over the horizon.
 *
 * Currencies are not mixed: the forecast runs over the single currency with the
 * largest open exposure, which is reported alongside the buckets.
 */
class ForecastService
{
    /**
     * @return array{
     *     currency: string,
     *     horizon_weeks: int,
     *     total_expected: float,
     *     overdue_expected: float,
     *     buckets: list<array{week_start:string, label:string, amount:float, invoice_count:int, confidence:string}>
     * }
     */
    public function forUser(User $user, ?ClientType $segment = null): array
    {
        $horizon = (int) config('invoicly.forecast.horizon_weeks', 12);
        $minHistory = (int) config('invoicly.credit.min_history', 2);
        $defaultDays = (int) config('invoicly.forecast.default_days_to_pay', 30);

        $invoices = Invoice::query()
            ->where('user_id', $user->id)
            ->openForPayment()
            ->when($segment !== null, fn ($q) => $q->whereHas('client', fn ($c) => $c->where('type', $segment->value)))
            ->with('client')
            ->get();

        $currency = $this->dominantCurrency($invoices) ?? ($user->preferred_currency ?: 'USD');
        $invoices = $invoices->filter(fn (Invoice $i) => $i->currency === $currency);

        $startOfWeek = now()->startOfWeek();

        // Pre-seed empty weekly buckets across the horizon.
        $buckets = [];
        for ($w = 0; $w < $horizon; $w++) {
            $weekStart = $startOfWeek->copy()->addWeeks($w);
            $buckets[$w] = [
                'week_start' => $weekStart->toDateString(),
                'label' => $weekStart->format('M j'),
                'amount' => '0.00',
                'invoice_count' => 0,
                'all_high_confidence' => true,
            ];
        }

        $overdue = '0.00';
        $overdueCount = 0;

        foreach ($invoices as $invoice) {
            $client = $invoice->client;
            $hasHistory = $client
                && $client->paid_invoice_count >= $minHistory
                && $client->avg_days_to_pay !== null;

            $daysToPay = $hasHistory ? (int) $client->avg_days_to_pay : $defaultDays;
            $predicted = $invoice->issue_date->copy()->addDays($daysToPay)->startOfDay();
            $outstanding = $invoice->outstandingAmount();

            $offsetDays = (int) floor(($predicted->timestamp - $startOfWeek->timestamp) / 86400);
            $weekIdx = intdiv($offsetDays, 7);

            if ($weekIdx < 0) {
                // Expected to have arrived already — surface as overdue inflow.
                $overdue = bcadd($overdue, $outstanding, 2);
                $overdueCount++;

                continue;
            }

            if ($weekIdx >= $horizon) {
                continue; // beyond the forecast window
            }

            $buckets[$weekIdx]['amount'] = bcadd($buckets[$weekIdx]['amount'], $outstanding, 2);
            $buckets[$weekIdx]['invoice_count']++;
            if (! $hasHistory) {
                $buckets[$weekIdx]['all_high_confidence'] = false;
            }
        }

        $total = '0.00';
        $shaped = [];
        foreach ($buckets as $bucket) {
            $total = bcadd($total, $bucket['amount'], 2);
            $shaped[] = [
                'week_start' => $bucket['week_start'],
                'label' => $bucket['label'],
                'amount' => (float) $bucket['amount'],
                'invoice_count' => $bucket['invoice_count'],
                'confidence' => $bucket['invoice_count'] === 0
                    ? 'none'
                    : ($bucket['all_high_confidence'] ? 'high' : 'low'),
            ];
        }

        return [
            'currency' => $currency,
            'horizon_weeks' => $horizon,
            'total_expected' => (float) $total,
            'overdue_expected' => (float) $overdue,
            'overdue_count' => $overdueCount,
            'buckets' => $shaped,
        ];
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function dominantCurrency(Collection $invoices): ?string
    {
        if ($invoices->isEmpty()) {
            return null;
        }

        return $invoices
            ->groupBy('currency')
            ->map(fn (Collection $group) => $group->reduce(
                fn (string $carry, Invoice $i) => bcadd($carry, $i->outstandingAmount(), 2),
                '0.00'
            ))
            ->sortDesc()
            ->keys()
            ->first();
    }
}
