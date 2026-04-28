<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $segment = $request->string('segment', 'external')->toString();
        if (! in_array($segment, ['invoicly', 'external'], true)) {
            $segment = 'external';
        }

        $segmentType = $segment === 'invoicly' ? ClientType::Invoicly : ClientType::External;

        $base = Invoice::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('is_template', false)
                    ->orWhere('status', InvoiceStatus::Paid->value);
            })
            ->whereHas('client', fn ($q) => $q->where('type', $segmentType->value));

        // KPI: total revenue (paid)
        $totalRevenue = (clone $base)
            ->where('status', InvoiceStatus::Paid)
            ->sum('amount');

        // KPI: outstanding (awaiting payment)
        $outstanding = (clone $base)
            ->where('status', InvoiceStatus::AwaitingPayment)
            ->sum('amount');

        // KPI: overdue (awaiting payment + past due date)
        $overdueQuery = (clone $base)
            ->where('status', InvoiceStatus::AwaitingPayment)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());

        $overdueAmount = (clone $overdueQuery)->sum('amount');
        $overdueCount = (clone $overdueQuery)->count();

        // KPI: total clients
        $totalClients = Client::query()
            ->where('user_id', $user->id)
            ->where('type', $segmentType->value)
            ->count();

        // Monthly revenue: last 12 months of paid invoices grouped by month
        $twelveMonthsAgo = Carbon::now()->startOfMonth()->subMonths(11);

        $monthExpr = match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char(issue_date::date, 'YYYY-MM')",
            default => "strftime('%Y-%m', issue_date)",
        };

        $monthlyRaw = (clone $base)
            ->where('status', InvoiceStatus::Paid)
            ->whereDate('issue_date', '>=', $twelveMonthsAgo->toDateString())
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('SUM(amount) as total')
            )
            ->groupByRaw($monthExpr)
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthlyRevenue = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $key = $month->format('Y-m');
            $monthlyRevenue[] = [
                'label' => $month->format('M Y'),
                'total' => $monthlyRaw->has($key) ? (float) $monthlyRaw[$key]->total : 0.0,
            ];
        }

        // Status breakdown counts
        $paidCount = (clone $base)->where('status', InvoiceStatus::Paid)->count();
        $awaitingCount = (clone $base)->where('status', InvoiceStatus::AwaitingPayment)->count();

        // Recent invoices (last 5)
        $recentInvoices = (clone $base)
            ->with('client')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'client' => $invoice->client->name,
                'amount' => (float) $invoice->amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status->value,
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
            ]);

        // Top clients by paid invoice total (top 5)
        $topClients = Client::query()
            ->where('clients.user_id', $user->id)
            ->where('clients.type', $segmentType->value)
            ->join('invoices', 'invoices.client_id', '=', 'clients.id')
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->select('clients.name as client_name', DB::raw('SUM(invoices.amount) as total'))
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'client_name' => $row->client_name,
                'total' => (float) $row->total,
            ]);

        return Inertia::render('Dashboard', [
            'segment' => $segment,
            'preferred_currency' => $user->preferred_currency ?? 'USD',
            'kpis' => [
                'total_revenue' => (float) $totalRevenue,
                'outstanding' => (float) $outstanding,
                'overdue_amount' => (float) $overdueAmount,
                'overdue_count' => $overdueCount,
                'total_clients' => $totalClients,
            ],
            'monthly_revenue' => $monthlyRevenue,
            'status_breakdown' => [
                'paid_count' => $paidCount,
                'awaiting_count' => $awaitingCount,
            ],
            'recent_invoices' => $recentInvoices,
            'top_clients' => $topClients,
        ]);
    }
}
