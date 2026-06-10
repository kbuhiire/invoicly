<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const BUCKETS = ['current', 'b1_30', 'b31_60', 'b61_90', 'b90_plus'];

    public function aging(Request $request): Response
    {
        ['rows' => $rows, 'totals' => $totals] = $this->agingData($request);

        return Inertia::render('Reports/Aging', [
            'rows' => array_values($rows),
            'totals' => array_values($totals),
            'generated_at' => now()->toDateString(),
        ]);
    }

    public function agingExport(Request $request): StreamedResponse
    {
        ['rows' => $rows] = $this->agingData($request);

        $filename = 'ar-aging-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Client', 'Currency', 'Current', '1-30 days', '31-60 days', '61-90 days', '90+ days', 'Total']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['client_name'],
                    $row['currency'],
                    $row['buckets']['current'],
                    $row['buckets']['b1_30'],
                    $row['buckets']['b31_60'],
                    $row['buckets']['b61_90'],
                    $row['buckets']['b90_plus'],
                    $row['total'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Outstanding balances bucketed by days overdue, grouped per client AND
     * currency — amounts in different currencies are never summed together.
     *
     * @return array{rows: array<string, array<string, mixed>>, totals: array<string, array<string, mixed>>}
     */
    private function agingData(Request $request): array
    {
        $today = now()->startOfDay();

        $invoices = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->openForPayment()
            ->with('client:id,uuid,name,type')
            ->get();

        $rows = [];
        $totals = [];

        foreach ($invoices as $invoice) {
            $bucket = $this->bucketFor($invoice, $today);
            $outstanding = $invoice->outstandingAmount();

            if (bccomp($outstanding, '0', 2) <= 0) {
                continue;
            }

            $rowKey = $invoice->client_id.':'.$invoice->currency;
            if (! isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'client_id' => $invoice->client_id,
                    'client_uuid' => $invoice->client?->uuid,
                    'client_name' => $invoice->client?->name ?? '—',
                    'client_type' => $invoice->client?->type?->value ?? 'external',
                    'currency' => $invoice->currency,
                    'buckets' => array_fill_keys(self::BUCKETS, '0.00'),
                    'total' => '0.00',
                ];
            }

            if (! isset($totals[$invoice->currency])) {
                $totals[$invoice->currency] = [
                    'currency' => $invoice->currency,
                    'buckets' => array_fill_keys(self::BUCKETS, '0.00'),
                    'total' => '0.00',
                ];
            }

            $rows[$rowKey]['buckets'][$bucket] = bcadd($rows[$rowKey]['buckets'][$bucket], $outstanding, 2);
            $rows[$rowKey]['total'] = bcadd($rows[$rowKey]['total'], $outstanding, 2);
            $totals[$invoice->currency]['buckets'][$bucket] = bcadd($totals[$invoice->currency]['buckets'][$bucket], $outstanding, 2);
            $totals[$invoice->currency]['total'] = bcadd($totals[$invoice->currency]['total'], $outstanding, 2);
        }

        uasort($rows, fn (array $a, array $b) => bccomp($b['total'], $a['total'], 2));
        ksort($totals);

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function bucketFor(Invoice $invoice, \Illuminate\Support\Carbon $today): string
    {
        if ($invoice->due_date === null || $invoice->due_date->gte($today)) {
            return 'current';
        }

        $daysOverdue = (int) $invoice->due_date->startOfDay()->diffInDays($today);

        return match (true) {
            $daysOverdue <= 30 => 'b1_30',
            $daysOverdue <= 60 => 'b31_60',
            $daysOverdue <= 90 => 'b61_90',
            default => 'b90_plus',
        };
    }
}
