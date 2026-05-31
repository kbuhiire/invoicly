<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes and caches each client's payment-behaviour aggregates and credit
 * score onto the clients table. These feed both credit insights and cash-flow
 * forecasting, so they live in one place and are refreshed nightly and on every
 * payment received. Pure statistics — no ML.
 */
class ClientBehaviorService
{
    public function __construct(private readonly CreditScoringService $scoring) {}

    public function recalculateForUser(User $user): int
    {
        $count = 0;
        $user->clients()->each(function (Client $client) use (&$count) {
            $this->recalculate($client);
            $count++;
        });

        return $count;
    }

    public function recalculate(Client $client): void
    {
        $invoices = $client->invoices()->where('is_template', false)->get();

        // Invoices actually settled with a recorded date carry payment timing.
        $paid = $invoices->filter(
            fn (Invoice $i) => $i->status === InvoiceStatus::Paid && $i->paid_at !== null
        );

        $days = $paid
            ->map(fn (Invoice $i) => $this->daysBetween($i->issue_date, $i->paid_at))
            ->sort()
            ->values();

        $dueDatedPaid = $paid->filter(fn (Invoice $i) => $i->due_date !== null);
        $onTimeRate = $dueDatedPaid->isNotEmpty()
            ? (int) round(
                $dueDatedPaid->filter(fn (Invoice $i) => $i->paid_at->lte($i->due_date->endOfDay()))->count()
                / $dueDatedPaid->count() * 100
            )
            : null;

        $lateCount = $dueDatedPaid
            ->filter(fn (Invoice $i) => $i->paid_at->gt($i->due_date->endOfDay()))
            ->count();

        $aggregates = [
            'paid_invoice_count' => $paid->count(),
            'avg_days_to_pay' => $this->median($days),
            'on_time_rate' => $onTimeRate,
            'late_count' => $lateCount,
            'overdue_amount' => $this->overdueAmount($invoices),
            'typical_invoice' => $this->typicalInvoice($invoices),
        ];

        $scored = $this->scoring->score($aggregates);

        $client->forceFill([
            'paid_invoice_count' => $aggregates['paid_invoice_count'],
            'avg_days_to_pay' => $aggregates['avg_days_to_pay'],
            'on_time_rate' => $aggregates['on_time_rate'],
            'late_count' => $aggregates['late_count'],
            'credit_score' => $scored['score'],
            'credit_risk_level' => $scored['level'],
            'flagged_for_review' => $scored['level'] === 'high',
            'behavior_recomputed_at' => now(),
        ])->save();
    }

    private function daysBetween(Carbon $issue, Carbon $paid): int
    {
        $diff = (int) floor(
            ($paid->copy()->startOfDay()->timestamp - $issue->copy()->startOfDay()->timestamp) / 86400
        );

        return max(0, $diff);
    }

    /**
     * @param  Collection<int, int>  $values  pre-sorted
     */
    private function median(Collection $values): ?int
    {
        $n = $values->count();
        if ($n === 0) {
            return null;
        }

        $mid = intdiv($n, 2);

        if ($n % 2 === 1) {
            return (int) $values[$mid];
        }

        return (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function overdueAmount(Collection $invoices): string
    {
        $today = now()->startOfDay();

        return $invoices
            ->filter(fn (Invoice $i) => $i->status !== InvoiceStatus::Paid
                && $i->due_date !== null
                && $i->due_date->lt($today))
            ->reduce(fn (string $carry, Invoice $i) => bcadd($carry, $i->outstandingAmount(), 2), '0.00');
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function typicalInvoice(Collection $invoices): string
    {
        if ($invoices->isEmpty()) {
            return '0.00';
        }

        $sum = $invoices->reduce(fn (string $carry, Invoice $i) => bcadd($carry, (string) $i->amount, 2), '0.00');

        return bcdiv($sum, (string) $invoices->count(), 2);
    }
}
