<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreditNoteRequest;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\CreditNoteService;
use App\Support\PdfAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;

class CreditNoteController extends Controller
{
    public function __construct(private readonly CreditNoteService $creditNotes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CreditNote::class);

        $user = $request->user();

        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $creditNotes = CreditNote::query()
            ->where('user_id', $user->id)
            ->with(['client:id,uuid,name', 'invoice:id,uuid,number'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CreditNote $note) => [
                'id' => $note->id,
                'uuid' => $note->uuid,
                'number' => $note->number,
                'status' => $note->status->value,
                'issue_date' => $note->issue_date->format('Y-m-d'),
                'currency' => $note->currency,
                'amount' => $note->amount,
                'memo' => $note->memo,
                'applied_at' => $note->applied_at?->toDateTimeString(),
                'client' => [
                    'id' => $note->client?->id,
                    'name' => $note->client?->name ?? '—',
                ],
                'invoice' => $note->invoice ? [
                    'uuid' => $note->invoice->uuid,
                    'number' => $note->invoice->number,
                ] : null,
            ]);

        $clients = Client::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $openInvoices = Invoice::query()
            ->where('user_id', $user->id)
            ->openForPayment()
            ->with('client:id,name')
            ->orderByDesc('issue_date')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client?->name ?? '—',
                'currency' => $invoice->currency,
                'outstanding' => $invoice->outstandingAmount(),
            ]);

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes,
            'clients' => $clients,
            'openInvoices' => $openInvoices,
            'prefill' => [
                'invoice_id' => $request->integer('invoice_id') ?: null,
            ],
        ]);
    }

    public function store(StoreCreditNoteRequest $request): RedirectResponse
    {
        $this->authorize('create', CreditNote::class);

        $data = $request->validated();

        $creditNote = $this->creditNotes->issue($request->user(), $data);

        if (! empty($data['apply_immediately']) && ! empty($data['invoice_id'])) {
            $invoice = Invoice::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['invoice_id']);

            $this->creditNotes->applyToInvoice($creditNote, $invoice);

            return back()->with('success', "Credit note {$creditNote->number} issued and applied to {$invoice->number}.");
        }

        return back()->with('success', "Credit note {$creditNote->number} issued.");
    }

    public function apply(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer'],
        ]);

        $invoice = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($validated['invoice_id']);

        $this->creditNotes->applyToInvoice($creditNote, $invoice);

        return back()->with('success', "Credit note {$creditNote->number} applied to {$invoice->number}.");
    }

    public function void(CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);

        $this->creditNotes->void($creditNote);

        return back()->with('success', "Credit note {$creditNote->number} voided.");
    }

    public function pdf(CreditNote $creditNote)
    {
        $this->authorize('view', $creditNote);

        $creditNote->load(['client', 'invoice', 'user']);
        $issuer = $creditNote->user;

        return Pdf::view('pdfs.credit-note', [
            'creditNote' => $creditNote,
            'issuer' => $issuer,
            'issuerLogoUri' => PdfAsset::dataUriFromPublicPath($issuer?->logo_path),
        ])
            ->format('a4')
            ->name($creditNote->number.'.pdf');
    }
}
