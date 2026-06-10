<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        $segment = $request->string('segment', 'external')->toString();
        if (! in_array($segment, ['invoicly', 'external'], true)) {
            $segment = 'external';
        }

        $segmentType = $segment === 'invoicly' ? ClientType::Invoicly : ClientType::External;

        $search = $request->string('search')->trim()->toString();

        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $scoped = Client::query()
            ->where('user_id', $request->user()->id)
            ->where('type', $segmentType->value);

        if ($search !== '') {
            $scoped->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $clients = $scoped
            ->withCount('invoices')
            ->withSum('invoices as billed_total', 'amount')
            ->withSum('invoices as paid_total', 'amount_paid')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Client $client) {
                $billed = bcadd((string) ($client->billed_total ?? '0'), '0', 2);
                $paid = bcadd((string) ($client->paid_total ?? '0'), '0', 2);
                $outstanding = bcsub($billed, $paid, 2);

                return [
                    'id' => $client->id,
                    'uuid' => $client->uuid,
                    'name' => $client->name,
                    'is_business' => $client->is_business,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'business_name' => $client->business_name,
                    'country' => $client->country,
                    'street' => $client->street,
                    'city' => $client->city,
                    'postal_code' => $client->postal_code,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'vat_number' => $client->vat_number,
                    'invoices_count' => $client->invoices_count,
                    'outstanding' => bccomp($outstanding, '0', 2) === 1 ? $outstanding : '0.00',
                    'credit_score' => $client->credit_score,
                    'credit_risk_level' => $client->credit_risk_level,
                    'flagged_for_review' => $client->flagged_for_review,
                ];
            });

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'segment' => $segment,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'per_page' => $perPage,
            ],
            'countries' => config('countries', []),
        ]);
    }

    public function show(Request $request, Client $client): Response
    {
        $this->authorize('view', $client);

        $invoices = $client->invoices()
            ->orderByDesc('issue_date')
            ->limit(10)
            ->get()
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'uuid' => $invoice->uuid,
                'number' => $invoice->number,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'status' => $invoice->status->value,
                'currency' => $invoice->currency,
                'amount' => $invoice->amount,
                'outstanding' => $invoice->outstandingAmount(),
                'is_template' => $invoice->is_template,
            ]);

        $payments = $client->payments()
            ->with('invoice:id,number')
            ->orderByDesc('paid_at')
            ->limit(10)
            ->get()
            ->map(fn ($payment) => [
                'uuid' => $payment->uuid,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toDateString(),
                'reference' => $payment->reference,
                'source' => $payment->source?->value,
                'invoice_number' => $payment->invoice?->number,
            ]);

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'uuid' => $client->uuid,
                'name' => $client->name,
                'type' => $client->type->value,
                'is_business' => $client->is_business,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'business_name' => $client->business_name,
                'country' => $client->country,
                'street' => $client->street,
                'city' => $client->city,
                'postal_code' => $client->postal_code,
                'email' => $client->email,
                'phone' => $client->phone,
                'vat_number' => $client->vat_number,
                'invoices_count' => $client->invoices()->count(),
                'paid_invoice_count' => $client->paid_invoice_count,
                'avg_days_to_pay' => $client->avg_days_to_pay,
                'on_time_rate' => $client->on_time_rate,
                'late_count' => $client->late_count,
                'credit_score' => $client->credit_score,
                'credit_risk_level' => $client->credit_risk_level,
                'flagged_for_review' => $client->flagged_for_review,
                'behavior_recomputed_at' => $client->behavior_recomputed_at?->toDateTimeString(),
            ],
            'invoices' => $invoices,
            'payments' => $payments,
            'countries' => config('countries', []),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        $data = $request->validated();
        $isBusiness = $request->isBusiness();

        $name = $isBusiness
            ? (string) $data['business_name']
            : trim((string) $data['first_name'].' '.(string) $data['last_name']);

        $client = Client::query()->create([
            'user_id' => $request->user()->id,
            'name' => $name,
            'type' => $data['type'],
            'is_business' => $isBusiness,
            'first_name' => $isBusiness ? null : $data['first_name'],
            'last_name' => $isBusiness ? null : $data['last_name'],
            'business_name' => $isBusiness ? $data['business_name'] : null,
            'country' => $data['country'],
            'street' => $data['street'],
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'vat_number' => $isBusiness ? ($data['vat_number'] ?? null) : null,
        ]);

        return redirect()
            ->route('clients.index', ['segment' => $client->type === ClientType::Invoicly ? 'invoicly' : 'external'])
            ->with('success', 'Client created.');
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $data = $request->validated();
        $isBusiness = $request->isBusiness();

        $client->is_business = $isBusiness;

        if ($isBusiness) {
            $client->business_name = $data['business_name'];
            $client->name = $data['business_name'];
            $client->first_name = null;
            $client->last_name = null;
            $client->vat_number = $data['vat_number'] ?? null;
        } else {
            $client->first_name = $data['first_name'];
            $client->last_name = $data['last_name'];
            $client->name = trim($data['first_name'].' '.$data['last_name']);
            $client->business_name = null;
            $client->vat_number = null;
        }

        $client->country = $data['country'];
        $client->street = $data['street'];
        $client->city = $data['city'];
        $client->postal_code = $data['postal_code'];
        $client->email = $data['email'] ?? null;
        $client->phone = $data['phone'] ?? null;

        $client->save();

        return back()->with('success', 'Client updated.');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        if ($client->invoices()->exists()) {
            return back()->withErrors(['client' => 'This client has existing invoices and cannot be deleted.']);
        }

        $client->delete();

        if ($request->routeIs('clients.*') && str_contains((string) url()->previous(), '/clients/')) {
            return redirect()->route('clients.index')->with('success', 'Client deleted.');
        }

        return back()->with('success', 'Client deleted.');
    }
}
