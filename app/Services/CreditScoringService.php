<?php

namespace App\Services;

/**
 * Rule-based credit scoring (no ML). Produces a 0-100 creditworthiness score
 * (higher = safer) from precomputed payment-behaviour aggregates, blended with
 * configurable weights, plus a low/medium/high risk level and human reasons.
 */
class CreditScoringService
{
    /**
     * @param  array{
     *     paid_invoice_count:int,
     *     avg_days_to_pay:?int,
     *     on_time_rate:?int,
     *     late_count:int,
     *     overdue_amount:string,
     *     typical_invoice:string
     * }  $aggregates
     * @return array{score:?int, level:?string, reasons:list<string>}
     */
    public function score(array $aggregates): array
    {
        $config = config('invoicly.credit');

        // Not enough history to say anything credible.
        if ($aggregates['paid_invoice_count'] < $config['min_history']) {
            return ['score' => null, 'level' => null, 'reasons' => ['Not enough payment history yet']];
        }

        $weights = $config['weights'];

        $onTime = (float) ($aggregates['on_time_rate'] ?? 100);
        $speed = $this->speedComponent($aggregates['avg_days_to_pay'], (int) $config['slow_days_floor']);
        $overdue = $this->overdueComponent($aggregates['overdue_amount'], $aggregates['typical_invoice']);
        $history = $this->historyComponent($aggregates['paid_invoice_count'], (int) $config['history_full_at']);

        $score = (int) round(
            $onTime * $weights['on_time']
            + $speed * $weights['speed']
            + $overdue * $weights['overdue']
            + $history * $weights['history']
        );
        $score = max(0, min(100, $score));

        $level = $score >= $config['thresholds']['low']
            ? 'low'
            : ($score >= $config['thresholds']['medium'] ? 'medium' : 'high');

        return [
            'score' => $score,
            'level' => $level,
            'reasons' => $this->reasons($aggregates),
        ];
    }

    /** Faster payers score higher; reaches 0 at the configured slow-day floor. */
    private function speedComponent(?int $avgDays, int $floor): float
    {
        if ($avgDays === null || $floor <= 0) {
            return 100.0;
        }

        return max(0.0, min(100.0, 100.0 - ($avgDays / $floor) * 100.0));
    }

    /** Current overdue exposure relative to a typical invoice drags the score down. */
    private function overdueComponent(string $overdueAmount, string $typicalInvoice): float
    {
        if (bccomp($overdueAmount, '0', 2) <= 0 || bccomp($typicalInvoice, '0', 2) <= 0) {
            return 100.0;
        }

        $ratio = (float) bcdiv($overdueAmount, $typicalInvoice, 4);

        return max(0.0, 100.0 - min(1.0, $ratio) * 100.0);
    }

    /** More paid invoices = more confidence; reaches 100 at history_full_at. */
    private function historyComponent(int $paidCount, int $fullAt): float
    {
        if ($fullAt <= 0) {
            return 100.0;
        }

        return max(0.0, min(100.0, ($paidCount / $fullAt) * 100.0));
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return list<string>
     */
    private function reasons(array $aggregates): array
    {
        $reasons = [];

        if (($aggregates['on_time_rate'] ?? null) !== null) {
            $reasons[] = "{$aggregates['on_time_rate']}% of invoices paid on time";
        }
        if (($aggregates['avg_days_to_pay'] ?? null) !== null) {
            $reasons[] = "Pays in ~{$aggregates['avg_days_to_pay']} days on average";
        }
        if ($aggregates['late_count'] > 0) {
            $reasons[] = "{$aggregates['late_count']} late payment".($aggregates['late_count'] === 1 ? '' : 's');
        }
        if (bccomp($aggregates['overdue_amount'], '0', 2) > 0) {
            $reasons[] = 'Has overdue balance';
        }

        return $reasons;
    }
}
