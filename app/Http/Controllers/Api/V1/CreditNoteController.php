<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CreditNoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCreditNoteRequest;
use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\CreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CreditNoteController extends Controller
{
    public function __construct(private readonly CreditNoteService $creditNotes) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CreditNote::class);

        $user = $request->user();

        abort_unless($user->tokenCan('credit-notes:read'), 403, 'Token missing credit-notes:read ability.');

        $query = CreditNote::query()
            ->where('user_id', $user->id)
            ->with('client');

        $statusFilter = $request->string('status')->toString();
        if (in_array($statusFilter, array_map(fn (CreditNoteStatus $c) => $c->value, CreditNoteStatus::cases()), true)) {
            $query->where('status', $statusFilter);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        $creditNotes = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return CreditNoteResource::collection($creditNotes)->response();
    }

    public function show(Request $request, CreditNote $creditNote): JsonResponse
    {
        Gate::authorize('view', $creditNote);

        abort_unless($request->user()->tokenCan('credit-notes:read'), 403, 'Token missing credit-notes:read ability.');

        $creditNote->load('client');

        return (new CreditNoteResource($creditNote))->response();
    }

    public function store(StoreCreditNoteRequest $request): JsonResponse
    {
        Gate::authorize('create', CreditNote::class);

        abort_unless($request->user()->tokenCan('credit-notes:write'), 403, 'Token missing credit-notes:write ability.');

        $data = $request->validated();

        $creditNote = $this->creditNotes->issue($request->user(), $data);

        if (! empty($data['apply_immediately']) && ! empty($data['invoice_id'])) {
            $invoice = Invoice::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['invoice_id']);

            $this->creditNotes->applyToInvoice($creditNote, $invoice);
        }

        $creditNote->refresh()->load('client');

        return (new CreditNoteResource($creditNote))
            ->response()
            ->setStatusCode(201);
    }

    public function apply(Request $request, CreditNote $creditNote): JsonResponse
    {
        Gate::authorize('update', $creditNote);

        abort_unless($request->user()->tokenCan('credit-notes:write'), 403, 'Token missing credit-notes:write ability.');

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer'],
        ]);

        $invoice = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($validated['invoice_id']);

        $this->creditNotes->applyToInvoice($creditNote, $invoice);

        $creditNote->refresh()->load('client');

        return (new CreditNoteResource($creditNote))->response();
    }

    public function void(Request $request, CreditNote $creditNote): JsonResponse
    {
        Gate::authorize('update', $creditNote);

        abort_unless($request->user()->tokenCan('credit-notes:write'), 403, 'Token missing credit-notes:write ability.');

        $this->creditNotes->void($creditNote);

        $creditNote->refresh()->load('client');

        return (new CreditNoteResource($creditNote))->response();
    }
}
