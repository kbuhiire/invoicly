<?php

namespace App\Services\Reminders;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Decides whether (and which) reminder is due for an invoice on a given day.
 * Pure and side-effect free so the timing heuristic is easy to test.
 *
 * Timeline for an open invoice, anchored on its due date:
 *
 *   [ due - lead ] ───── upcoming ───── [ due + grace ] ──── overdue (repeating) ──→
 *
 * The lead time is widened for clients who historically pay late (their median
 * days-to-pay exceeds the invoice's net terms, or they're flagged high risk),
 * so chronic late payers are nudged earlier. Overdue reminders repeat on a
 * fixed interval up to a cap; each occurrence gets its own dedupe key.
 */
class ReminderPlanner
{
    /**
     * @return array{type: 'upcoming'|'overdue', dedupe_key: string}|null
     */
    public function plan(Invoice $invoice, ?Carbon $today = null): ?array
    {
        $today = ($today ?? Date::now())->copy()->startOfDay();

        if ($invoice->due_date === null || $invoice->client === null) {
            return null;
        }

        $minOutstanding = (string) config('invoicly.reminders.min_outstanding', '0');
        if (bccomp($invoice->outstandingAmount(), $minOutstanding, 2) <= 0) {
            return null;
        }

        $due = $invoice->due_date->copy()->startOfDay();
        $graceDays = (int) config('invoicly.reminders.overdue_grace_days', 1);
        $overdueStart = $due->copy()->addDays($graceDays);

        if ($today->gte($overdueStart)) {
            return $this->overduePlan($overdueStart, $today);
        }

        $lead = (int) config('invoicly.reminders.lead_days', 3);
        if ($this->isLatePayer($invoice)) {
            $lead += (int) config('invoicly.reminders.late_payer_extra_lead_days', 4);
        }

        $windowStart = $due->copy()->subDays($lead);
        if ($today->gte($windowStart)) {
            return ['type' => 'upcoming', 'dedupe_key' => 'upcoming'];
        }

        return null;
    }

    /**
     * @return array{type: 'overdue', dedupe_key: string}|null
     */
    private function overduePlan(Carbon $overdueStart, Carbon $today): ?array
    {
        $interval = max(1, (int) config('invoicly.reminders.overdue_interval_days', 7));
        $max = (int) config('invoicly.reminders.max_overdue_reminders', 4);

        $iteration = intdiv($this->daysBetween($overdueStart, $today), $interval);

        if ($iteration >= $max) {
            return null; // stop nagging
        }

        return ['type' => 'overdue', 'dedupe_key' => "overdue:{$iteration}"];
    }

    private function isLatePayer(Invoice $invoice): bool
    {
        $client = $invoice->client;

        if ($client->flagged_for_review) {
            return true;
        }

        if ($client->avg_days_to_pay === null || $invoice->issue_date === null || $invoice->due_date === null) {
            return false;
        }

        $netTerms = $this->daysBetween(
            $invoice->issue_date->copy()->startOfDay(),
            $invoice->due_date->copy()->startOfDay()
        );

        return $netTerms > 0 && $client->avg_days_to_pay > $netTerms;
    }

    private function daysBetween(Carbon $start, Carbon $end): int
    {
        return (int) floor(($end->timestamp - $start->timestamp) / 86400);
    }
}
